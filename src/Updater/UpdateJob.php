<?php

namespace MediaWiki\Extension\Produnto\Updater;

use Generator;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Produnto\RepoViewer\RepoLinker;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Purge or invalidate caches following a deployment
 */
class UpdateJob extends Job {
	private int $updateRowsPerJob;
	private int $linkTargetBatchSize;
	private int $jobBatchSize;
	private int $purgeBatchSize;
	private bool $isCdnEnabled;

	/** @var JobSpecification[] Pending jobs to be pushed */
	private $jobs = [];
	/** @var string[] Pending URLs to be purged */
	private $urls = [];
	/** @var LinkTarget[] Pending link targets which have changed */
	private $linkTargets = [];

	/**
	 * @var array<int,array<string,bool>> Namespace and DB key of link targets
	 *   we've already processed, for deduplication.
	 */
	private $seenLinks = [];

	/**
	 * @param array $params
	 *   - oldId: The previously deployed deployment ID, or zero if there wasn't one
	 *   - newId: The new deployment ID, which was active at the time the job was queued
	 * @param ProduntoStore $store
	 * @param RepoLinker $repoLinker
	 * @param IConnectionProvider $dbProvider
	 * @param JobQueueGroup $jobQueueGroup
	 * @param Config $config
	 */
	public function __construct(
		$params,
		private ProduntoStore $store,
		private RepoLinker $repoLinker,
		private IConnectionProvider $dbProvider,
		private JobQueueGroup $jobQueueGroup,
		Config $config,
	) {
		parent::__construct( 'ProduntoUpdate', $params );
		$this->updateRowsPerJob = $config->get( MainConfigNames::UpdateRowsPerJob );
		$batchSizes = $config->get( 'ProduntoUpdateBatchSizes' );
		$this->linkTargetBatchSize = $batchSizes['link'];
		$this->jobBatchSize = $batchSizes['job'];
		$this->purgeBatchSize = $batchSizes['purge'];
		$this->isCdnEnabled = $config->get( MainConfigNames::UseCdn );
	}

	/** @inheritDoc */
	public function run() {
		if ( $this->params['oldId'] ) {
			$oldDeployment = $this->store->getDeploymentById( $this->params['oldId'] );
		} else {
			$oldDeployment = null;
		}
		$newDeployment = $this->store->getDeploymentById( $this->params['newId'] );
		if ( !$newDeployment ) {
			$newDeployment = $this->store->getDeploymentById(
				$this->params['newId'], \IDBAccessObject::READ_LATEST );
			if ( !$newDeployment ) {
				throw new \RuntimeException( "New deployment not found" );
			}
		}

		$this->doModuleChanges( $oldDeployment, $newDeployment );
		$this->doFileChanges( $oldDeployment, $newDeployment );
		$this->flushAll();
		return true;
	}

	/**
	 * If the map of Lua module names to path names has changed for any module
	 * which was previously deployed, purge references to the old paths.
	 *
	 * @param DeploymentAccess|null $oldDeployment
	 * @param DeploymentAccess $newDeployment
	 */
	private function doModuleChanges( $oldDeployment, $newDeployment ) {
		$oldModulePaths = $oldDeployment?->getModulePaths() ?? [];
		$newModulePaths = $newDeployment->getModulePaths();
		$moduleChanges = array_diff_assoc( $oldModulePaths, $newModulePaths );
		foreach ( $moduleChanges as $path ) {
			[ $package, $path ] = explode( '/', $path, 2 );
			$this->queueLinkTarget( $this->repoLinker->getFileLinkTarget( $package, $path ) );
		}
	}

	/**
	 * If any file which was previously deployed has changed its content hash
	 * or has been deleted, purge pages which used that file.
	 *
	 * We don't track links to nonexistent package files, so there's no need to
	 * purge references to newly created files.
	 *
	 * @param DeploymentAccess|null $oldDeployment
	 * @param DeploymentAccess $newDeployment
	 */
	private function doFileChanges( $oldDeployment, $newDeployment ) {
		$oldPackages = $oldDeployment?->getPackages() ?? [];
		foreach ( $oldPackages as $packageId => $oldPackage ) {
			$packageName = $oldPackage->getName();
			$newPackage = $newDeployment->getPackageByName( $packageName );
			if ( $newPackage?->getId() === $packageId ) {
				// Old package version is still deployed, no need to iterate through files
				continue;
			}

			$oldHashes = $oldPackage->getFileHashes();
			$newHashes = $newPackage?->getFileHashes() ?? [];

			// If the README file has changed, purge the index page, since it
			// includes the README file contents. There's no need to purge
			// backlinks since no template links are registered to the index page.
			// TODO: purge all repo viewer pages if the file list changes
			if ( $this->getReadmeHash( $oldPackage, $oldHashes )
				!== $this->getReadmeHash( $newPackage, $newHashes )
			) {
				$this->queuePurge(
					$this->repoLinker->getPackageLinkTarget( $oldPackage->getName() ) );
			}

			$changedHashes = array_diff_assoc( $oldHashes, $newHashes );
			foreach ( $changedHashes as $path => $oldHash ) {
				$this->queueLinkTarget(
					$this->repoLinker->getFileLinkTarget( $packageName, $path ) );
			}
		}
	}

	/**
	 * Get the content hash of the package's readme file, if any
	 *
	 * @param PackageAccess|null $package
	 * @param array<string,string> $hashes The content hash of all files in the
	 *   package, indexed by path. We need this because PackageAccess doesn't
	 *   have a process cache of it.
	 * @return string|null
	 */
	private function getReadmeHash( ?PackageAccess $package, array $hashes ) {
		if ( !$package ) {
			return null;
		}
		$path = $package->getReadmePath();
		if ( $path === null ) {
			return null;
		}
		return $hashes[$path] ?? null;
	}

	/**
	 * Load template backlinks for the given link targets and queue jobs to
	 * purge those backlinks.
	 *
	 * @param LinkTarget[] $linkTargets
	 */
	private function queueJobsForLinks( $linkTargets ) {
		foreach ( $linkTargets as $linkTarget ) {
			$this->queuePurge( $linkTarget );
		}

		$placeholderTitle = Title::makeTitle( NS_SPECIAL, 'Produnto' );
		foreach ( $this->getBacklinkChunks( $linkTargets ) as $pages ) {
			$this->queueJob( new JobSpecification(
				'htmlCacheUpdate',
				$this->makeJobParams( [ 'pages' => $pages ] ),
				[],
				$placeholderTitle,
			) );
			$this->queueJob( new JobSpecification(
				'refreshLinks',
				$this->makeJobParams( [ 'pages' => $pages ] ),
				[],
				$placeholderTitle,
			) );
		}
	}

	/**
	 * Append root job parameters
	 *
	 * @param array $params The new job parameters
	 * @return array The new job parameters with cause and root parameters
	 */
	private function makeJobParams( $params ) {
		return $params + $this->getRootJobParams() + [
			'causeAction' => 'produnto',
			'causeAgent' => $this->params['causeAgent'] ?? 'unknown'
		];
	}

	/**
	 * Generate backlinks as namespace/title pairs indexed by page_id, in chunks
	 * of an appropriate size.
	 *
	 * @param LinkTarget[] $linkTargets
	 * @return Generator<array<int,array{int,string}>>
	 */
	private function getBacklinkChunks( $linkTargets ) {
		$db = $this->dbProvider->getReplicaDatabase();

		$data = [];
		foreach ( $linkTargets as $linkTarget ) {
			$data[$linkTarget->getNamespace()][$linkTarget->getDBkey()] = true;
		}
		$linkTargetConds = $db->makeWhereFrom2d( $data, 'lt_namespace', 'lt_title' );

		$chunkConds = [];
		while ( true ) {
			$res = $db->newSelectQueryBuilder()
				->select( [ 'page_id', 'page_namespace', 'page_title' ] )
				->from( 'page' )
				->join( 'templatelinks', null, 'tl_from=page_id' )
				->join( 'linktarget', null, 'lt_id=tl_target_id' )
				->where( $linkTargetConds )
				->andWhere( $chunkConds )
				->orderBy( 'tl_from' )
				->limit( $this->updateRowsPerJob )
				->caller( __METHOD__ )
				->fetchResultSet();
			$chunk = [];
			$endId = null;
			foreach ( $res as $row ) {
				$chunk[$row->page_id] = [ (int)$row->page_namespace, $row->page_title ];
				$endId = $row->page_id;
			}
			if ( count( $chunk ) ) {
				yield $chunk;
			}
			if ( count( $chunk ) < $this->updateRowsPerJob || $endId === null ) {
				break;
			}
			$chunkConds = $db->expr( 'tl_from', '>', $endId );
		}
	}

	private function queueJob( JobSpecification $job ) {
		$this->jobs[] = $job;
		if ( count( $this->jobs ) >= $this->jobBatchSize ) {
			$this->jobQueueGroup->push( $this->jobs );
			$this->jobs = [];
		}
	}

	private function flushJobs() {
		if ( count( $this->jobs ) ) {
			$this->jobQueueGroup->push( $this->jobs );
			$this->jobs = [];
		}
	}

	private function queuePurge( ?LinkTarget $linkTarget ) {
		if ( !$linkTarget ) {
			return;
		}

		$title = Title::newFromLinkTarget( $linkTarget );
		$url = $title->getInternalURL();

		if ( $this->isCdnEnabled ) {
			$this->urls[] = $url;
			if ( count( $this->urls ) >= $this->purgeBatchSize ) {
				$this->flushUrls();
			}
		}
	}

	private function flushUrls() {
		if ( count( $this->urls ) ) {
			$this->queueJob( new JobSpecification(
				'cdnPurge',
				$this->makeJobParams( [ 'urls' => $this->urls ] )
			) );
			$this->urls = [];
		}
	}

	private function queueLinkTarget( ?LinkTarget $linkTarget ) {
		if ( !$linkTarget ) {
			return;
		}
		if ( isset( $this->seenLinks[$linkTarget->getNamespace()][$linkTarget->getDBkey()] ) ) {
			return;
		}
		$this->seenLinks[$linkTarget->getNamespace()][$linkTarget->getDBkey()] = true;
		$this->linkTargets[] = $linkTarget;
		if ( count( $this->linkTargets ) >= $this->linkTargetBatchSize ) {
			$this->flushLinkTargets();
		}
	}

	private function flushLinkTargets() {
		if ( count( $this->linkTargets ) ) {
			$this->queueJobsForLinks( $this->linkTargets );
			$this->linkTargets = [];
		}
	}

	private function flushAll() {
		$this->flushLinkTargets();
		$this->flushUrls();
		$this->flushJobs();
	}

}

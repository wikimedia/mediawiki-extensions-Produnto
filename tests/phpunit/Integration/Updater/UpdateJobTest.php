<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Extension\Produnto\Updater\UpdateJob;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Updater\UpdateJob
 */
class UpdateJobTest extends \MediaWikiIntegrationTestCase {
	private int $nextDeploymentId = 1;
	private int $nextPackageId = 1;
	private array $jobs = [];

	public function setUp(): void {
		$this->overrideConfigValues( [
			MainConfigNames::Server => 'http://localhost',
			MainConfigNames::UseCdn => true,
		] );
	}

	public function addDBDataOnce() {
		$this->addPageWithLink(
			'Package1/path1',
			'Package:Package1/path1'
		);
		$this->addPageWithLink(
			'A',
			'Package:Multi-batch/path'
		);
		$this->addPageWithLink(
			'B',
			'Package:Multi-batch/path'
		);
		$this->addPageWithLink(
			'C',
			'Package:Multi-batch/path'
		);
	}

	private function addPageWithLink( $titleText, $linkText ) {
		$title = Title::newFromText( $titleText );
		$status = $this->editPage( $title, '...' );

		$services = $this->getServiceContainer();
		$linkTargetLookup = $services->getLinkTargetLookup();
		$link = Title::newFromText( $linkText );
		$ltId = $linkTargetLookup->acquireLinkTargetId( $link, $this->getDb() );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'templatelinks' )
			->row( [
				'tl_from' => $status->getNewRevision()->getPageId(),
				'tl_from_namespace' => $title->getNamespace(),
				'tl_target_id' => $ltId,
			] )
			->caller( __METHOD__ )
			->execute();
	}

	public static function provideDoModuleChanges() {
		$page11 = [ 1 => [ NS_MAIN, 'Package1/path1' ] ];
		$urls11 = [ 'http://localhost/wiki/Package:Package1/path1' ];
		$jobs11 = [
			[ 'type' => 'htmlCacheUpdate', 'pages' => $page11 ],
			[ 'type' => 'refreshLinks', 'pages' => $page11 ],
			[ 'type' => 'cdnPurge', 'urls' => $urls11 ]
		];
		$package111 = [ 'package1' => [ 'module1' => 'path1' ] ];
		$package1__ = [ 'package1' => [] ];
		$package221 = [ 'package2' => [ 'module2' => 'path1' ] ];
		$package211 = [ 'package2' => [ 'module1' => 'path1' ] ];
		return [
			'empty' => [ [], [], [] ],
			'creation' => [
				$package1__,
				$package111,
				[],
			],
			'creation with existing' => [
				$package111,
				$package111 + $package221,
				[],
			],
			'deletion' => [
				$package111,
				$package1__,
				$jobs11,
			],
			'move' => [
				$package111,
				$package211,
				$jobs11,
			],
		];
	}

	/**
	 * @dataProvider provideDoModuleChanges
	 * @param array $oldModules
	 * @param array $newModules
	 * @param array $expectedJobs
	 */
	public function testDoModuleChanges( $oldModules, $newModules, $expectedJobs ) {
		$oldDeployment = $this->createDeployment( [], $oldModules );
		$newDeployment = $this->createDeployment( [], $newModules );
		$job = $this->createJob( $oldDeployment, $newDeployment );
		$job->run();
		$resultJobs = $this->getJobs();
		$this->assertSame( $expectedJobs, $resultJobs );
	}

	public static function provideDoFileChanges() {
		$page11 = [ 1 => [ NS_MAIN, 'Package1/path1' ] ];
		$urls11 = [ 'http://localhost/wiki/Package:Package1/path1' ];
		$jobs11 = [
			[ 'type' => 'htmlCacheUpdate', 'pages' => $page11 ],
			[ 'type' => 'refreshLinks', 'pages' => $page11 ],
			[ 'type' => 'cdnPurge', 'urls' => $urls11 ]
		];
		$package1__ = [ 'package1' => [] ];
		$package11a = [ 'package1' => [ 'path1' => 'a' ] ];
		$package11b = [ 'package1' => [ 'path1' => 'b' ] ];

		yield from [
			'empty' => [ [], [], [] ],
			'package creation' => [ [], $package11a, [] ],
			'creation with no previous deployment' => [ null, $package11a, [] ],
			'file creation' => [ $package1__, $package11a, [] ],
			'package deletion' => [
				$package11a,
				[],
				$jobs11
			],
			'file deletion' => [
				$package11a,
				$package1__,
				$jobs11
			],
			'file update' => [
				$package11a,
				$package11b,
				$jobs11
			],
		];

		$package1ra = [ 'package1' => [ 'README.md' => 'a' ] ];
		$package1rb = [ 'package1' => [ 'README.md' => 'b' ] ];
		$urls1r = [
			'http://localhost/wiki/Package:Package1',
			'http://localhost/wiki/Package:Package1/README.md'
		];
		$jobs1r = [
			[ 'type' => 'cdnPurge', 'urls' => $urls1r ]
		];

		yield 'readme update' => [
			$package1ra,
			$package1rb,
			$jobs1r
		];

		$package_ma = [ 'multi-batch' => [ 'path' => 'a' ] ];
		$package_mb = [ 'multi-batch' => [ 'path' => 'b' ] ];
		$pages_m1 = [
			2 => [ NS_MAIN, 'A' ],
			3 => [ NS_MAIN, 'B' ],
		];
		$pages_m2 = [
			4 => [ NS_MAIN, 'C' ],
		];
		$urls_m = [ 'http://localhost/wiki/Package:Multi-batch/path' ];
		$jobs_m = [
			[ 'type' => 'htmlCacheUpdate', 'pages' => $pages_m1 ],
			[ 'type' => 'refreshLinks', 'pages' => $pages_m1 ],
			[ 'type' => 'htmlCacheUpdate', 'pages' => $pages_m2 ],
			[ 'type' => 'refreshLinks', 'pages' => $pages_m2 ],
			[ 'type' => 'cdnPurge', 'urls' => $urls_m ],
		];
		yield 'multi-batch link update' => [
			$package_ma,
			$package_mb,
			$jobs_m
		];
	}

	/**
	 * @dataProvider provideDoFileChanges
	 * @param array|null $oldFiles
	 * @param array $newFiles
	 * @param array $expectedJobs
	 */
	public function testDoFileChanges( ?array $oldFiles, array $newFiles, array $expectedJobs ) {
		if ( $oldFiles === null ) {
			$oldDeployment = null;
		} else {
			$oldDeployment = $this->createDeployment( $oldFiles, [] );
		}
		$newDeployment = $this->createDeployment( $newFiles, [] );
		$job = $this->createJob( $oldDeployment, $newDeployment );
		$job->run();
		$resultJobs = $this->getJobs();
		$this->assertSame( $expectedJobs, $resultJobs );
	}

	/**
	 * Covers the duplicate removal "seenLinks" case
	 */
	public function testFileAndModuleBothChange() {
		$oldDeployment = $this->createDeployment(
			[ 'package1' => [ 'path1' => 'a' ] ],
			[ 'package1' => [ 'module1' => 'path1' ] ]
		);
		$newDeployment = $this->createDeployment(
			[ 'package1' => [ 'path1' => 'b', 'path2' => 'c' ] ],
			[ 'package1' => [ 'module1' => 'path2' ] ]
		);

		$page11 = [ 1 => [ NS_MAIN, 'Package1/path1' ] ];
		$urls11 = [ 'http://localhost/wiki/Package:Package1/path1' ];
		$expectedJobs = [
			[ 'type' => 'htmlCacheUpdate', 'pages' => $page11 ],
			[ 'type' => 'refreshLinks', 'pages' => $page11 ],
			[ 'type' => 'cdnPurge', 'urls' => $urls11 ]
		];

		$job = $this->createJob( $oldDeployment, $newDeployment );
		$job->run();

		$this->assertSame( $expectedJobs, $this->getJobs() );
	}

	/**
	 * Covers the batch size overflow in queueLinkTarget()
	 */
	public function testLinkTargetBatch() {
		$oldDeployment = $this->createDeployment(
			[ 'package1' => [ 'a' => '1', 'b' => '1', 'c' => '1', 'd' => '1' ] ],
			[]
		);
		$newDeployment = $this->createDeployment(
			[ 'package1' => [ 'a' => '2', 'b' => '2', 'c' => '2', 'd' => '2' ] ],
			[]
		);

		$expectedJobs = [
			[
				'type' => 'cdnPurge',
				'urls' => [
					'http://localhost/wiki/Package:Package1/a',
					'http://localhost/wiki/Package:Package1/b',
				]
			],
			[
				'type' => 'cdnPurge',
				'urls' => [
					'http://localhost/wiki/Package:Package1/c',
					'http://localhost/wiki/Package:Package1/d',
				]
			],
		];

		$job = $this->createJob( $oldDeployment, $newDeployment );
		$job->run();

		$this->assertSame( $expectedJobs, $this->getJobs() );
	}

	/**
	 * Covers the same package ID shortcut in doFileChanges()
	 */
	public function testSamePackageId() {
		$oldDeployment = $this->createDeployment(
			[ 'package1' => [ 'path1' => 'a' ] ],
			[]
		);
		$newDeployment = new DeploymentAccess(
			TestingAccessWrapper::newFromObject( $oldDeployment )->fileAccess,
			$this->getDb(),
			$this->nextDeploymentId++,
			'', 1,
			[],
			$oldDeployment->getPackages()
		);
		$job = $this->createJob( $oldDeployment, $newDeployment );
		$job->run();
		$this->assertSame( [], $this->getJobs() );
	}

	/**
	 * Covers the deployment not found case, and also the job wiring
	 */
	public function testInvalidDeployment() {
		$services = $this->getServiceContainer();
		$jobFactory = $services->getJobFactory();
		$job = $jobFactory->newJob( 'ProduntoUpdate', [ 'oldId' => 1, 'newId' => 2 ] );
		$this->expectExceptionMessageMatches( '/deployment not found/' );
		$job->run();
	}

	private function createDeployment( $files, $modules ) {
		$moduleData = [];
		$packages = [];
		$filesById = [];
		$packageNames = array_merge( array_keys( $files ), array_keys( $modules ) );
		$packageIdsByName = [];

		foreach ( $packageNames as $packageName ) {
			$packageId = $this->nextPackageId++;
			$packageIdsByName[$packageName] = $packageId;
			$filesById[$packageId] = $files[$packageName] ?? [];
		}

		foreach ( $packageNames as $packageName ) {
			$packageId = $packageIdsByName[$packageName];
			$packageModules = $modules[$packageName] ?? [];
			$packages[$packageId] = new PackageAccess(
				new SimpleFileAccess( $filesById ),
				$packageId,
				$packageName,
				'',
				'',
				'',
				[],
				ProduntoStore::STATE_READY,
				null
			);

			foreach ( $packageModules as $moduleName => $path ) {
				$moduleData[$moduleName] = [ $packageId, $path ];
			}
		}

		return new DeploymentAccess(
			new SimpleFileAccess( $filesById ),
			$this->getDb(),
			$this->nextDeploymentId++,
			'', 1,
			[ 'modules' => $moduleData ],
			$packages
		);
	}

	private function createJob( ?DeploymentAccess $oldDeployment, DeploymentAccess $newDeployment ) {
		$store = $this->createMock( ProduntoStore::class );
		$store->method( 'getDeploymentById' )->willReturnCallback(
			static function ( $id ) use ( $oldDeployment, $newDeployment ) {
				if ( $id === $oldDeployment?->getId() ) {
					return $oldDeployment;
				}
				if ( $id === $newDeployment->getId() ) {
					return $newDeployment;
				}
				return null;
			}
		);

		$jobFactory = $this->getServiceContainer()->getJobFactory();

		$jqg = $this->createMock( JobQueueGroup::class );
		$jqg->method( 'push' )->willReturnCallback(
			function ( $jobs ) use ( $jobFactory ) {
				foreach ( $jobs as $jobSpec ) {
					/** @var JobSpecification $jobSpec */
					// Confirm that the job is constructible
					$jobFactory->newJob( $jobSpec->getType(), $jobSpec->getParams() );
					// Save essential parameters for a later assertion
					$params = array_diff_key(
						$jobSpec->getParams(),
						array_fill_keys( [
							'rootJobSignature', 'rootJobTimestamp',
							'causeAction', 'causeAgent',
							'requestId', 'namespace', 'title'
						], true )
					);
					$this->jobs[] = [ 'type' => $jobSpec->getType() ] + $params;
				}
			}
		);

		$config = new HashConfig( [
			MainConfigNames::UpdateRowsPerJob => 2,
			MainConfigNames::UseCdn => true,
			'ProduntoUpdateBatchSizes' => [
				'link' => 3,
				'job' => 2,
				'purge' => 2
			]
		] );

		$services = $this->getServiceContainer();
		$produntoServices = new ProduntoServices( $services );
		return new UpdateJob(
			[
				'oldId' => $oldDeployment?->getId() ?? 0,
				'newId' => $newDeployment->getId(),
			],
			$store,
			$produntoServices->getRepoLinker(),
			$services->getConnectionProvider(),
			$jqg,
			$config
		);
	}

	private function getJobs() {
		return $this->jobs;
	}
}

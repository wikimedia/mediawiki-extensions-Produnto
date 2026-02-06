<?php

namespace MediaWiki\Extension\Produnto\Store;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Service providing access to the database
 */
class ProduntoStore {
	public const STATE_FETCHING = 1;
	public const STATE_READY = 2;
	public const STATE_FAILED = 3;

	private const TEXT_CACHE_SIZE = 1000;

	private IConnectionProvider $dbProvider;
	private TextStore $textStore;
	private MapCacheLRU $textCache;
	private NameStore $nameStore;

	public function __construct(
		IConnectionProvider $dbProvider
	) {
		$this->dbProvider = $dbProvider;
		$this->textStore = new TextStore( $dbProvider );
		$this->textCache = new MapCacheLRU( self::TEXT_CACHE_SIZE );
		$this->nameStore = new NameStore( $dbProvider );
	}

	/**
	 * Create an object used for creating a deployment
	 *
	 * @return DeploymentBuilder
	 */
	public function createDeployment(): DeploymentBuilder {
		return new DeploymentBuilder(
			$this->textCache,
			$this->dbProvider->getPrimaryDatabase( 'virtual-produnto' )
		);
	}

	/**
	 * Make a previously stored deployment be the active deployment for the
	 * current wiki.
	 *
	 * @param DeploymentAccess $deployment
	 */
	public function activateDeployment( DeploymentAccess $deployment ) {
		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-produnto' );
		$wiki = WikiMap::getCurrentWikiId();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_active_deployment' )
			->row( [
				'pad_wiki' => $wiki,
				'pad_deployment' => $deployment->getId()
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( 'pad_wiki' )
			->set( [ 'pad_deployment' => $deployment->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Get the currently active deployment, if there is one
	 *
	 * @return DeploymentAccess|null
	 */
	public function getActiveDeployment(): ?DeploymentAccess {
		$db = $this->getDbFromRecency( IDBAccessObject::READ_NORMAL );
		$row = $db->newSelectQueryBuilder()
			->select( 'pd_id' )
			->from( 'produnto_active_deployment' )
			->join( 'produnto_deployment', null, 'pad_deployment=pd_id' )
			->where( [ 'pad_wiki' => WikiMap::getCurrentWikiId() ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$row ) {
			return null;
		}
		return new DeploymentAccess(
			$this->textCache,
			$db,
			(int)$row->pd_id
		);
	}

	/**
	 * Create a new package version
	 *
	 * @return PackageBuilder
	 */
	public function createPackageVersion(): PackageBuilder {
		return new PackageBuilder(
			$this->textStore,
			$this->textCache,
			$this->nameStore,
			$this->dbProvider->getPrimaryDatabase( 'virtual-produnto' )
		);
	}

	/**
	 * Resume building of a package in the fetching state.
	 *
	 * @param PackageAccess $package
	 * @return PackageBuilder
	 */
	public function resumePackageBuilder( PackageAccess $package ): PackageBuilder {
		return PackageBuilder::resume(
			$this->textStore,
			$this->textCache,
			$this->nameStore,
			$this->dbProvider->getPrimaryDatabase( 'virtual-produnto' ),
			$package
		);
	}

	/**
	 * Get the package version with the specified ID, or null if there is no such package.
	 *
	 * @param int $id
	 * @param int $recency One of the READ_xxx constants
	 * @return PackageAccess|null
	 */
	public function getPackageById( $id, $recency = IDBAccessObject::READ_NORMAL ): ?PackageAccess {
		$db = $this->getDbFromRecency( $recency );
		$row = $db->newSelectQueryBuilder()
			->select( [ 'pp_name', 'ppv_version', 'pp_url', 'ppv_state', 'ppv_error' ] )
			->from( 'produnto_package_version' )
			->join( 'produnto_package', null, 'pp_id=ppv_package' )
			->where( [ 'ppv_id' => $id ] )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			return new PackageAccess(
				$this->textCache,
				$db,
				$id,
				$row->pp_name,
				$row->ppv_version,
				$row->pp_url,
				$row->ppv_state,
				$row->ppv_error,
			);
		} else {
			return null;
		}
	}

	/**
	 * Get a package by name and specific version
	 *
	 * @param string $name
	 * @param string $version
	 * @param int $recency
	 * @return PackageAccess|null
	 */
	public function getPackageByName(
		string $name, string $version, int $recency = IDBAccessObject::READ_NORMAL
	): ?PackageAccess {
		$db = $this->getDbFromRecency( $recency );
		$row = $db->newSelectQueryBuilder()
			->select( [ 'ppv_id', 'pp_url', 'ppv_state', 'ppv_error' ] )
			->from( 'produnto_package_version' )
			->join( 'produnto_package', null, 'pp_id=ppv_package' )
			->where( [
				'pp_name' => $name,
				'ppv_version' => $version
			] )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			return new PackageAccess(
				$this->textCache,
				$db,
				$row->ppv_id,
				$name,
				$version,
				$row->pp_url,
				$row->ppv_state,
				$row->ppv_error
			);
		} else {
			return null;
		}
	}

	/**
	 * Get a connection using IDBAccessObject recency flags
	 * @param int $recency
	 * @return IReadableDatabase
	 */
	private function getDbFromRecency( $recency ) {
		if ( $recency & IDBAccessObject::READ_LATEST ) {
			return $this->dbProvider->getPrimaryDatabase( 'virtual-produnto' );
		} else {
			return $this->dbProvider->getReplicaDatabase( 'virtual-produnto' );
		}
	}
}

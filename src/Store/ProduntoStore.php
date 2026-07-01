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
	public const int STATE_FETCHING = 1;
	public const int STATE_READY = 2;
	public const int STATE_FAILED = 3;

	private const int TEXT_CACHE_SIZE = 1000;

	private TextStore $textStore;
	private MapCacheLRU $textCache;
	private NameStore $nameStore;

	public function __construct(
		private readonly IConnectionProvider $dbProvider,
	) {
		$this->textStore = new TextStore( $dbProvider );
		$this->textCache = new MapCacheLRU( self::TEXT_CACHE_SIZE );
		$this->nameStore = new NameStore( $dbProvider );
	}

	/**
	 * Create an object used for creating a deployment
	 */
	public function createDeployment(): DeploymentBuilder {
		return new DeploymentBuilder(
			$this->getFileAccess( IDBAccessObject::READ_LATEST ),
			$this->dbProvider->getPrimaryDatabase( 'virtual-produnto' )
		);
	}

	/**
	 * Make a previously stored deployment be the active deployment for the
	 * current wiki.
	 */
	public function activateDeployment( DeploymentAccess $deployment ): void {
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
			$this->getFileAccess( IDBAccessObject::READ_NORMAL ),
			$db,
			(int)$row->pd_id
		);
	}

	/**
	 * Get the deployment with the given ID, or null if no such deployment exists.
	 */
	public function getDeploymentById(
		int $id, int $recency = IDBAccessObject::READ_NORMAL
	): ?DeploymentAccess {
		$db = $this->getDbFromRecency( $recency );
		$exists = $db->newSelectQueryBuilder()
			->select( '1' )
			->from( 'produnto_deployment' )
			->where( [ 'pd_id' => $id ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$exists ) {
			return null;
		}
		return new DeploymentAccess(
			$this->getFileAccess( $recency ),
			$db,
			$id
		);
	}

	/**
	 * Create a new package version
	 */
	public function createPackageVersion(): PackageBuilder {
		return new PackageBuilder(
			$this->textStore,
			$this->getFileAccess( IDBAccessObject::READ_LATEST ),
			$this->nameStore,
			$this->dbProvider->getPrimaryDatabase( 'virtual-produnto' )
		);
	}

	/**
	 * Resume building of a package in the fetching state.
	 */
	public function resumePackageBuilder( PackageAccess $package ): PackageBuilder {
		return PackageBuilder::resume(
			$this->textStore,
			$this->getFileAccess( IDBAccessObject::READ_LATEST ),
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
	 */
	public function getPackageById( $id, $recency = IDBAccessObject::READ_NORMAL ): ?PackageAccess {
		$db = $this->getDbFromRecency( $recency );
		$row = $db->newSelectQueryBuilder()
			->select( [
				'pp_name', 'ppv_version', 'ppv_upstream_ref', 'pp_url', 'ppv_state',
				'ppv_error', 'ppv_props'
			] )
			->from( 'produnto_package_version' )
			->join( 'produnto_package', null, 'pp_id=ppv_package' )
			->where( [ 'ppv_id' => $id ] )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			return new PackageAccess(
				$this->getFileAccess( $recency ),
				$id,
				$row->pp_name,
				$row->ppv_version,
				$row->ppv_upstream_ref,
				$row->pp_url,
				self::decodeJson( $row->ppv_props ),
				$row->ppv_state,
				$row->ppv_error,
			);
		} else {
			return null;
		}
	}

	/**
	 * Get a package by name and specific version
	 */
	public function getPackageByName(
		string $name, string $version, int $recency = IDBAccessObject::READ_NORMAL
	): ?PackageAccess {
		$db = $this->getDbFromRecency( $recency );
		$row = $db->newSelectQueryBuilder()
			->select( [
				'ppv_id', 'pp_url', 'ppv_upstream_ref', 'ppv_state', 'ppv_error', 'ppv_props'
			] )
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
				$this->getFileAccess( $recency ),
				(int)$row->ppv_id,
				$name,
				$version,
				$row->ppv_upstream_ref,
				$row->pp_url,
				self::decodeJson( $row->ppv_props ),
				$row->ppv_state,
				$row->ppv_error
			);
		} else {
			return null;
		}
	}

	/**
	 * Get a connection using IDBAccessObject recency flags
	 */
	private function getDbFromRecency( int $recency ): IReadableDatabase {
		if ( $recency & IDBAccessObject::READ_LATEST ) {
			return $this->dbProvider->getPrimaryDatabase( 'virtual-produnto' );
		} else {
			return $this->dbProvider->getReplicaDatabase( 'virtual-produnto' );
		}
	}

	public function getFileAccess( int $recency ): SqlFileAccess {
		return new SqlFileAccess(
			$this->textCache,
			$this->getDbFromRecency( $recency )
		);
	}

	/**
	 * Determine whether a set of SHA-256 content hashes exist in the store
	 *
	 * @param string[] $hashes
	 * @return array<string,bool>
	 */
	public function hasFileHashBatch( array $hashes ): array {
		$db = $this->getDbFromRecency( IDBAccessObject::READ_NORMAL );
		$results = array_fill_keys( $hashes, false );
		foreach ( array_chunk( $hashes, 1000 ) as $batchHashes ) {
			$foundHashes = $db->newSelectQueryBuilder()
				->select( 'pft_hash' )
				->from( 'produnto_file_text' )
				->where( [
					'pft_hash' => $batchHashes
				] )
				->caller( __METHOD__ )
				->fetchFieldValues();
			foreach ( $foundHashes as $hash ) {
				$results[$hash] = true;
			}
		}
		return $results;
	}

	/**
	 * Decode a JSON array stored internally
	 *
	 * @internal
	 */
	public static function decodeJson( string $json ): array {
		if ( $json === '' ) {
			$json = '{}';
		}
		return json_decode( $json, true, flags: JSON_THROW_ON_ERROR );
	}

	/**
	 * Encode an array as JSON for internal storage
	 *
	 * @internal
	 */
	public static function encodeJson( array $data ): string {
		if ( $data === [] ) {
			return '';
		}
		return json_encode( $data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
		);
	}

}

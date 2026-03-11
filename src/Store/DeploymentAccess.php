<?php

namespace MediaWiki\Extension\Produnto\Store;

use MediaWiki\Extension\Produnto\Runtime\ModuleInfo;
use Wikimedia\Rdbms\IReadableDatabase;
use function array_key_exists;

class DeploymentAccess {
	/** @var array<string,int>|null */
	private ?array $idsByName = null;

	/**
	 * @param FileAccess $fileAccess
	 * @param IReadableDatabase $db
	 * @param int $id
	 * @param array|null $dataItems The data items, or null to load from the DB
	 * @param PackageAccess[]|null $packages The packages, or null to load from the DB
	 */
	public function __construct(
		private FileAccess $fileAccess,
		private IReadableDatabase $db,
		private int $id,
		private ?array $dataItems = null,
		private ?array $packages = null
	) {
	}

	/**
	 * Get the pd_id value
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get arbitrary data associated with the deployment.
	 *
	 * @param string $name
	 * @return array|null
	 */
	public function getData( string $name ) {
		if ( $this->dataItems === null ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( [ 'pdd_name', 'pdd_text' ] )
				->from( 'produnto_deployment_data' )
				->where( [ 'pdd_deployment' => $this->id ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$this->dataItems = [];
			foreach ( $res as $row ) {
				$this->dataItems[$row->pdd_name] = ProduntoStore::decodeJson( $row->pdd_text );
			}
		}
		return $this->dataItems[$name] ?? null;
	}

	/**
	 * Get data associated with a Lua module
	 *
	 * @param string $moduleName
	 * @return ModuleInfo|null
	 */
	public function getModuleInfo( $moduleName ) {
		$modules = $this->getData( 'modules' );
		if ( !array_key_exists( $moduleName, $modules ) ) {
			return null;
		}
		[ $packageId, $path ] = $modules[$moduleName];
		$package = $this->getPackageById( $packageId );
		if ( !$package ) {
			return null;
		}
		$contents = $this->fileAccess->getFileContents( $packageId, $path );
		if ( !$contents ) {
			return null;
		}
		return new ModuleInfo( $package->getName(), $path, $contents );
	}

	/**
	 * Get all packages included in the deployment.
	 *
	 * @return array<int,PackageAccess> Packages by ID
	 */
	public function getPackages(): array {
		if ( $this->packages === null ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( [
					'ppv_id', 'pp_name', 'ppv_version', 'ppv_upstream_ref',
					'pp_url', 'ppv_state', 'ppv_error', 'ppv_props' ] )
				->from( 'produnto_package_version' )
				->join( 'produnto_package', null, 'pp_id=ppv_package' )
				->join( 'produnto_package_deployment', null, 'ppd_package_version=ppv_id' )
				->where( [ 'ppd_deployment' => $this->id ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$this->packages = [];
			foreach ( $res as $row ) {
				$this->packages[$row->ppv_id] = new PackageAccess(
					$this->fileAccess,
					$row->ppv_id,
					$row->pp_name,
					$row->ppv_version,
					$row->ppv_upstream_ref,
					$row->pp_url,
					ProduntoStore::decodeJson( $row->ppv_props ),
					$row->ppv_state,
					$row->ppv_error
				);
			}
		}
		return $this->packages;
	}

	private function getPackageById( int $id ): ?PackageAccess {
		return $this->getPackages()[$id] ?? null;
	}

	/**
	 * Get a package by name
	 *
	 * @param string $name
	 * @return PackageAccess|null
	 */
	public function getPackageByName( string $name ): ?PackageAccess {
		$packages = $this->getPackages();
		if ( $this->idsByName === null ) {
			$this->idsByName = [];
			foreach ( $packages as $id => $package ) {
				$this->idsByName[$package->getName()] = $id;
			}
		}
		if ( array_key_exists( $name, $this->idsByName ) ) {
			return $packages[$this->idsByName[$name]];
		}
		return null;
	}
}

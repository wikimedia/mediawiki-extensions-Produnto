<?php

namespace MediaWiki\Extension\Produnto\Store;

use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;

class DeploymentAccess {
	/**
	 * @param MapCacheLRU $textCache
	 * @param IReadableDatabase $db
	 * @param int $id
	 * @param string[]|null $dataItems The data items, or null to load from the DB
	 * @param PackageAccess[]|null $packages The packages, or null to load from the DB
	 */
	public function __construct(
		private MapCacheLRU $textCache,
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
	 * @return string|null
	 */
	public function getData( string $name ): ?string {
		if ( $this->dataItems === null ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( [ 'pdd_name', 'pdd_text' ] )
				->from( 'produnto_deployment_data' )
				->where( [ 'pdd_deployment' => $this->id ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$this->dataItems = [];
			foreach ( $res as $row ) {
				$this->dataItems[$row->pdd_name] = $row->pdd_text;
			}
		}
		return $this->dataItems[$name] ?? null;
	}

	/**
	 * Get all packages included in the deployment.
	 *
	 * @return PackageAccess[]
	 */
	public function getPackages(): array {
		if ( $this->packages === null ) {
			$res = $this->db->newSelectQueryBuilder()
				->select( [ 'ppv_id', 'pp_name', 'ppv_version', 'pp_url', 'ppv_state', 'ppv_error' ] )
				->from( 'produnto_package_version' )
				->join( 'produnto_package', null, 'pp_id=ppv_package' )
				->join( 'produnto_package_deployment', null, 'ppd_package_version=ppv_id' )
				->where( [ 'ppd_deployment' => $this->id ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$this->packages = [];
			foreach ( $res as $row ) {
				$this->packages[] = new PackageAccess(
					$this->textCache,
					$this->db,
					$row->ppv_id,
					$row->pp_name,
					$row->ppv_version,
					$row->pp_url,
					$row->ppv_state,
					$row->ppv_error
				);
			}
		}
		return $this->packages;
	}
}

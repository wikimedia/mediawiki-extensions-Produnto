<?php

namespace MediaWiki\Extension\Produnto\Store;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IDatabase;

class DeploymentBuilder {
	/** @var int|null The revision ID of the deployment control page */
	private ?int $revId = null;

	/** @var int|null The deployment ID, if it has been inserted */
	private ?int $id = null;

	/** @var array<string,array> Saved data items */
	private array $dataItems = [];

	/** @var array A batch of uninserted produnto_package_deployment rows */
	private array $packageRows = [];

	/** @var PackageAccess[] The packages in the deployment, indexed by package ID */
	private array $packages = [];

	/** @var array<string,array{int,string}> */
	private array $modules = [];

	public function __construct(
		private FileAccess $fileAccess,
		private IDatabase $dbw
	) {
	}

	/**
	 * Set the revision ID
	 *
	 * @param int $revId
	 * @return $this
	 */
	public function revId( int $revId ): self {
		$this->revId = $revId;
		return $this;
	}

	/**
	 * Set the Lua modules
	 *
	 * @param array<string,array{int,string}> $modules
	 * @return $this
	 */
	public function modules( array $modules ): self {
		$this->modules = $modules;
		return $this;
	}

	/**
	 * Add arbitrary data associated with the deployment
	 *
	 * @param string $name
	 * @param array $data
	 * @return $this
	 */
	public function addData( string $name, array $data ): self {
		$id = $this->ensureInserted();
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_deployment_data' )
			->row( [
				'pdd_deployment' => $id,
				'pdd_name' => $name,
				'pdd_text' => ProduntoStore::encodeJson( $data )
			] )
			->caller( __METHOD__ )
			->execute();
		$this->dataItems[$name] = $data;
		return $this;
	}

	/**
	 * Add a package to the deployment
	 *
	 * @param PackageAccess $package
	 * @return $this
	 */
	public function addPackage( PackageAccess $package ): self {
		$id = $this->ensureInserted();
		$this->packageRows[] = [
			'ppd_deployment' => $id,
			'ppd_package_version' => $package->getId()
		];
		$this->packages[$package->getId()] = $package;
		return $this;
	}

	/**
	 * Finish storing the deployment.
	 *
	 * @return DeploymentAccess
	 */
	public function commit(): DeploymentAccess {
		$id = $this->ensureInserted();

		if ( $this->modules ) {
			$this->addData( 'modules', $this->modules );
		}

		if ( $this->packageRows ) {
			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'produnto_package_deployment' )
				->rows( $this->packageRows )
				->caller( __METHOD__ )
				->execute();
			$this->packageRows = [];
		}
		return new DeploymentAccess(
			$this->fileAccess,
			$this->dbw,
			$id,
			$this->dataItems,
			$this->packages
		);
	}

	/**
	 * Insert the produnto_deployment row if it hasn't been inserted already
	 *
	 * @return int The pd_id
	 */
	private function ensureInserted(): int {
		if ( $this->id ) {
			return $this->id;
		}
		if ( $this->revId === null ) {
			throw new \LogicException( 'The revision ID must be set' );
		}
		$wiki = WikiMap::getCurrentWikiId();
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_deployment' )
			->row( [
				'pd_target_wiki' => $wiki,
				'pd_control_wiki' => $wiki,
				'pd_control_rev_id' => $this->revId,
			] )
			->caller( __METHOD__ )
			->execute();
		$id = $this->dbw->insertId();
		if ( !$id ) {
			throw new \RuntimeException( 'No insert ID when inserting into produnto_deployment' );
		}
		$this->id = $id;
		return $id;
	}
}

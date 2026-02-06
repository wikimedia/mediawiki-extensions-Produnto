<?php

namespace MediaWiki\Extension\Produnto\Store;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IDatabase;

class DeploymentBuilder {
	/** @var int|null The revision ID of the deployment control page */
	private ?int $revId = null;

	/** @var int|null The deployment ID, if it has been inserted */
	private ?int $id = null;

	/** @var array<string,string> Saved data items */
	private array $dataItems = [];

	/** @var array A batch of uninserted produnto_package_deployment rows */
	private array $packageRows = [];

	/** @var PackageAccess[] The packages in the deployment */
	private array $packages = [];

	public function __construct(
		private MapCacheLRU $textCache,
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
	 * Add arbitrary data associated with the deployment
	 *
	 * @param string $name
	 * @param string $contents
	 * @return $this
	 */
	public function addData( string $name, string $contents ): self {
		$id = $this->ensureInserted();
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_deployment_data' )
			->row( [
				'pdd_deployment' => $id,
				'pdd_name' => $name,
				'pdd_text' => $contents
			] )
			->caller( __METHOD__ )
			->execute();
		$this->dataItems[$name] = $contents;
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
		$this->packages[] = $package;
		return $this;
	}

	/**
	 * Finish storing the deployment.
	 *
	 * @return DeploymentAccess
	 */
	public function commit(): DeploymentAccess {
		$id = $this->ensureInserted();

		if ( $this->packageRows ) {
			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'produnto_package_deployment' )
				->rows( $this->packageRows )
				->caller( __METHOD__ )
				->execute();
			$this->packageRows = [];
		}
		return new DeploymentAccess(
			$this->textCache,
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

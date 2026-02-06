<?php

namespace MediaWiki\Extension\Produnto\Store;

use StatusValue;
use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IDatabase;

/**
 * Class for adding and updating a package version
 */
class PackageBuilder {
	private TextStore $textStore;
	private MapCacheLRU $textCache;
	private NameStore $nameStore;
	private ?string $name = null;
	private ?string $version = null;
	private ?string $url = null;
	private ?int $id = null;
	private int $state = ProduntoStore::STATE_FETCHING;
	private IDatabase $dbw;

	public function __construct(
		TextStore $textStore,
		MapCacheLRU $textCache,
		NameStore $nameStore,
		IDatabase $dbw,
	) {
		$this->textStore = $textStore;
		$this->textCache = $textCache;
		$this->nameStore = $nameStore;
		$this->dbw = $dbw;
	}

	/**
	 * Resume building of a package in the fetching state.
	 *
	 * @param TextStore $textStore
	 * @param MapCacheLRU $textCache
	 * @param NameStore $nameStore
	 * @param IDatabase $dbw
	 * @param PackageAccess $package
	 * @return self
	 */
	public static function resume(
		TextStore $textStore,
		MapCacheLRU $textCache,
		NameStore $nameStore,
		IDatabase $dbw,
		PackageAccess $package
	) {
		$builder = new self( $textStore, $textCache, $nameStore, $dbw );
		$builder->name = $package->getName();
		$builder->url = $package->getUrl();
		$builder->version = $package->getVersion();
		$builder->id = $package->getId();
		$builder->state = $package->getState();
		return $builder;
	}

	/**
	 * Set the name
	 *
	 * @param string $name
	 * @return $this
	 */
	public function name( string $name ): self {
		$this->assertNotInserted( __FUNCTION__ );
		$this->name = $name;
		return $this;
	}

	/**
	 * Set the version
	 *
	 * @param string $version
	 * @return $this
	 */
	public function version( string $version ): self {
		$this->assertNotInserted( __FUNCTION__ );
		$this->version = $version;
		return $this;
	}

	/**
	 * Set the URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function url( string $url ): self {
		$this->assertNotInserted( __FUNCTION__ );
		$this->url = $url;
		return $this;
	}

	/**
	 * Add a file to the package
	 *
	 * @param string $path
	 * @param string $contents
	 * @return $this
	 * @throws PackageBuilderError
	 */
	public function addFile( $path, $contents ): self {
		$id = $this->ensureInserted();
		$hash = $this->textStore->store( $contents );
		$nameId = $this->nameStore->store( $path );
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_file' )
			->row( [
				'pf_package_version' => $id,
				'pf_name_id' => $nameId,
				'pf_hash' => $hash
			] )
			->caller( __METHOD__ )
			->execute();

		$this->textCache->set(
			$this->textCache->makeKey( $id, $path ),
			$contents
		);

		return $this;
	}

	/**
	 * Suspend construction of the package, to be resumed in a job
	 *
	 * @return PackageAccess
	 * @throws PackageBuilderError
	 */
	public function suspend(): PackageAccess {
		$id = $this->ensureInserted();
		return new PackageAccess(
			$this->textCache,
			$this->dbw,
			$id,
			$this->name,
			$this->version,
			$this->url,
			$this->state,
			null
		);
	}

	/**
	 * Write the package version to the database and mark it ready.
	 *
	 * @return PackageAccess
	 * @throws PackageBuilderError
	 */
	public function commit(): PackageAccess {
		$id = $this->ensureInserted();
		$this->updateState( $id, ProduntoStore::STATE_READY );

		return new PackageAccess(
			$this->textCache,
			$this->dbw,
			$id,
			$this->name,
			$this->version,
			$this->url,
			$this->state,
			null
		);
	}

	/**
	 * Mark the fetch operation as failed.
	 *
	 * @param StatusValue $status
	 */
	public function fail( StatusValue $status ) {
		if ( $this->id === null ) {
			return;
		}
		$this->updateState( $this->id, ProduntoStore::STATE_FAILED, serialize( $status ) );
	}

	/**
	 * Set the state of the package
	 *
	 * @param int $id The non-null ID
	 * @param int $state The new state, one of the ProduntoStore::STATE_* constants
	 * @param string|null $error An optional error message
	 * @return void
	 */
	private function updateState( int $id, int $state, ?string $error = null ) {
		$this->state = $state;
		$this->dbw->newUpdateQueryBuilder()
			->update( 'produnto_package_version' )
			->set( [
				'ppv_state' => $this->state,
				'ppv_error' => $error
			] )
			->where( [ 'ppv_id' => $id ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Throw an exception if the produnto_package_version row has been inserted.
	 *
	 * @param string $func
	 */
	private function assertNotInserted( $func ) {
		if ( $this->id !== null ) {
			throw new \LogicException(
				"Can't call $func after the produnto_package_version row has been inserted" );
		}
	}

	/**
	 * Insert the produnto_package_version row, if it has not already been inserted.
	 *
	 * @return int
	 * @throws PackageBuilderError
	 */
	private function ensureInserted(): int {
		if ( $this->name === null
			|| $this->url === null
			|| $this->version === null
		) {
			throw new \LogicException( "name, version and url must be set before inserting" );
		}
		if ( $this->id === null ) {
			$packageId = $this->acquirePackage();
			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'produnto_package_version' )
				->row( [
					'ppv_package' => $packageId,
					'ppv_version' => $this->version,
					'ppv_state' => $this->state,
				] )
				->ignore()
				->caller( __METHOD__ )
				->execute();
			$this->id = $this->dbw->insertId();
			if ( !$this->id ) {
				throw new VersionAlreadyExistsError;
			}
		}
		return $this->id;
	}

	/**
	 * Insert a produnto_package row if it doesn't already exist. If it exists
	 * with the wrong URL, throw an exception.
	 *
	 * @return int The pp_id value
	 * @throws WrongUrlError
	 */
	private function acquirePackage() {
		$row = $this->dbw->newSelectQueryBuilder()
			->select( [ 'pp_id', 'pp_url' ] )
			->from( 'produnto_package' )
			->where( [ 'pp_name' => $this->name ] )
			->forUpdate()
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			if ( $this->url !== $row->pp_url ) {
				throw new WrongUrlError;
			}
			return (int)$row->pp_id;
		}
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_package' )
			->row( [
				'pp_name' => $this->name,
				'pp_url' => $this->url
			] )
			->caller( __METHOD__ )
			->execute();
		$id = $this->dbw->insertId();
		if ( !$id ) {
			throw new \RuntimeException( 'Failed to acquire ID for package' );
		}
		return $id;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

use StatusValue;
use Wikimedia\Rdbms\IDatabase;
use function array_key_exists;

/**
 * Class for adding and updating a package version
 */
class PackageBuilder {
	private TextStore $textStore;
	private FileAccess $fileAccess;
	private NameStore $nameStore;
	private ?string $name = null;
	private ?string $version = null;
	private string $upstreamRef = '';
	private ?string $fetchedUrl = null;
	private array $props = [];
	private ?int $id = null;
	private int $state = ProduntoStore::STATE_FETCHING;
	private IDatabase $dbw;

	/**
	 * @var bool Whether $props contains updated property values
	 */
	private bool $propsDirty = false;

	public function __construct(
		TextStore $textStore,
		FileAccess $fileAccess,
		NameStore $nameStore,
		IDatabase $dbw,
	) {
		$this->textStore = $textStore;
		$this->fileAccess = $fileAccess;
		$this->nameStore = $nameStore;
		$this->dbw = $dbw;
	}

	/**
	 * Resume building of a package in the fetching state.
	 *
	 * @param TextStore $textStore
	 * @param FileAccess $fileAccess
	 * @param NameStore $nameStore
	 * @param IDatabase $dbw
	 * @param PackageAccess $package
	 * @return self
	 */
	public static function resume(
		TextStore $textStore,
		FileAccess $fileAccess,
		NameStore $nameStore,
		IDatabase $dbw,
		PackageAccess $package
	) {
		$builder = new self( $textStore, $fileAccess, $nameStore, $dbw );
		$builder->name = $package->getName();
		$builder->fetchedUrl = $package->getFetchedUrl();
		$builder->props = $package->getProps();
		$builder->version = $package->getVersion();
		$builder->upstreamRef = $package->getUpstreamRef();
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
	 * Set the commit hash or some other server-dependent upstream ref
	 *
	 * @param string $ref
	 * @return $this
	 */
	public function upstreamRef( string $ref ): self {
		$this->assertNotInserted( __FUNCTION__ );
		$this->upstreamRef = $ref;
		return $this;
	}

	/**
	 * Set the fetched URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function fetchedUrl( string $url ): self {
		$this->assertNotInserted( __FUNCTION__ );
		$this->fetchedUrl = $url;
		return $this;
	}

	/**
	 * Set the type of the package
	 *
	 * @param string $type
	 * @return $this
	 */
	public function type( string $type ): self {
		return $this->setProperty( 'type', $type );
	}

	/**
	 * Set the homepage URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function homepageUrl( string $url ): self {
		return $this->setProperty( 'homepage-url', $url );
	}

	/**
	 * Set the documentation URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function docUrl( string $url ): self {
		return $this->setProperty( 'doc-url', $url );
	}

	/**
	 * Set the collaboration project page URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function collabUrl( string $url ): self {
		return $this->setProperty( 'collab-url', $url );
	}

	/**
	 * Set the bug tracker URL
	 *
	 * @param string $url
	 * @return $this
	 */
	public function issueUrl( string $url ): self {
		return $this->setProperty( 'issue-url', $url );
	}

	/**
	 * Add a localised name of the package
	 *
	 * @param string $lang
	 * @param string $name
	 * @return $this
	 */
	public function localName( string $lang, string $name ): self {
		return $this->setSubProperty( 'name', $lang, $name );
	}

	/**
	 * Add a localised description of the package.
	 *
	 * @param string $lang
	 * @param string $desc
	 * @return $this
	 */
	public function description( string $lang, string $desc ): self {
		return $this->setSubProperty( 'description', $lang, $desc );
	}

	/**
	 * Add an author to the package
	 *
	 * @param string $author
	 * @return $this
	 */
	public function author( string $author ): self {
		$this->props['authors'][] = $author;
		$this->propsDirty = true;
		return $this;
	}

	/**
	 * Set the SPDX license identifier
	 *
	 * @param string $license
	 * @return $this
	 */
	public function license( string $license ): self {
		return $this->setProperty( 'license', $license );
	}

	/**
	 * Declare that this package depends on another package
	 *
	 * @param string $package The name of the other package
	 * @param string $constraint A version constraint in a format understood by composer/semver
	 * @return $this
	 */
	public function requires( string $package, string $constraint ): self {
		return $this->setSubProperty( 'requires', $package, $constraint );
	}

	/**
	 * Add a Lua module to the package
	 *
	 * @param string $name
	 * @param string $path
	 * @return $this
	 */
	public function module( string $name, string $path ): self {
		return $this->setSubProperty( 'modules', $name, $path );
	}

	/**
	 * Set a named property
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	private function setProperty( string $name, $value ) {
		if ( !array_key_exists( $name, $this->props ) || $this->props[$name] !== $value ) {
			$this->props[$name] = $value;
			$this->propsDirty = true;
		}
		return $this;
	}

	/**
	 * Set a subproperty
	 *
	 * @param string $name
	 * @param string $subname
	 * @param mixed $value
	 * @return $this
	 */
	private function setSubProperty( string $name, string $subname, $value ) {
		if ( !array_key_exists( $name, $this->props )
			|| !array_key_exists( $subname, $this->props[$name] )
			|| $this->props[$name][$subname] !== $value
		) {
			$this->props[$name][$subname] = $value;
			$this->propsDirty = true;
		}
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

		$this->fileAccess->setCache( $id, $path, $contents );

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
		$this->updateProps( $id );
		return new PackageAccess(
			$this->fileAccess,
			$id,
			$this->name,
			$this->version,
			$this->upstreamRef,
			$this->fetchedUrl,
			$this->props,
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
			$this->fileAccess,
			$id,
			$this->name,
			$this->version,
			$this->upstreamRef,
			$this->fetchedUrl,
			$this->props,
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
	 * Set the state of the package and flush any pending property updates
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
				'ppv_error' => $error,
				'ppv_props' => PackageAccess::encodeProps( $this->props )
			] )
			->where( [ 'ppv_id' => $id ] )
			->caller( __METHOD__ )
			->execute();
		$this->propsDirty = false;
	}

	/**
	 * Flush any pending property updates
	 *
	 * @param int $id
	 */
	private function updateProps( int $id ) {
		if ( !$this->propsDirty ) {
			return;
		}
		$this->dbw->newUpdateQueryBuilder()
			->update( 'produnto_package_version' )
			->set( [
				'ppv_props' => PackageAccess::encodeProps( $this->props )
			] )
			->where( [ 'ppv_id' => $id ] )
			->caller( __METHOD__ )
			->execute();
		$this->propsDirty = false;
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
			|| $this->fetchedUrl === null
			|| $this->version === null
		) {
			throw new \LogicException( "name, version and fetchedUrl must be set before inserting" );
		}
		if ( $this->id === null ) {
			$packageId = $this->acquirePackage();
			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'produnto_package_version' )
				->row( [
					'ppv_package' => $packageId,
					'ppv_version' => $this->version,
					'ppv_upstream_ref' => $this->upstreamRef,
					'ppv_state' => $this->state,
					'ppv_props' => PackageAccess::encodeProps( $this->props ),
				] )
				->ignore()
				->caller( __METHOD__ )
				->execute();
			$this->id = $this->dbw->insertId();
			if ( !$this->id ) {
				throw new VersionAlreadyExistsError;
			}
			$this->propsDirty = false;
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
			if ( $this->fetchedUrl !== $row->pp_url ) {
				throw new WrongUrlError;
			}
			return (int)$row->pp_id;
		}

		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_package' )
			->row( [
				'pp_name' => $this->name,
				'pp_url' => $this->fetchedUrl,
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

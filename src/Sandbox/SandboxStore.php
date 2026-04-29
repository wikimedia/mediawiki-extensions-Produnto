<?php

namespace MediaWiki\Extension\Produnto\Sandbox;

use IDBAccessObject;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use Wikimedia\ObjectCache\BagOStuff;

class SandboxStore {
	/** Sandbox data array key used to store hashes by package and path */
	public const HASHES_BY_PACKAGE_PATH = 'hashes';
	/** Sandbox data array key used to store full text contents by hash */
	public const TEXTS = 'texts';
	/** Sandbox data array key used to store module information */
	public const MODULES = 'modules';
	/** Metadata array key storing the size of each sandbox */
	public const SIZES = 'sizes';
	/** Metadata array key storing the modification time of each sandbox */
	public const MODIFICATION_TIMES = 'mtimes';
	/** BagOStuff key component for sandbox storage */
	public const SANDBOX_KEY_PREFIX = 'ProduntoSandbox';
	/** BagOStuff key component for metadata storage */
	public const META_KEY_PREFIX = 'ProduntoSandboxMeta';

	/** Sandbox expiry time in seconds */
	public const SANDBOX_MAX_AGE = 2 * 86_400;
	/** Metadata expiry time in seconds */
	public const META_MAX_AGE = 30 * 86_400;
	/** Maximum total size for all sandboxes for a given user, in bytes */
	public const MAX_SANDBOX_SIZE = 8 * 1_048_576;

	public function __construct(
		private ProduntoStore $store,
		private BagOStuff $stash,
	) {
	}

	/**
	 * Create a sandbox or update an existing sandbox.
	 *
	 * @param int $userId
	 * @param string $sandboxId
	 * @return SandboxBuilder
	 */
	public function createOrUpdate( int $userId, string $sandboxId ): SandboxBuilder {
		$data = $this->fetchData( $userId, $sandboxId );
		return new SandboxBuilder( $this->store->getFileAccess( IDBAccessObject::READ_NORMAL ),
			$this->stash, $userId, $sandboxId, $data ?? [] );
	}

	/**
	 * Delete a sandbox
	 *
	 * @param int $userId
	 * @param string $sandboxId
	 */
	public function delete( int $userId, string $sandboxId ) {
		$this->stash->merge(
			$this->stash->makeKey( self::META_KEY_PREFIX, $userId ),
			static function ( $bag, $key, $currentValue, &$ttl ) use ( $sandboxId ) {
				if ( !$currentValue ) {
					return false;
				}
				unset( $currentValue[self::SIZES][$sandboxId] );
				unset( $currentValue[self::MODIFICATION_TIMES][$sandboxId] );
				return $currentValue;
			},
			self::META_MAX_AGE
		);
		$this->stash->delete( $this->stash->makeKey(
			self::SANDBOX_KEY_PREFIX, $userId, $sandboxId ) );
	}

	/**
	 * Get a sandbox from the store
	 *
	 * @param int $userId
	 * @param string $sandboxId
	 * @return SandboxAccess|null
	 */
	public function get( int $userId, string $sandboxId ): ?SandboxAccess {
		$data = $this->fetchData( $userId, $sandboxId );
		if ( $data === null ) {
			return null;
		}
		return new SandboxAccess(
			$this->store->getFileAccess( IDBAccessObject::READ_NORMAL ),
			$data
		);
	}

	/**
	 * Fetch the sandbox IDs of all sandboxes defined for a given user
	 *
	 * @param int $userId
	 * @return array
	 */
	public function getSandboxNames( int $userId ): array {
		$meta = $this->stash->get( $this->stash->makeKey( self::META_KEY_PREFIX, $userId ) );
		if ( !$meta ) {
			return [];
		}
		return array_keys( $meta[self::SIZES] ?? [] );
	}

	/**
	 * @param int $userId
	 * @return array Array of associative arrays, the keys being:
	 *   - id: The sandbox ID
	 *   - size: The size in bytes
	 *   - mtime: The UNIX time of last modification
	 */
	public function getMetadata( int $userId ): array {
		$meta = $this->stash->get( $this->stash->makeKey( self::META_KEY_PREFIX, $userId ) );
		if ( !$meta ) {
			return [];
		}
		$result = [];
		foreach ( $meta[self::SIZES] as $id => $size ) {
			$result[] = [
				'id' => $id,
				'size' => $size,
				'mtime' => $meta[self::MODIFICATION_TIMES][$id] ?? 0,
			];
		}
		return $result;
	}

	/**
	 * Get sandbox data from the stash
	 *
	 * @param int $userId
	 * @param string $sandboxId
	 * @return array|null
	 */
	private function fetchData( int $userId, string $sandboxId ): ?array {
		$key = $this->stash->makeKey( self::SANDBOX_KEY_PREFIX, $userId, $sandboxId );
		$data = $this->stash->get( $key );
		return $data === false ? null : $data;
	}
}

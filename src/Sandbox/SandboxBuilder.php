<?php

namespace MediaWiki\Extension\Produnto\Sandbox;

use MediaWiki\Extension\Produnto\Store\FileAccess;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Class for creating a new sandbox
 */
class SandboxBuilder {
	/** @var int|float|null */
	private $now = null;

	public function __construct(
		private FileAccess $fileAccess,
		private BagOStuff $stash,
		private int $userId,
		private string $sandboxId,
		private array $data
	) {
	}

	/**
	 * Add a file with known contents
	 *
	 * @param string $package
	 * @param string $path
	 * @param string $hash
	 * @param string $text
	 * @return $this
	 */
	public function addFile( string $package, string $path, string $hash, string $text ): self {
		$this->data[SandboxStore::HASHES_BY_PACKAGE_PATH][$package][$path] = $hash;
		$this->data[SandboxStore::TEXTS][$hash] = $text;
		return $this;
	}

	/**
	 * Add a file with contents defined somewhere else
	 *
	 * @param string $package
	 * @param string $path
	 * @param string $hash
	 * @return $this
	 */
	public function addFileReference( string $package, string $path, string $hash ): self {
		$this->data[SandboxStore::HASHES_BY_PACKAGE_PATH][$package][$path] = $hash;
		return $this;
	}

	/**
	 * Check whether the hash has already been added with known text
	 *
	 * @param string $hash
	 * @return bool
	 */
	public function hasHash( string $hash ): bool {
		return isset( $this->data[SandboxStore::TEXTS][$hash] );
	}

	/**
	 * Set the map of module names to package and path
	 *
	 * @param array<string,array{string,string}> $modules
	 * @return $this
	 */
	public function modules( array $modules ): self {
		$this->data[SandboxStore::MODULES] = $modules;
		return $this;
	}

	/**
	 * Access the data which has been added to the builder.
	 *
	 * @return SandboxAccess
	 */
	public function access(): SandboxAccess {
		return new SandboxAccess( $this->fileAccess, $this->data );
	}

	/**
	 * Write the sandbox to the store. Return false if the serialized size is too big.
	 *
	 * @return bool
	 */
	public function commit(): bool {
		$size = strlen( serialize( $this->data ) );
		if ( $size > SandboxStore::MAX_SANDBOX_SIZE ) {
			return false;
		}
		if ( !$this->updateMeta( $size ) ) {
			$this->evict( $size );
			if ( !$this->updateMeta( $size ) ) {
				return false;
			}
		}
		$this->stash->set( $this->getKey(), $this->data, SandboxStore::SANDBOX_MAX_AGE );
		return true;
	}

	/**
	 * Update the metadata store with information about this sandbox
	 *
	 * @param int $size The serialized size of the sandbox
	 * @return bool Success; false if this addition would overflow the sandbox
	 */
	private function updateMeta( int $size ) {
		$ok = true;
		$this->stash->merge(
			$this->getMetaKey(),
			function ( $bag, $key, $currentValue, &$ttl ) use ( $size, &$ok ) {
				$value = is_array( $currentValue ) ? $currentValue : [];
				$value[SandboxStore::SIZES][$this->sandboxId] = $size;
				$value[SandboxStore::MODIFICATION_TIMES][$this->sandboxId] = $this->getCurrentUnixTime();
				if ( array_sum( $value[SandboxStore::SIZES] ) > SandboxStore::MAX_SANDBOX_SIZE ) {
					$ok = false;
					return false;
				}
				return $value;
			},
			SandboxStore::META_MAX_AGE
		);
		return $ok;
	}

	/**
	 * The current UNIX timestamp
	 *
	 * @return int|float
	 */
	private function getCurrentUnixTime() {
		return $this->now ?? time();
	}

	/**
	 * @param int|float $time
	 * @return $this
	 */
	public function currentUnixTime( $time ): self {
		$this->now = $time;
		return $this;
	}

	/**
	 * Delete old sandboxes until a sandbox of the given size can be accomodated
	 *
	 * @param int $newSize
	 */
	private function evict( $newSize ) {
		$meta = $this->stash->get( $this->getMetaKey() );
		if ( !$meta ) {
			return;
		}
		$mtimes = $meta[SandboxStore::MODIFICATION_TIMES];
		$sizes = $meta[SandboxStore::SIZES];
		arsort( $mtimes );
		$ids = array_keys( $mtimes );
		$deletedIds = [];

		$totalSize = array_sum( $sizes ) + $newSize;
		while ( $totalSize > SandboxStore::MAX_SANDBOX_SIZE && $ids ) {
			$id = array_pop( $ids );
			$this->stash->delete( $this->getKey( $id ) );
			$deletedIds[] = $id;
			$totalSize -= $sizes[$id] ?? 0;
		}

		$this->stash->merge(
			$this->getMetaKey(),
			static function ( $bag, $key, $value, &$ttl ) use ( $deletedIds ) {
				foreach ( $deletedIds as $id ) {
					unset( $value[SandboxStore::SIZES][$id] );
					unset( $value[SandboxStore::MODIFICATION_TIMES][$id] );
				}
				return $value;
			},
			SandboxStore::META_MAX_AGE
		);
	}

	private function getMetaKey(): string {
		return $this->stash->makeKey( SandboxStore::META_KEY_PREFIX, $this->userId );
	}

	private function getKey( ?string $sandboxId = null ): string {
		return $this->stash->makeKey( SandboxStore::SANDBOX_KEY_PREFIX,
			$this->userId, $sandboxId ?? $this->sandboxId );
	}
}

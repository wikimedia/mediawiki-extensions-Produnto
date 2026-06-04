<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * Simple FileAccess class for unit testing
 */
class SimpleFileAccess implements FileAccess {
	/** @var array<int,array<string,string>> File hash indexed by package and path */
	private $hashesByPath;
	/** @var array<string,string> File contents indexed by content hash */
	private $filesByHash;

	/**
	 * @param array<int,array<string,string>> $files File contents indexed by package and path
	 */
	public function __construct( array $files = [] ) {
		$hashes = [];
		$filesByHash = [];
		foreach ( $files as $packageId => $packageFiles ) {
			foreach ( $packageFiles as $path => $contents ) {
				$hash = hash( 'sha256', $contents );
				$hashes[$packageId][$path] = $hash;
				$filesByHash[$hash] = $contents;
			}
		}
		$this->hashesByPath = $hashes;
		$this->filesByHash = $filesByHash;
	}

	/** @inheritDoc */
	public function hasFile( int $packageId, string $path ): bool {
		return isset( $this->hashesByPath[$packageId][$path] );
	}

	/** @inheritDoc */
	public function getFilePaths( int $packageId ): array {
		return array_keys( $this->hashesByPath[$packageId] ?? [] );
	}

	/** @inheritDoc */
	public function getFileContents( int $packageId, string $path ): ?string {
		$hash = $this->hashesByPath[$packageId][$path] ?? null;
		return $hash === null ? null : ( $this->filesByHash[$hash] ?? null );
	}

	/** @inheritDoc */
	public function getFileHashes( int $packageId ): array {
		return $this->hashesByPath[$packageId] ?? [];
	}

	/** @inheritDoc */
	public function getFileContentsByHash( string $hash ): ?string {
		return $this->filesByHash[$hash] ?? null;
	}

	/** @inheritDoc */
	public function setCache( int $packageId, string $path, string $hash, string $contents ): void {
		$this->hashesByPath[$packageId][$path] = $hash;
		$this->filesByHash[$hash] = $contents;
	}
}

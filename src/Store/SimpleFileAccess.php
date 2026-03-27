<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * Simple FileAccess class for unit testing
 */
class SimpleFileAccess implements FileAccess {
	/**
	 * @param array<int,array<string,string>> $files File contents indexed by package and path
	 * @param array<string,string> $filesByHash File contents indexed by content hash
	 */
	public function __construct(
		private array $files = [],
		private array $filesByHash = [],
	) {
	}

	/** @inheritDoc */
	public function hasFile( int $packageId, string $path ): bool {
		return isset( $this->files[$packageId][$path] );
	}

	/** @inheritDoc */
	public function getFileContents( int $packageId, string $path ): ?string {
		return $this->files[$packageId][$path] ?? null;
	}

	/** @inheritDoc */
	public function getFileContentsByHash( string $hash ): ?string {
		return $this->filesByHash[$hash] ?? null;
	}

	/** @inheritDoc */
	public function setCache( int $packageId, string $path, string $hash, string $contents ): void {
		$this->files[$packageId][$path] = $contents;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * Simple FileAccess class for unit testing
 */
class SimpleFileAccess implements FileAccess {

	public function __construct( private array $files = [] ) {
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
	public function setCache( int $packageId, string $path, string $contents ): void {
		$this->files[$packageId][$path] = $contents;
	}
}

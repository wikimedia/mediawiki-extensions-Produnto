<?php

namespace MediaWiki\Extension\Produnto\Store;

use FilesystemIterator;
use SplFileInfo;

class LocalFileCollection implements FileCollection {
	private array $cache = [];

	public function __construct( private string $path ) {
	}

	/** @inheritDoc */
	public function getFileContents( string $path ): ?string {
		if ( !array_key_exists( $path, $this->cache ) ) {
			$fullPath = "{$this->path}/$path";
			if ( file_exists( $fullPath ) ) {
				$this->cache[$path] = file_get_contents( $fullPath );
			} else {
				$this->cache[$path] = null;
			}
		}
		return $this->cache[$path];
	}

	/** @inheritDoc */
	public function hasFile( string $path ): bool {
		return isset( $this->cache[$path] )
			|| file_exists( "{$this->path}/$path" );
	}

	/** @inheritDoc */
	public function getFilePaths(): array {
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator(
				$this->path,
				FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
			)
		);
		$paths = [];
		/** @var SplFileInfo $entry */
		foreach ( $iter as $path => $entry ) {
			if ( $entry->isFile() ) {
				$paths[] = substr( $path, strlen( $this->path ) + 1 );
			}
		}
		return $paths;
	}

	/** @inheritDoc */
	public function getFileHashes(): array {
		$hashes = [];
		foreach ( $this->getFilePaths() as $path ) {
			$hashes[$path] = hash( 'sha256', $this->getFileContents( $path ) ?? '' );
		}
		return $hashes;
	}
}

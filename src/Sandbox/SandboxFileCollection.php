<?php

namespace MediaWiki\Extension\Produnto\Sandbox;

use MediaWiki\Extension\Produnto\Store\FileAccess;
use MediaWiki\Extension\Produnto\Store\FileCollection;

/**
 * Provide access to the contents of a package in a sandbox
 */
class SandboxFileCollection implements FileCollection {
	public function __construct(
		private readonly FileAccess $fileAccess,
		private readonly array $hashesByPath,
		private readonly array $texts,
	) {
	}

	/** @inheritDoc */
	public function getFileContents( string $path ): ?string {
		$hash = $this->hashesByPath[$path] ?? null;
		if ( $hash === null ) {
			return null;
		}
		$text = $this->texts[$hash] ?? null;
		if ( $text === null ) {
			$text = $this->fileAccess->getFileContentsByHash( $hash );
		}
		return $text;
	}

	/** @inheritDoc */
	public function hasFile( string $path ): bool {
		return isset( $this->hashesByPath[$path] );
	}

	/** @inheritDoc */
	public function getFilePaths(): array {
		return array_keys( $this->hashesByPath );
	}

	/** @inheritDoc */
	public function getFileHashes(): array {
		return $this->hashesByPath;
	}
}

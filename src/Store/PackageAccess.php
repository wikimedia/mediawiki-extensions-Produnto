<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * Read-only access to metadata and file contents relating to a committed package version
 */
class PackageAccess extends PackageMetaAccess implements FileCollection {
	public const README_PATHS = [ 'README.wiki', 'README.md' ];

	public function __construct(
		private FileAccess $fileAccess,
		private int $id,
		string $name,
		string $version,
		string $upstreamRef,
		string $fetchedUrl,
		array $props,
		int $state,
		?string $error
	) {
		parent::__construct( $name, $version, $upstreamRef, $fetchedUrl, $props, $state, $error );
	}

	/**
	 * Get the ppv_id value
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/** @inheritDoc */
	public function getFileContents( string $path ): ?string {
		return $this->fileAccess->getFileContents( $this->getId(), $path );
	}

	/** @inheritDoc */
	public function hasFile( string $path ): bool {
		return $this->fileAccess->hasFile( $this->getId(), $path );
	}

	/** @inheritDoc */
	public function getFilePaths(): array {
		return $this->fileAccess->getFilePaths( $this->getId() );
	}

	/** @inheritDoc */
	public function getFileHashes(): array {
		return $this->fileAccess->getFileHashes( $this->getId() );
	}

	/**
	 * If the package has a README file, return its path
	 *
	 * @return string|null
	 */
	public function getReadmePath(): ?string {
		foreach ( self::README_PATHS as $path ) {
			if ( $this->hasFile( $path ) ) {
				return $path;
			}
		}
		return null;
	}
}

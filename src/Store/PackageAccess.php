<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * Read-only access to metadata and file contents relating to a committed package version
 */
class PackageAccess extends PackageMetaAccess implements FileCollection {
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
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * A narrow interface for loading and caching package file paths and contents.
 */
interface FileAccess {
	/**
	 * Determine whether a package has a file with the given name.
	 *
	 * @param int $packageId
	 * @param string $path
	 * @return bool
	 */
	public function hasFile( int $packageId, string $path ): bool;

	/**
	 * Get the contents of a file from a package
	 *
	 * @param int $packageId
	 * @param string $path
	 * @return string|null
	 */
	public function getFileContents( int $packageId, string $path ): ?string;

	/**
	 * Update an entry in the text cache
	 *
	 * @param int $packageId
	 * @param string $path
	 * @param string $contents
	 */
	public function setCache( int $packageId, string $path, string $contents ): void;
}

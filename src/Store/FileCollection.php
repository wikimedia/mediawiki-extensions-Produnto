<?php

namespace MediaWiki\Extension\Produnto\Store;

interface FileCollection {
	/**
	 * Get the contents of a file from the package
	 */
	public function getFileContents( string $path ): ?string;

	/**
	 * Determine whether the package has a file with the given name.
	 */
	public function hasFile( string $path ): bool;

	/**
	 * Get all file paths in the package.
	 *
	 * @return string[]
	 */
	public function getFilePaths(): array;

	/**
	 * Get the hash of the contents of each file indexed by path.
	 *
	 * @return array<string,string>
	 */
	public function getFileHashes(): array;
}

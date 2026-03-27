<?php

namespace MediaWiki\Extension\Produnto\Store;

interface FileCollection {
	/**
	 * Get the contents of a file from the package
	 *
	 * @param string $path
	 * @return string|null
	 */
	public function getFileContents( string $path ): ?string;

	/**
	 * Determine whether the package has a file with the given name.
	 *
	 * @param string $path
	 * @return bool
	 */
	public function hasFile( string $path ): bool;

}

<?php

namespace MediaWiki\Extension\Produnto\Runtime;

interface Loader {
	/**
	 * Determine whether a package with the given name exists
	 *
	 * @param string $packageName
	 * @return bool
	 */
	public function hasPackage( string $packageName ): bool;

	/**
	 * Get a Lua module by name. Search deployed packages to find a package
	 * providing this module.
	 *
	 * @param string $moduleName
	 * @return ModuleInfo|null
	 */
	public function getModuleInfo( string $moduleName ): ?ModuleInfo;

	/**
	 * Get the contents of a file in a deployed package. If the file or package
	 * doesn't exist, return null.
	 *
	 * @param string $packageName
	 * @param string $path
	 * @return string|null
	 */
	public function getFileContents( string $packageName, string $path ): ?string;
}

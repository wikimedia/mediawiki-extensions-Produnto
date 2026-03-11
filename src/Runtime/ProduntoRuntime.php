<?php

namespace MediaWiki\Extension\Produnto\Runtime;

/**
 * Service providing access to deployed packages. For use by extensions.
 */
class ProduntoRuntime {
	/**
	 * @param Loader[] $loaders
	 */
	public function __construct(
		private array $loaders
	) {
	}

	/**
	 * Get data associated with a Lua module
	 *
	 * @param string $moduleName
	 * @return ModuleInfo|null
	 */
	public function getModuleInfo( $moduleName ): ?ModuleInfo {
		foreach ( $this->loaders as $loader ) {
			$info = $loader->getModuleInfo( $moduleName );
			if ( $info ) {
				return $info;
			}
		}
		return null;
	}

	/**
	 * Get file contents from a package
	 *
	 * @param string $packageName
	 * @param string $path
	 * @return string|null
	 */
	public function getFileContents( $packageName, $path ): ?string {
		foreach ( $this->loaders as $loader ) {
			if ( $loader->hasPackage( $packageName ) ) {
				return $loader->getFileContents( $packageName, $path );
			}
		}
		return null;
	}
}

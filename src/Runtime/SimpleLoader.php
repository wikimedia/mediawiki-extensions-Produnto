<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use function array_key_exists;

/**
 * Simple loader for testing
 */
class SimpleLoader implements Loader {
	public function __construct(
		private array $packages,
		private array $modules
	) {
	}

	/** @inheritDoc */
	public function hasPackage( string $packageName ): bool {
		return array_key_exists( $packageName, $this->packages );
	}

	/** @inheritDoc */
	public function getModuleInfo( string $moduleName ): ?ModuleInfo {
		return $this->modules[$moduleName] ?? null;
	}

	/** @inheritDoc */
	public function getFileContents( string $packageName, string $path ): ?string {
		return $this->packages[$packageName][$path] ?? null;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

/**
 * A bundle of information about a module, returned by DeploymentAccess
 */
class ModuleInfo {
	public function __construct(
		public readonly int $packageId,
		public readonly string $packageName,
		public readonly string $path,
		public readonly string $contents
	) {
	}
}

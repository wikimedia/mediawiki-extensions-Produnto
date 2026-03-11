<?php

namespace MediaWiki\Extension\Produnto\Runtime;

/**
 * A bundle of information about a module
 */
class ModuleInfo {
	public function __construct(
		public readonly string $packageName,
		public readonly string $path,
		public readonly string $contents
	) {
	}
}

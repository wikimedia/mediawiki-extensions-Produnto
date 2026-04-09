<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use MediaWiki\Extension\Produnto\Sandbox\SandboxAccess;
use MediaWiki\Parser\ParserOutput;
use Wikimedia\Message\MessageValue;

/**
 * Service providing access to deployed packages. For use by extensions.
 */
class ProduntoRuntime {
	private bool $wasSandboxUsed = false;

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
				if ( $loader instanceof SandboxAccess ) {
					$this->wasSandboxUsed = true;
				}
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
				$contents = $loader->getFileContents( $packageName, $path );
				if ( $contents !== null && $loader instanceof SandboxAccess ) {
					$this->wasSandboxUsed = true;
				}
				return $contents;
			}
		}
		return null;
	}

	/**
	 * If content from the sandbox was used, add a warning to the ParserOutput
	 *
	 * @param ParserOutput $parserOutput
	 */
	public function maybeAddSandboxWarning( ParserOutput $parserOutput ) {
		if ( $this->wasSandboxUsed ) {
			$parserOutput->addWarningMsgVal(
				MessageValue::new( 'produnto-sandbox-preview-warning' ),
			);
		}
	}
}

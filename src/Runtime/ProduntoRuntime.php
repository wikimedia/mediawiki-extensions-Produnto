<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use MediaWiki\Extension\Produnto\RepoViewer\RepoLinker;
use MediaWiki\Extension\Produnto\Sandbox\SandboxAccess;
use MediaWiki\Language\Language;
use MediaWiki\Parser\ParserOutput;
use Wikimedia\Message\MessageValue;

/**
 * Service providing access to deployed packages. For use by extensions.
 */
class ProduntoRuntime {
	private bool $wasSandboxUsed = false;

	/**
	 * @param Language $contLang
	 * @param RepoLinker $repoLinker
	 * @param Loader[] $loaders
	 */
	public function __construct(
		private readonly Language $contLang,
		private readonly RepoLinker $repoLinker,
		private readonly array $loaders,
	) {
	}

	/**
	 * Get data associated with a Lua module
	 */
	public function getModuleInfo( string $moduleName ): ?ModuleInfo {
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
	 * If the string starts with "Package:" and looks like a RepoViewer title
	 * pointing to a package file, canonicalise the title and get the contents.
	 *
	 * This isn't MediaWiki title parsing, in a way it's more strict (with no
	 * whitespace normalisation), and in another way it's more loose (allowing
	 * characters that are invalid in titles).
	 */
	public function getFileInfoByTitle( string $titleText ): ?ModuleInfo {
		if ( !preg_match( '!([^:]+):([^/]+)/(.*)!', $titleText, $m ) ) {
			return null;
		}
		[ , $nsText, $packageName, $path ] = $m;

		// Ensure that the prefix was Package:
		$ns = $this->contLang->getNsIndex( $nsText );
		if ( $ns !== NS_PACKAGE ) {
			return null;
		}

		$contents = $this->getFileContents( $packageName, $path );
		if ( $contents === null ) {
			// Initial letter is case-insensitive -- try lcfirst
			$lcPackageName = $this->contLang->lcfirst( $packageName );
			if ( $lcPackageName !== $packageName ) {
				$contents = $this->getFileContents( $lcPackageName, $path );
				if ( $contents === null ) {
					return null;
				} else {
					$packageName = $lcPackageName;
				}
			} else {
				return null;
			}
		}

		return new ModuleInfo( $packageName, $path, $contents );
	}

	/**
	 * Get file contents from a package
	 */
	public function getFileContents( string $packageName, string $path ): ?string {
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
	 */
	public function maybeAddSandboxWarning( ParserOutput $parserOutput ): void {
		if ( $this->wasSandboxUsed ) {
			$parserOutput->addWarningMsgVal(
				MessageValue::new( 'produnto-sandbox-preview-warning' ),
			);
		}
	}

	/**
	 * Add a template link to the repo viewer if the file is linkable
	 */
	public function maybeAddDependency(
		ParserOutput $parserOutput,
		string $packageName,
		string $path
	): void {
		$linkTarget = $this->repoLinker->getFileLinkTarget( $packageName, $path );
		if ( $linkTarget ) {
			$parserOutput->addTemplate( $linkTarget, 0, 0 );
		}
	}
}

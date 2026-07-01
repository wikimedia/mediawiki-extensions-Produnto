<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageFactory;
use MediaWiki\Language\LanguageFallback;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\MessageFormatterFactory;
use MediaWiki\Page\PageReference;
use MediaWiki\ShadowPage\BaseShadowPageProvider;
use MediaWiki\ShadowPage\ShadowPage;
use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use Wikimedia\Parsoid\Core\LinkTarget;

/**
 * Handler for content in the Package namespace
 */
class RepoProvider extends BaseShadowPageProvider {
	public function __construct(
		private readonly Language $contLang,
		private readonly LanguageFallback $languageFallback,
		private readonly MessageFormatterFactory $messageFormatterFactory,
		private readonly LanguageFactory $languageFactory,
		private readonly RepoLinker $repoLinker,
		private readonly LinkRenderer $linkRenderer,
		private readonly ProduntoStore $store,
		private readonly ?SyntaxHighlight $syntaxHighlight,
	) {
	}

	/** @inheritDoc */
	public function get( PageReference $title ): ?ShadowPage {
		$parts = explode( '/', $title->getDBkey(), 2 );
		if ( count( $parts ) === 2 ) {
			[ $packageName, $path ] = $parts;
		} else {
			$packageName = $parts[0];
			$path = null;
		}
		$deployment = $this->store->getActiveDeployment();
		if ( !$deployment ) {
			return null;
		}
		$package = $deployment->getPackageByName( $packageName );
		if ( !$package ) {
			$packageName = $this->contLang->lcfirst( $packageName );
			$package = $deployment->getPackageByName( $packageName );
			if ( !$package ) {
				return null;
			}
		}

		if ( $path === null ) {
			$isIndex = true;
			$contents = null;
			$readmePath = $package->getReadmePath();
			if ( $readmePath !== null ) {
				$path = $readmePath;
				$contents = $package->getFileContents( $readmePath );
			}
			if ( $path === null ) {
				// Placeholder for message
				$path = PackageAccess::README_PATHS[0];
			}
		} else {
			if ( str_starts_with( $path, '/' ) ) {
				$path = $this->repoLinker->getPathFromFallback( $package, substr( $path, 1 ) );
				if ( $path === null ) {
					return null;
				}
			}
			$isIndex = false;
			$contents = $package->getFileContents( $path );
			if ( $contents === null ) {
				return null;
			}
		}

		return new RepoPage(
			$this->languageFallback,
			$this->messageFormatterFactory,
			$this->languageFactory,
			$this->repoLinker,
			$this->linkRenderer,
			$this->syntaxHighlight,
			$this->getParseHelper(),
			$isIndex, $title, $package, $path, $contents
		);
	}

	/** @inheritDoc */
	public function existsForLink( LinkTarget $link ): bool {
		$deployment = $this->store->getActiveDeployment();
		if ( !$deployment ) {
			return false;
		}
		$parts = explode( '/', $link->getDBkey(), 2 );
		if ( count( $parts ) === 2 ) {
			[ $packageName, $path ] = $parts;
		} else {
			$packageName = $parts[0];
			$path = null;
		}

		$package = $deployment->getPackageByName( $packageName );
		if ( !$package ) {
			$packageName = $this->contLang->lcfirst( $packageName );
			$package = $deployment->getPackageByName( $packageName );
		}
		if ( $path === null ) {
			return $package !== null;
		}
		return (bool)$package?->hasFile( $path );
	}
}

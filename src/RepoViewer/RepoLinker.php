<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\TitleParser;

/**
 * Utilities for dealing with RepoViewer titles
 */
class RepoLinker {
	private ?string $cachedPackage = null;
	private ?LinkTarget $cachedPackageLink = null;

	public function __construct(
		private readonly TitleParser $titleParser
	) {
	}

	/**
	 * Get a link to the package index page
	 */
	public function getPackageLinkTarget( string $package ): ?LinkTarget {
		if ( $package === $this->cachedPackage ) {
			return $this->cachedPackageLink;
		}
		$link = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $package );
		$this->cachedPackage = $package;
		$this->cachedPackageLink = $link;
		return $link;
	}

	/**
	 * Get a link to a file in a package
	 */
	public function getFileLinkTarget( string $package, string $path ): ?LinkTarget {
		return $this->getReadableLinkTarget( $package, $path )
			?? $this->getFallbackLinkTarget( $package, $path );
	}

	public function getPathFromFallback( PackageAccess $package, string $nameHash ): ?string {
		foreach ( $package->getFilePaths() as $path ) {
			if ( $this->shortHash( $path ) === $nameHash ) {
				// Don't serve file contents from the fallback URL if the path
				// has a readable URL
				if ( $this->getReadableLinkTarget( $package->getName(), $path ) ) {
					return null;
				}

				return $path;
			}
		}
		return null;
	}

	/**
	 * Provide a 1:1 mapping of a path to a readable title, or null if a readable
	 * title can't be constructed.
	 *
	 * RepoProvider::get() implements the inverse of this mapping. If a path is
	 * modified by title canonicalization, this function will return null since
	 * RepoProvider wouldn't be able to uniquely convert the title back to a path.
	 */
	private function getReadableLinkTarget( string $package, string $path ): ?LinkTarget {
		$packageLink = $this->getPackageLinkTarget( $package );
		if ( !$packageLink ) {
			return null;
		}
		$dbKey = $packageLink->getDBkey() . '/' . $path;

		$target = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $dbKey );
		if ( !$target || $target->getDBkey() !== $dbKey ) {
			return null;
		}
		return $target;
	}

	private function getFallbackLinkTarget( string $package, string $path ): ?LinkTarget {
		$packageLink = $this->getPackageLinkTarget( $package );
		if ( !$packageLink ) {
			return null;
		}
		$dbKey = $packageLink->getDBkey() . '//' . $this->shortHash( $path );
		return $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $dbKey );
	}

	/**
	 * Get a short hash for a path. Strict collision resistance is not
	 * necessary, we just want most paths to be visible in the repo viewer.
	 * If two paths in the same package have the same hash, pages using both
	 * will be purged when either is updated, which is harmless.
	 */
	private function shortHash( string $path ): string {
		return substr( hash( 'sha256', $path ), 0, 16 );
	}
}

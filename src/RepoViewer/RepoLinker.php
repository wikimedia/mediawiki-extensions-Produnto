<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;

class RepoLinker {
	private ?string $cachedPackage = null;
	private ?LinkTarget $cachedPackageLink = null;

	public function __construct(
		private TitleParser $titleParser
	) {
	}

	public function getPackageLinkTarget( string $package ): ?LinkTarget {
		if ( $package === $this->cachedPackage ) {
			return $this->cachedPackageLink;
		}
		try {
			$link = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $package );
		} catch ( MalformedTitleException ) {
			return null;
		}
		$this->cachedPackage = $package;
		$this->cachedPackageLink = $link;
		return $link;
	}

	public function getFileLinkTarget( string $package, string $path ): ?LinkTarget {
		$packageLink = $this->getPackageLinkTarget( $package );
		if ( !$packageLink ) {
			return null;
		}
		$dbKey = $packageLink->getDBkey() . '/' . $path;

		try {
			$target = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $dbKey );
		} catch ( MalformedTitleException ) {
			return null;
		}

		return $target->getDBkey() === $dbKey ? $target : null;
	}
}

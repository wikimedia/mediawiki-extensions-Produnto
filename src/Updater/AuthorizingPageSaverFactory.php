<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Page\PageStore;
use MediaWiki\Permissions\Authority;
use MediaWiki\Storage\PageUpdaterFactory;

class AuthorizingPageSaverFactory {
	public function __construct(
		private PageStore $pageStore,
		private PageUpdaterFactory $pageUpdaterFactory,
		private Updater $updater,
	) {
	}

	public function create( Authority $authority ): AuthorizingPageSaver {
		return new AuthorizingPageSaver(
			$this->pageStore, $this->pageUpdaterFactory, $this->updater, $authority );
	}
}

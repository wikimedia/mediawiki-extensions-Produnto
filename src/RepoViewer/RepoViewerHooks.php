<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Extension\Produnto\Store\PackageProtectionStore;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Title\Title;

class RepoViewerHooks implements
	GetUserPermissionsErrorsHook,
	SkinTemplateNavigation__UniversalHook,
	MediaWikiPerformActionHook
{
	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly PackageProtectionStore $protection,
	) {
	}

	private function getPackageTitle( Title $title ): Title {
		return Title::makeTitle( NS_PACKAGE, explode( '/', $title->getDBkey(), 2 )[0] );
	}

	/**
	 * Override action=protect for package pages to enable package protection.
	 * @inheritDoc
	 */
	public function onMediaWikiPerformAction( $output, $article, $title, $user, $request, $mediaWiki ) {
		if ( $title->inNamespace( NS_PACKAGE ) && $mediaWiki->getAction() === 'protect' ) {
			$action = new PackageProtectAction(
				$article, $this->getPackageTitle( $title ), $this->protection, $this->permissionManager
			);
			$action->show();
			return false;
		}
		return true;
	}

	/**
	 * Add a protection action for users with the protect right on package pages.
	 * File subpages link to the containing package because protection applies
	 * to deployment of the entire package, rather than individual files.
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$title = $sktemplate->getTitle();
		if ( $title->getNamespace() !== NS_PACKAGE || !$sktemplate->getAuthority()->isAllowed( 'protect' ) ) {
			return;
		}
		$packageTitle = $this->getPackageTitle( $title );
		$isProtected = $this->protection->getProtection( $packageTitle->getDBkey() ) !== null;
		$links['actions']['produnto-protect'] = [
			'text' => $sktemplate->msg( $isProtected ? 'skin-action-unprotect' : 'skin-action-protect' )->text(),
			'href' => $packageTitle->getLocalURL( [ 'action' => 'protect' ] ),
			'icon' => 'lock',
		];
	}

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $action === 'edit' && $title->getNamespace() === NS_PACKAGE ) {
			$result = 'produnto-viewer-edit-denied';
			return false;
		}
		return true;
	}

}

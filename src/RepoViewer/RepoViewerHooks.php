<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Permissions\Hook\GetUserPermissionsErrorsHook;

class RepoViewerHooks implements GetUserPermissionsErrorsHook {

	/** @inheritDoc */
	public function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $action === 'edit' && $title->getNamespace() === NS_PACKAGE ) {
			$result = 'produnto-viewer-edit-denied';
			return false;
		}
		return true;
	}
}

<?php

namespace MediaWiki\Extension\Produnto;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-produnto',
			'addTable',
			'produnto_deployment',
			dirname( __DIR__ ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql",
			true
		] );
	}
}

<?php

namespace MediaWiki\Extension\Produnto;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaHooks implements LoadExtensionSchemaUpdatesHook {
	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = dirname( __DIR__ ) . '/sql';
		$type = $updater->getDB()->getType();
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-produnto',
			'addTable',
			'produnto_deployment',
			"$dir/$type/tables-generated.sql",
			true
		] );

		// T439402
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-produnto',
			'modifyField',
			'produnto_package_version',
			'ppv_package',
			"$dir/$type/patch-produnto_package_version-ppv_package.sql",
			true
		] );
		if ( $type === 'mysql' ) {
			$updater->addExtensionUpdateOnVirtualDomain( [
				'virtual-produnto',
				'modifyField',
				'produnto_file_name',
				'pfn_name',
				"$dir/$type/patch-produnto_file_name-pfn_name.sql",
				true
			] );
		}
	}
}

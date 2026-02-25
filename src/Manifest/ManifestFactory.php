<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\PackageAccess;

/**
 * Detect a manifest file and invoke the relevant manifest parser.
 *
 * Alternative manifest parsers such as LuaRocks may one day plug in here.
 */
class ManifestFactory {
	/**
	 * @param PackageAccess $package
	 * @return ManifestStatus
	 */
	public function parseManifest( PackageAccess $package ) {
		foreach ( $this->getManifestParsers() as $parser ) {
			if ( $parser->hasManifest( $package ) ) {
				return $parser->parse( $package );
			}
		}
		return ManifestStatus::newFatal( 'produnto-fetch-no-manifest' );
	}

	/**
	 * @return ProduntoJsonManifestParser[]
	 */
	private function getManifestParsers() {
		return [
			new ProduntoJsonManifestParser
		];
	}
}

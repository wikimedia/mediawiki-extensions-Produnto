<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\FileCollection;

/**
 * Detect a manifest file and invoke the relevant manifest parser.
 *
 * Alternative manifest parsers such as LuaRocks may one day plug in here.
 */
class ManifestFactory {
	public function parseManifest( FileCollection $package ): ManifestStatus {
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
	private function getManifestParsers(): array {
		return [
			new ProduntoJsonManifestParser
		];
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\FileCollection;

/**
 * Hierarchy for manifest detectors/parsers
 */
interface ManifestParser {
	/**
	 * Determine whether a package has a manifest recognised by this parser
	 *
	 * @param FileCollection $package
	 * @return bool
	 */
	public function hasManifest( FileCollection $package ): bool;

	/**
	 * Parse the detected manifest.
	 *
	 * @param FileCollection $package
	 * @return ManifestStatus
	 */
	public function parse( FileCollection $package ): ManifestStatus;
}

<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\PackageAccess;

/**
 * Hierarchy for manifest detectors/parsers
 */
interface ManifestParser {
	/**
	 * Determine whether a package has a manifest recognised by this parser
	 *
	 * @param PackageAccess $package
	 * @return bool
	 */
	public function hasManifest( PackageAccess $package ): bool;

	/**
	 * Parse the detected manifest.
	 *
	 * @param PackageAccess $package
	 * @return ManifestStatus
	 */
	public function parse( PackageAccess $package ): ManifestStatus;
}

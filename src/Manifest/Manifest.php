<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\PackageBuilder;

/**
 * A manifest is the result of a ManifestParser
 */
interface Manifest {
	/**
	 * Copy package properties from the manifest to the PackageBuilder
	 *
	 * @param PackageBuilder $builder
	 */
	public function populateProps( PackageBuilder $builder );
}

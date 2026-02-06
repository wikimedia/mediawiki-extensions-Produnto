<?php

namespace MediaWiki\Extension\Produnto\Hook;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Updater\ValidationStatus;

interface ProduntoValidatePackageHook {
	/**
	 * A hook called to allow extensions to validate a package which is proposed
	 * for deployment. The extension may add errors to the ValidationStatus.
	 *
	 * @param PackageAccess $package
	 * @param ValidationStatus $status
	 * @return bool True to allow the package, false to reject it, in which case
	 *   an error should also be added to ValidationStatus.
	 */
	public function onProduntoValidatePackage(
		PackageAccess $package,
		ValidationStatus $status
	): bool;
}

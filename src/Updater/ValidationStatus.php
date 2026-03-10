<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use StatusValue;

/**
 * @extends StatusValue<never>
 */
class ValidationStatus extends StatusValue {
	/** @var PackageAccess[] */
	private $packages = [];
	/** @var array<string,array{int,string}>|null */
	private ?array $modules = null;
	/** @var array */
	private $extensionItems = [];

	/**
	 * Add a validated package
	 *
	 * @param PackageAccess $package
	 */
	public function addPackage( PackageAccess $package ) {
		$this->packages[] = $package;
	}

	/**
	 * Get the validated package list
	 *
	 * @return PackageAccess[]
	 */
	public function getPackages() {
		return $this->packages;
	}

	/**
	 * @param array<string,array{int,string}> $modules An associative array of
	 *   modules where the key is the module name and the value is a list of
	 *   package version ID and the path inside the package.
	 */
	public function setModules( $modules ) {
		$this->modules = $modules;
	}

	/**
	 * @return array<string,array{int,string}> An associative array of
	 *    modules where the key is the module name and the value is a list of
	 *    package version ID and the path inside the package.
	 */
	public function getModules() {
		return $this->modules;
	}
}

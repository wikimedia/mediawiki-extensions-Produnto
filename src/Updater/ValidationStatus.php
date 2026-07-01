<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use StatusValue;

/**
 * @extends StatusValue<never>
 */
class ValidationStatus extends StatusValue {
	/** @var PackageAccess[] */
	private array $packages = [];
	/** @var array<string,array{int,string}>|null */
	private ?array $modules = null;

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
	public function getPackages(): array {
		return $this->packages;
	}

	/**
	 * @param array<string,array{int,string}> $modules An associative array of
	 *   modules where the key is the module name and the value is a list of
	 *   package version ID and the path inside the package.
	 */
	public function setModules( array $modules ): void {
		$this->modules = $modules;
	}

	/**
	 * @return ?array<string,array{int,string}> An associative array of
	 *    modules where the key is the module name and the value is a list of
	 *    package version ID and the path inside the package.
	 */
	public function getModules(): ?array {
		return $this->modules;
	}
}

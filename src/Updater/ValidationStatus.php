<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use StatusValue;

/**
 * @extends StatusValue<never>
 */
class ValidationStatus extends \StatusValue {
	/** @var PackageAccess[] */
	private $packages = [];
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
	 * A hook can use this to store arbitrary data during validation
	 *
	 * @param string $name
	 * @param mixed $value Does not need to be serializable
	 */
	public function setExtensionData( $name, $value ) {
		$this->extensionItems[$name] = $value;
	}

	/**
	 * Get data stored during validation. To be used by a deployment hook.
	 *
	 * @param string $name
	 * @return mixed|null
	 */
	public function getExtensionData( $name ) {
		return $this->extensionItems[$name] ?? null;
	}
}

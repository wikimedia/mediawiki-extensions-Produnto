<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Revision\RevisionRecord;
use StatusValue;
use stdClass;

/**
 * @extends StatusValue<never>
 */
class UpdateStatus extends StatusValue {
	/** @var PackageAccess[] */
	private $packages = [];
	/** @var array<string,array{int,string}>|null */
	private ?array $modules = null;
	private ?DeploymentAccess $deployment = null;
	private ?stdClass $data = null;
	private ?RevisionRecord $revision = null;

	/**
	 * Add a validated package
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

	/**
	 * Store the deployment. This is called after the validated package set is deployed.
	 */
	public function setDeployment( DeploymentAccess $deployment ) {
		$this->deployment = $deployment;
	}

	/**
	 * Get the deployment, or throw if the packages have not been deployed
	 */
	public function getDeployment(): DeploymentAccess {
		return $this->deployment;
	}

	/**
	 * Check whether a deployment has been done.
	 */
	public function isDeployed(): bool {
		return (bool)$this->deployment;
	}

	/**
	 * Set the package name to version map which is used to generate content for the page
	 */
	public function setData( stdClass $data ) {
		$this->data = $data;
	}

	/**
	 * Get the package name to version map which is used to generate content for the page
	 *
	 * @suppress PhanTypeMismatchReturnNullable -- throws if setData() has not been called
	 */
	public function getData(): stdClass {
		return $this->data;
	}

	/**
	 * Set the MediaWiki:Packages.json revision
	 */
	public function setRevision( RevisionRecord $revision ) {
		$this->revision = $revision;
	}

	/**
	 * Get the MediaWiki:Packages.json revision, or throw if the revision was not saved
	 */
	public function getRevision(): RevisionRecord {
		return $this->revision;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Updater;

use Composer\Semver\Semver;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\HookContainer\HookContainer;
use stdClass;

class Validator {
	private UpdateStatus $status;
	/** @var array<string,PackageAccess> */
	private array $packages;

	/**
	 * @param ProduntoStore $store
	 * @param HookContainer $hookContainer
	 * @param mixed $data Typically a stdClass unpacked from JSON. If it is
	 *    anything else, the status will have a suitable error.
	 */
	public function __construct(
		private ProduntoStore $store,
		private HookContainer $hookContainer,
		private mixed $data
	) {
		$this->status = new UpdateStatus;
	}

	public function validate(): UpdateStatus {
		$this->doValidation();
		return $this->status;
	}

	private function doValidation() {
		if ( !( $this->data instanceof stdClass ) ) {
			$this->fatal( 'produnto-update-invalid' );
			return;
		}
		$this->status->setData( $this->data );
		$this->fetchPackages();
		$this->checkModuleConflicts();
		$this->checkRequirements();
	}

	private function fetchPackages() {
		$this->packages = [];
		foreach ( $this->data as $name => $version ) {
			if ( !is_string( $name ) || !is_string( $version ) ) {
				$this->fatal( 'produnto-update-invalid' );
				continue;
			}

			$package = $this->store->getPackageByName( $name, $version );
			if ( !$package ) {
				$this->fatal( 'produnto-update-missing-package', $name, $version );
				continue;
			}
			if ( $package->getState() === ProduntoStore::STATE_FETCHING ) {
				$this->fatal( 'produnto-update-fetching-package', $name, $version );
				continue;
			}
			if ( $package->getState() === ProduntoStore::STATE_FAILED ) {
				$this->fatal( 'produnto-update-failed-package', $name, $version );
				continue;
			}
			$this->status->addPackage( $package );
			$this->packages[$name] = $package;
		}
	}

	/**
	 * Check for packages defining the same module name.
	 *
	 * The module name is the string that is passed to `require()` in Lua.
	 * Following the LuaRocks convention, module names should typically be
	 * composed of a set of dot-separated components where the first component
	 * is the package name. So it should be rare for two packages to use the
	 * same module name.
	 */
	private function checkModuleConflicts() {
		$modules = [];
		$packageNamesById = [];
		foreach ( $this->packages as $name => $package ) {
			$packageNamesById[$package->getId()] = $name;

			foreach ( $package->getModules() as $moduleName => $path ) {
				if ( array_key_exists( $moduleName, $modules ) ) {
					$conflictId = $modules[$moduleName][0];
					$conflictName = $packageNamesById[$conflictId];
					$this->warning( 'produnto-update-module-conflict',
						$package->getName(), $moduleName, $conflictName );
					continue;
				}
				$modules[$moduleName] = [ $package->getId(), $path ];
			}

		}
		$this->status->setModules( $modules );
	}

	private function checkRequirements() {
		foreach ( $this->packages as $name => $package ) {
			$requires = $package->getRequires();
			foreach ( $requires as $requiredName => $constraint ) {
				$haveVersion = '';

				$platformVersions = $this->getPlatformVersions();
				if ( isset( $platformVersions[$requiredName] ) ) {
					$haveVersion = $platformVersions[$requiredName];
					$ok = Semver::satisfies( $platformVersions[$requiredName], $constraint );
					$msg = 'produnto-update-requires-unsatisfied-platform';
				} elseif ( isset( $this->packages[$requiredName] ) ) {
					$haveVersion = $this->packages[$requiredName]->getVersion();
					$ok = Semver::satisfies( $haveVersion, $constraint );
					$msg = 'produnto-update-requires-unsatisfied';
				} else {
					$ok = false;
					$msg = 'produnto-update-requires-missing';
				}
				if ( !$ok ) {
					$this->warning( $msg, $name, $requiredName, $constraint, $haveVersion );
				}
			}
		}
	}

	/**
	 * Get the versions of platform components: names that can be required but
	 * don't exist as packages.
	 *
	 * @return array<string,string>
	 */
	private function getPlatformVersions(): array {
		$versions = [
			'MediaWiki' => MW_VERSION
		];
		( new HookRunner( $this->hookContainer ) )->onProduntoPlatformVersions( $versions );
		return $versions;
	}

	/**
	 * @param string $message
	 * @param string|int|float ...$parameters
	 */
	private function fatal( string $message, ...$parameters ) {
		$this->status->fatal( $message, ...$parameters );
	}

	/**
	 * @param string $message
	 * @param string|int|float ...$parameters
	 */
	private function warning( string $message, ...$parameters ) {
		$this->status->warning( $message, ...$parameters );
	}
}

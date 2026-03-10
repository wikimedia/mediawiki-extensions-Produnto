<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use stdClass;

class Updater {
	public function __construct(
		private ProduntoStore $store,
	) {
	}

	/**
	 * Validate the package to version map
	 *
	 * @param mixed $packages Typically a stdClass unpacked from JSON. If it is
	 *   anything else, the status will have a suitable error.
	 * @return ValidationStatus
	 */
	public function validateDeployment( $packages ) {
		$status = new ValidationStatus;
		if ( !( $packages instanceof stdClass ) ) {
			$status->fatal( 'produnto-update-invalid' );
			return $status;
		}

		$modules = [];
		$packageNamesById = [];
		foreach ( $packages as $name => $version ) {
			if ( !is_string( $name ) || !is_string( $version ) ) {
				$status->fatal( 'produnto-update-invalid' );
				continue;
			}

			$package = $this->store->getPackageByName( $name, $version );
			if ( !$package ) {
				$status->fatal( 'produnto-update-missing-package', $name, $version );
				continue;
			}

			$packageNamesById[$package->getId()] = $name;

			foreach ( $package->getModules() as $moduleName => $path ) {
				if ( array_key_exists( $moduleName, $modules ) ) {
					$conflictId = $modules[$moduleName][0];
					$conflictName = $packageNamesById[$conflictId];
					$status->warning( 'produnto-update-module-conflict',
						$moduleName, $conflictName, $package->getName() );
					continue;
				}
				$modules[$moduleName] = [ $package->getId(), $path ];
			}

			$status->addPackage( $package );
		}
		$status->setModules( $modules );
		return $status;
	}

	/**
	 * Deploy a previously validated set of package versions.
	 * Create the deployment and activate it.
	 *
	 * @param ValidationStatus $status
	 * @param int $revId The revision ID of the saved JSON page
	 */
	public function deploy( ValidationStatus $status, $revId ) {
		$deploymentBuilder = $this->store->createDeployment()
			->revId( $revId );
		foreach ( $status->getPackages() as $package ) {
			$deploymentBuilder->addPackage( $package );
		}
		$deploymentBuilder->modules( $status->getModules() );
		$deployment = $deploymentBuilder->commit();
		$this->store->activateDeployment( $deployment );
	}
}

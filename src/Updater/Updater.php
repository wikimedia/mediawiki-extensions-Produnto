<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use stdClass;

/**
 * Unauthenticated backend for updating the deployment tables
 */
class Updater {
	/** @var array<string,UpdateStatus> */
	private $validationResults = [];
	private ?TitleValue $packagesTitle = null;

	public function __construct(
		private readonly Config $config,
		private readonly TitleParser $titleParser,
		private readonly ProduntoStore $store,
		private readonly JobQueueGroup $jobQueueGroup,
	) {
	}

	/**
	 * Validate the package to version map
	 *
	 * @param mixed $packages Typically a stdClass unpacked from JSON. If it is
	 *   anything else, the status will have a suitable error.
	 */
	public function validateDeployment( $packages ): UpdateStatus {
		$status = new UpdateStatus;
		if ( !( $packages instanceof stdClass ) ) {
			$status->fatal( 'produnto-update-invalid' );
			return $status;
		}
		$status->setData( $packages );

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
			if ( $package->getState() === ProduntoStore::STATE_FETCHING ) {
				$status->fatal( 'produnto-update-fetching-package', $name, $version );
				continue;
			}
			if ( $package->getState() === ProduntoStore::STATE_FAILED ) {
				$status->fatal( 'produnto-update-failed-package', $name, $version );
				continue;
			}

			$packageNamesById[$package->getId()] = $name;

			foreach ( $package->getModules() as $moduleName => $path ) {
				if ( array_key_exists( $moduleName, $modules ) ) {
					$conflictId = $modules[$moduleName][0];
					$conflictName = $packageNamesById[$conflictId];
					$status->warning( 'produnto-update-module-conflict',
						$package->getName(), $moduleName, $conflictName );
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
	 * @param UpdateStatus $status in/out: The new deployment will be set in this status
	 * @param int $revId The revision ID of the saved JSON page
	 * @param UserIdentity $user
	 */
	public function deploy( UpdateStatus $status, int $revId, UserIdentity $user ): void {
		$prevDeploymentId = $this->store->getActiveDeployment()?->getId() ?? 0;

		$deploymentBuilder = $this->store->createDeployment()
			->revId( $revId );
		foreach ( $status->getPackages() as $package ) {
			$deploymentBuilder->addPackage( $package );
		}
		$deploymentBuilder->modules( $status->getModules() );
		$deployment = $deploymentBuilder->commit();
		$this->store->activateDeployment( $deployment );

		$this->jobQueueGroup->push( new JobSpecification(
			'ProduntoUpdate',
			[
				'oldId' => $prevDeploymentId,
				'newId' => $deployment->getId(),
				'causeAgent' => $user->getName(),
			] + Job::newRootJobParams(
				"ProduntoUpdate:$prevDeploymentId:{$deployment->getId()}"
			)
		) );
		$status->setDeployment( $deployment );
	}

	/**
	 * Save the validation result pre-save
	 *
	 * @param stdClass $data The JSON package data, used as a key
	 * @param UpdateStatus $status
	 */
	public function saveValidationResult( stdClass $data, UpdateStatus $status ) {
		$hash = json_encode( $data );
		$this->validationResults[$hash] = $status;
	}

	/**
	 * Retrieve a validation result post-save
	 * @param stdClass $data The JSON package data, used as a key
	 * @return ?UpdateStatus
	 */
	public function getValidationResult( stdClass $data ): ?UpdateStatus {
		$hash = json_encode( $data );
		return $this->validationResults[$hash] ?? null;
	}

	/**
	 * Get the title of [[MediaWiki:Packages.json]]
	 */
	public function getPackagesTitleValue(): TitleValue {
		if ( !$this->packagesTitle ) {
			$titleText = $this->config->get( 'ProduntoPackagesTitle' );
			if ( $titleText === null ) {
				$this->packagesTitle = new TitleValue( NS_MEDIAWIKI, 'Packages.json' );
			} else {
				$this->packagesTitle = $this->titleParser->parseTitle( $titleText );
			}
		}
		return $this->packagesTitle;
	}
}

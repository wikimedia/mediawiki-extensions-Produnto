<?php

namespace MediaWiki\Extension\Produnto;

use MediaWiki\Extension\Produnto\Hook\ProduntoCreateDeploymentHook;
use MediaWiki\Extension\Produnto\Hook\ProduntoValidatePackageHook;
use MediaWiki\Extension\Produnto\Store\DeploymentBuilder;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Updater\ValidationStatus;
use MediaWiki\HookContainer\HookContainer;

class ProduntoHookRunner implements
	ProduntoCreateDeploymentHook,
	ProduntoValidatePackageHook
{
	public function __construct(
		private HookContainer $hookContainer
	) {
	}

	/** @inheritDoc */
	public function onProduntoCreateDeployment(
		DeploymentBuilder $deploymentBuilder, ValidationStatus $status
	): void {
		$this->hookContainer->run(
			'ProduntoCreateDeployment',
			[ $deploymentBuilder, $status ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onProduntoValidatePackage(
		PackageAccess $package,
		ValidationStatus $status
	): bool {
		return $this->hookContainer->run(
			'ProduntoValidatePackage',
			[ $package, $status ],
		);
	}
}

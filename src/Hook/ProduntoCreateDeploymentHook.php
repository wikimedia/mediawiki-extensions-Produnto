<?php

namespace MediaWiki\Extension\Produnto\Hook;

use MediaWiki\Extension\Produnto\Store\DeploymentBuilder;
use MediaWiki\Extension\Produnto\Updater\ValidationStatus;

interface ProduntoCreateDeploymentHook {
	/**
	 * A hook called before creation of a deployment. An extension may use the
	 * ValidationStatus to modify the DeploymentBuilder.
	 *
	 * @param DeploymentBuilder $deploymentBuilder
	 * @param ValidationStatus $status
	 */
	public function onProduntoCreateDeployment(
		DeploymentBuilder $deploymentBuilder,
		ValidationStatus $status
	): void;
}

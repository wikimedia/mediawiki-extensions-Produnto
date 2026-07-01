<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;

class SqlLoader implements Loader {
	private bool $deploymentLoaded = false;
	private ?DeploymentAccess $deployment;

	public function __construct(
		private readonly ProduntoStore $store,
	) {
	}

	/** @inheritDoc */
	public function hasPackage( string $packageName ): bool {
		return (bool)$this->getDeployment()?->getPackageByName( $packageName );
	}

	/** @inheritDoc */
	public function getModuleInfo( string $moduleName ): ?ModuleInfo {
		return $this->getDeployment()?->getModuleInfo( $moduleName );
	}

	/** @inheritDoc */
	public function getFileContents( string $packageName, string $path ): ?string {
		return $this->getDeployment()
			?->getPackageByName( $packageName )
			?->getFileContents( $path );
	}

	private function getDeployment(): ?DeploymentAccess {
		if ( !$this->deploymentLoaded ) {
			$this->deployment = $this->store->getActiveDeployment();
			$this->deploymentLoaded = true;
		}
		return $this->deployment;
	}
}

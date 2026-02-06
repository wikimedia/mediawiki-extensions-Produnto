<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\Store\ProduntoStore;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Updater\UpdaterHooks
 */
class UpdaterHooksTest extends \MediaWikiIntegrationTestCase {
	public function testDeploy() {
		$this->clearHook( 'ProduntoValidatePackage' );
		$this->clearHook( 'ProduntoCreateDeployment' );
		$store = $this->getStore();
		$store->createPackageVersion()
			->name( 'test' )
			->version( '1.0.0' )
			->url( '' )
			->commit();
		$this->editPage(
			'MediaWiki:Packages.json',
			'{"test": "1.0.0"}'
		);
		$deployment = $store->getActiveDeployment();
		$this->assertSame( 1, $deployment->getId() );
		$packages = $deployment->getPackages();
		$this->assertCount( 1, $packages );
		$this->assertSame( 'test', $packages[0]->getName() );
	}

	private function getStore(): ProduntoStore {
		return $this->getServiceContainer()->get( 'Produnto.Store' );
	}
}

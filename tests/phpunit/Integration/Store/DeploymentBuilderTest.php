<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Store\DeploymentBuilder
 * @covers \MediaWiki\Extension\Produnto\Store\DeploymentAccess
 */
class DeploymentBuilderTest extends \MediaWikiIntegrationTestCase {
	public function testCommit() {
		$store = ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
		$foo = $store->createPackageVersion()
			->name( 'foo' )
			->version( '1.0.0' )
			->fetchedUrl( 'https://foo' )
			->description( 'en', 'A package providing foo' )
			->addFile( 'init.lua', 'return "foo"' )
			->commit();

		$bar = $store->createPackageVersion()
			->name( 'bar' )
			->version( '1.0.0' )
			->fetchedUrl( 'https://bar' )
			->addFile( 'init.lua', 'return "bar"' )
			->commit();

		$modules = [
			'foo' => [ $foo->getId(), 'init.lua' ],
			'bar' => [ $bar->getId(), 'init.lua' ],
		];

		$deployment = $store->createDeployment()
			->revId( 100 )
			->addPackage( $foo )
			->addPackage( $bar )
			->addData( 'data', [ 'a' => 'b' ] )
			->modules( $modules )
			->commit();

		$store->activateDeployment( $deployment );

		$this->assertFields( $deployment );

		$deployment = $store->getActiveDeployment();
		$this->assertFields( $deployment );
	}

	private function assertFields( DeploymentAccess $deployment ) {
		$this->assertSame( 1, $deployment->getId() );
		$this->assertSame( [ 'a' => 'b' ], $deployment->getData( 'data' ) );

		$info = $deployment->getModuleInfo( 'foo' );
		$this->assertSame( 'foo', $info->packageName );
		$this->assertSame( 'init.lua', $info->path );
		$this->assertSame( 'return "foo"', $info->contents );

		$info = $deployment->getModuleInfo( 'bar' );
		$this->assertSame( 'bar', $info->packageName );
		$this->assertSame( 'init.lua', $info->path );
		$this->assertSame( 'return "bar"', $info->contents );

		$this->assertNull( $deployment->getModuleInfo( 'no' ) );

		$foo = $deployment->getPackageByName( 'foo' );
		$this->assertSame( 'foo', $foo->getName() );
		$this->assertSame( '1.0.0', $foo->getVersion() );
		$this->assertSame( 'https://foo', $foo->getFetchedUrl() );
		$this->assertSame( 'A package providing foo', $foo->getDescription( 'en' ) );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\Store\ProduntoStore;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\ProduntoStore
 * @group Database
 */
class ProduntoStoreTest extends \MediaWikiIntegrationTestCase {
	private function getStore() {
		return new ProduntoStore(
			new ValidateDomainDbProvider( $this->getServiceContainer()->getDBLoadBalancerFactory() )
		);
	}

	public function testCreateDeployment() {
		$da = $this->getStore()
			->createDeployment()
			->revId( 100 )
			->commit();
		$this->assertSame( 1, $da->getId() );
	}

	public function testActivateDeployment() {
		$store = $this->getStore();
		$this->assertNull( $store->getActiveDeployment() );

		$da = $store->createDeployment()
			->revId( 100 )
			->commit();
		$store->activateDeployment( $da );
		$this->assertSame(
			$da->getId(),
			$store->getActiveDeployment()->getId()
		);

		$da2 = $store->createDeployment()
			->revId( 101 )
			->commit();
		$store->activateDeployment( $da2 );
		$this->assertSame(
			$da2->getId(),
			$store->getActiveDeployment()->getId()
		);
	}

	public function testCreatePackageVersion() {
		$p = $this->getStore()->createPackageVersion()
			->name( 'test' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		$this->assertSame( 1, $p->getId() );
	}

	private function createTwoPackages( ProduntoStore $store ) {
		$packages = [];
		$packages[] = $store->createPackageVersion()
			->name( 'foo' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		$packages[] = $store->createPackageVersion()
			->name( 'bar' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		return $packages;
	}

	public function testGetPackageById() {
		$store = $this->getStore();
		$packages = $this->createTwoPackages( $store );
		$this->assertNull( $store->getPackageById( 100 ) );
		$p = $store->getPackageById( $packages[0]->getId() );
		$this->assertSame( 'foo', $p->getName() );
		$p = $store->getPackageById( $packages[1]->getId() );
		$this->assertSame( 'bar', $p->getName() );
	}

	public function testGetPackageByName() {
		$store = $this->getStore();
		$packages = $this->createTwoPackages( $store );
		$this->assertNull( $store->getPackageByName( 'no', '' ) );
		$p = $store->getPackageByName( 'foo', '1.0' );
		$this->assertSame( $packages[0]->getId(), $p->getId() );
		$p = $store->getPackageByName( 'bar', '1.0' );
		$this->assertSame( $packages[1]->getId(), $p->getId() );
	}

	public static function provideDecodeJson() {
		return [
			[ '', [] ],
			[ '{"a":"b"}', [ 'a' => 'b' ] ]
		];
	}

	public static function provideEncodeJson() {
		foreach ( self::provideDecodeJson() as [ $json, $array ] ) {
			yield [ $array, $json ];
		}
	}

	/**
	 * @dataProvider provideDecodeJson
	 * @param string $input
	 * @param mixed $expected
	 */
	public function testDecodeJson( $input, $expected ) {
		$result = ProduntoStore::decodeJson( $input );
		$this->assertSame( $expected, $result );
	}

	/**
	 * @dataProvider provideEncodeJson
	 * @param mixed $input
	 * @param string $expected
	 */
	public function testEncodeJson( $input, $expected ) {
		$result = ProduntoStore::encodeJson( $input );
		$this->assertSame( $expected, $result );
	}
}

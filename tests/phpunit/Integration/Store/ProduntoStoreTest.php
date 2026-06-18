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

	public function testGetDeploymentById() {
		$store = $this->getStore();

		$this->assertNull(
			$store->getDeploymentById( 1 )
		);

		$da = $store->createDeployment()
			->revId( 100 )
			->commit();
		$id = $da->getId();
		$this->assertSame( $id, $store->getDeploymentById( $id )->getId() );
	}

	public function testGetRecentDeployments() {
		$store = $this->getStore();
		$this->assertSame( [], $store->getRecentDeployments() );

		$p1 = $store->createPackageVersion()
			->name( 'package1' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		$p2 = $store->createPackageVersion()
			->name( 'package2' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		$store->createPackageVersion()
			->name( 'package3' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();

		$store->createDeployment()
			->revId( 100 )
			->commit();
		$store->createDeployment()
			->revId( 100 )
			->addPackage( $p1 )
			->commit();
		$store->createDeployment()
			->revId( 100 )
			->addPackage( $p1 )
			->addPackage( $p2 )
			->commit();

		$deployments = $store->getRecentDeployments( 3 );
		$this->assertSame( 3, $deployments[0]->getId() );
		$this->assertSame( 2, $deployments[1]->getId() );
		$this->assertSame( 1, $deployments[2]->getId() );
		$this->assertCount( 2, $deployments[0]->getPackages() );
		$this->assertCount( 1, $deployments[1]->getPackages() );
		$this->assertCount( 0, $deployments[2]->getPackages() );

		$deployments = $store->getRecentDeployments( 2 );
		$this->assertSame( 3, $deployments[0]->getId() );
		$this->assertSame( 2, $deployments[1]->getId() );

		$deployments = $store->getRecentDeployments( 0 );
		$this->assertSame( [], $deployments );
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

	public function testGetMaxPackageId() {
		$store = $this->getStore();
		$this->assertNull( $store->getMaxPackageId() );

		$this->getStore()->createPackageVersion()
			->name( 'test' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();

		$this->assertSame( 1, $store->getMaxPackageId() );
	}

	public static function provideGetPackagesFromIdRange() {
		return [
			[ 0, 0, [] ],
			[ 0, 1, [ 1 ] ],
			[ 1, 1, [ 1 ] ],
			[ 1, 2, [ 1, 2 ] ],
		];
	}

	/**
	 * @dataProvider provideGetPackagesFromIdRange
	 */
	public function testGetPackagesFromIdRange( int $start, int $end, array $expect ) {
		$store = $this->getStore();
		$this->createTwoPackages( $store );
		$packages = $store->getPackagesFromIdRange( $start, $end );

		$packageIds = [];
		foreach ( $packages as $package ) {
			$packageIds[] = $package->getId();
		}
		$this->assertSame( $expect, $packageIds );
	}

	public static function provideGetPackageStatesFromIdRange() {
		return [
			[ 0, 0, [] ],
			[ 1, 1, [ 1 => ProduntoStore::STATE_READY ] ],
			[ 1, 2, [ 1 => ProduntoStore::STATE_READY, 2 => ProduntoStore::STATE_FETCHING ] ]
		];
	}

	/**
	 * @dataProvider provideGetPackageStatesFromIdRange
	 */
	public function testGetPackageStatesFromIdRange( int $start, int $end, array $expect ) {
		$store = $this->getStore();
		$store->createPackageVersion()
			->name( 'good' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->commit();
		$store->createPackageVersion()
			->name( 'bad' )
			->fetchedUrl( '' )
			->version( '1.0' )
			->suspend();
		$states = $store->getPackageStatesFromIdRange( $start, $end );
		$this->assertSame( $expect, $states );
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

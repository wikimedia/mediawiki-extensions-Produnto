<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\PackageProtectionStore;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\PackageProtectionStore
 * @group Database
 */
class PackageProtectionStoreTest extends \MediaWikiIntegrationTestCase {
	public function testGetProtections() {
		$store = $this->getProtectionStore();
		$user = $this->getTestSysop()->getUser();
		$store->setProtection( $user, 'example', 'sysop', 'infinity', '' );
		$store->setProtection( $user, 'second package', 'reviewer', 'infinity', '' );
		$store->setProtection( $user, 'expired', 'sysop', '20000101000000', '' );
		$this->assertEquals( [
			'example' => [ 'level' => 'sysop', 'expiry' => 'infinity' ],
			'Example' => [ 'level' => 'sysop', 'expiry' => 'infinity' ],
			'second package' => [ 'level' => 'reviewer', 'expiry' => 'infinity' ],
		], $store->getProtections(
			[ 'example', 'Example', 'second package', 'expired', 'unprotected', '[invalid]' ]
		) );
	}

	public function testEmptyBatchDoesNotReadDatabase() {
		$services = $this->getServiceContainer();
		$store = new PackageProtectionStore(
			$services->get( 'Produnto.RepoLinker' ),
			$this->createNoOpMock( IConnectionProvider::class ),
			$services->getRestrictionStore()
		);
		$this->assertSame( [], $store->getProtections( [] ) );
		$this->assertNull( $store->getProtection( '[invalid]' ) );
	}

	public function testProtectionLifecycle() {
		$store = $this->getProtectionStore();
		$user = $this->getTestSysop()->getUser();
		$this->assertNull( $store->getProtection( 'example' ) );
		$store->setProtection( $user, 'example', 'sysop', 'infinity', 'initial' );
		$this->assertSame( [ 'level' => 'sysop', 'expiry' => 'infinity' ], $store->getProtection( 'example' ) );
		$expiry = wfTimestamp( TS_MW, time() + 86400 );
		$store->setProtection( $user, 'example', 'autoconfirmed', $expiry, 'change' );
		$this->assertSame( [ 'level' => 'autoconfirmed', 'expiry' => $expiry ], $store->getProtection( 'example' ) );
		$store->setProtection( $user, 'example', '', '', 'remove' );
		$this->assertNull( $store->getProtection( 'example' ) );
		$logs = $this->getDb()->newSelectQueryBuilder()->select( 'log_action' )->from( 'logging' )
			->where( [ 'log_type' => 'protect', 'log_namespace' => NS_PACKAGE ] )
			->orderBy( 'log_id' )->caller( __METHOD__ )->fetchFieldValues();
		$this->assertSame( [ 'protect', 'modify', 'unprotect' ], $logs );
		$this->assertSame( 0, (int)$this->getDb()->newSelectQueryBuilder()->select( 'COUNT(*)' )->from( 'page' )
			->where( [ 'page_namespace' => NS_PACKAGE ] )->caller( __METHOD__ )->fetchField() );
	}

	private function getProtectionStore(): PackageProtectionStore {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getPackageProtectionStore();
	}
}

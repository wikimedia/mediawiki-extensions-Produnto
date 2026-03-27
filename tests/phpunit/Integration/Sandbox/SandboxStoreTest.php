<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Sandbox;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Sandbox\SandboxStore
 * @covers \MediaWiki\Extension\Produnto\Sandbox\SandboxBuilder
 */
class SandboxStoreTest extends \MediaWikiIntegrationTestCase {
	public function testCreateGetDelete() {
		$services = new ProduntoServices( $this->getServiceContainer() );
		$produntoStore = $services->getStore();
		$sandboxStore = $services->getSandboxStore();

		$text1 = 'text1';
		$text2_1 = 'text2.1';
		$hash1 = hash( 'sha256', $text1 );
		$hash2_1 = hash( 'sha256', $text2_1 );

		$produntoStore->createPackageVersion()
			->name( 'package1' )
			->version( '1.0.0' )
			->fetchedUrl( '' )
			->addFile( 'file1', 'text1' )
			->addFile( 'file2', 'text2' )
			->commit();

		$sandboxStore->createOrUpdate( 1, 'sandbox1' )
			->addFileReference( 'package1', 'file1', $hash1 )
			->addFile( 'package1', 'file2', $hash2_1, $text2_1 )
			->commit();

		$sandbox = $sandboxStore->get( 1, 'sandbox1' );
		$this->assertSame(
			$sandbox->getPackage( 'package1' )->getFileContents( 'file1' ),
			$text1
		);
		$this->assertSame(
			$sandbox->getPackage( 'package1' )->getFileContents( 'file2' ),
			$text2_1
		);

		$this->assertNull( $sandboxStore->get( 2, 'sandbox1' ) );
		$this->assertNull( $sandboxStore->get( 1, 'sandbox2' ) );

		$this->assertSame(
			[ 'sandbox1' ],
			$sandboxStore->getSandboxNames( 1 )
		);
		$this->assertSame( [], $sandboxStore->getSandboxNames( 2 ) );

		$sandboxStore->delete( 1, 'sandbox1' );
		$this->assertNull( $sandboxStore->get( 1, 'sandbox1' ) );
	}

	public function testDeleteAccounting() {
		$sandboxStore = ( new ProduntoServices( $this->getServiceContainer() ) )
			->getSandboxStore();
		$text = str_repeat( 'x', (int)( SandboxStore::MAX_SANDBOX_SIZE * 2 / 5 ) );
		$hash = hash( 'sha256', $text );

		// Add two large sandboxes
		$sandboxStore->createOrUpdate( 1, 'sandbox1' )
			->currentUnixTime( 1 )
			->addFile( 'package1', 'file1', $hash, $text )
			->commit();
		$sandboxStore->createOrUpdate( 1, 'sandbox2' )
			->currentUnixTime( 2 )
			->addFile( 'package1', 'file1', $hash, $text )
			->commit();
		$this->assertSame( [ 'sandbox1', 'sandbox2' ], $sandboxStore->getSandboxNames( 1 ) );

		// Adding a third will cause the first to be evicted
		$sandboxStore->createOrUpdate( 1, 'sandbox3' )
			->currentUnixTime( 3 )
			->addFile( 'package1', 'file1', $hash, $text )
			->commit();
		$this->assertSame( [ 'sandbox2', 'sandbox3' ], $sandboxStore->getSandboxNames( 1 ) );

		// Deleting sandbox3 leaves room for sandbox4
		$sandboxStore->delete( 1, 'sandbox3' );
		$sandboxStore->createOrUpdate( 1, 'sandbox4' )
			->currentUnixTime( 4 )
			->addFile( 'package1', 'file1', $hash, $text )
			->commit();
		$this->assertSame( [ 'sandbox2', 'sandbox4' ], $sandboxStore->getSandboxNames( 1 ) );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\VersionAlreadyExistsError;
use MediaWiki\Extension\Produnto\Store\WrongUrlError;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\PackageBuilder
 * @covers \MediaWiki\Extension\Produnto\Store\PackageAccess::getFileContents
 * @covers \MediaWiki\Extension\Produnto\Store\ProduntoStore::createPackageVersion
 * @covers \MediaWiki\Extension\Produnto\Store\ProduntoStore::resumePackageBuilder
 * @group Database
 */
class PackageBuilderTest extends \MediaWikiIntegrationTestCase {
	public function testSingleStageCreate() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$this->addFiles( $builder );
		$package = $builder->commit();

		// Test internal state
		$this->assertFields( $package, ProduntoStore::STATE_READY );
		$this->assertFiles( $package );

		// Test reload from DB
		$package = $store->getPackageById( 1 );
		$this->assertFields( $package, ProduntoStore::STATE_READY );
		$this->assertFiles( $package );
	}

	public function testTwoStageCreate() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$package = $builder->suspend();

		$this->assertFields( $package, ProduntoStore::STATE_FETCHING );
		$package = $store->getPackageById( 1 );
		$this->assertFields( $package, ProduntoStore::STATE_FETCHING );

		$builder = $store->resumePackageBuilder( $package );
		$this->addFiles( $builder );
		$package = $builder->commit();

		$this->assertFields( $package, ProduntoStore::STATE_READY );
		$this->assertFiles( $package );

		$package = $store->getPackageById( 1 );
		$this->assertFields( $package, ProduntoStore::STATE_READY );
		$this->assertFiles( $package );
	}

	public function testTwoVersions() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$this->addFiles( $builder );
		$p1 = $builder->commit();
		$this->assertSame( 1, $p1->getId() );

		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$builder->version( '2.0.0' );
		$p2 = $builder->commit();
		$this->assertSame( 2, $p2->getId() );
		$this->assertSame( '2.0.0', $p2->getVersion() );
		$this->assertNull( $p2->getFileContents( 'README.md' ) );
	}

	public function testVersionAlreadyExists() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$builder->commit();

		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$this->expectException( VersionAlreadyExistsError::class );
		$builder->commit();
	}

	public function testWrongUrl() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$builder->commit();

		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$builder->fetchedUrl( 'http://example.com/bar' );
		$this->expectException( WrongUrlError::class );
		$builder->commit();
	}

	public function testFail() {
		$store = $this->getStore();
		$builder = $store->createPackageVersion();
		$this->setFields( $builder );
		$package = $builder->suspend();

		$builder = $store->resumePackageBuilder( $package );
		$builder->fail( \StatusValue::newFatal( 'failed' ) );

		$package = $store->getPackageById( $package->getId() );
		$status = $package->getStatus();
		$this->assertStatusError( 'failed', $status );
	}

	private function getStore(): ProduntoStore {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
	}

	private function setFields( PackageBuilder $builder ) {
		$builder->name( 'foo' )
			->version( '1.0.0' )
			->upstreamRef( 'refs/tags/v1.0.0' )
			->fetchedUrl( 'http://example.com/foo' )
			->type( 'test' )
			->homepageUrl( 'http://example.com/foo/home' )
			->docUrl( 'http://example.com/foo/doc' )
			->collabUrl( 'http://example.com/foo/collab' )
			->issueUrl( 'http://example.com/foo/issue' )
			->description( 'en', 'Package description' )
			->localName( 'en', 'Foo' )
			->author( 'Alice' )
			->author( 'Bob' )
			->license( 'CC0-1.0' )
			->requires( 'bar', '>1.0.0' )
			->module( 'foo', 'src/init.lua' );
	}

	private function addFiles( PackageBuilder $builder ) {
		$builder->addFile( 'README.md', 'Some documentation' )
			->addFile( 'empty', '' )
			->addFile( 'src/test.lua', 'return {}' );
	}

	private function assertFields( PackageAccess $package, $expectedState ) {
		$this->assertSame( 'foo', $package->getName() );
		$this->assertSame( '1.0.0', $package->getVersion() );
		$this->assertSame( 'refs/tags/v1.0.0', $package->getUpstreamRef() );
		$this->assertSame( 'http://example.com/foo/home', $package->getHomepageUrl() );
		$this->assertSame( 'http://example.com/foo', $package->getFetchedUrl() );
		$this->assertSame( 'test', $package->getType() );
		$this->assertSame( 'http://example.com/foo/doc', $package->getDocUrl() );
		$this->assertSame( 'http://example.com/foo/collab', $package->getCollabUrl() );
		$this->assertSame( 'http://example.com/foo/issue', $package->getIssueUrl() );
		$this->assertSame( 'Package description', $package->getDescription( 'en' ) );
		$this->assertSame( 'Foo', $package->getLocalName( 'en' ) );
		$this->assertSame( [ 'Alice', 'Bob' ], $package->getAuthors() );
		$this->assertSame( 'CC0-1.0', $package->getLicense() );
		$this->assertSame( [ 'bar' => '>1.0.0' ], $package->getRequires() );
		$this->assertSame( [ 'foo' => 'src/init.lua' ], $package->getModules() );
		$this->assertSame( 1, $package->getId() );
		$this->assertSame( $expectedState, $package->getState() );
	}

	private function assertFiles( PackageAccess $package ) {
		$this->assertSame( 'Some documentation', $package->getFileContents( 'README.md' ) );
		$this->assertSame( '', $package->getFileContents( 'empty' ) );
		$this->assertSame( 'return {}', $package->getFileContents( 'src/test.lua' ) );
		$this->assertNull( $package->getFileContents( 'nonexistent' ) );

		$this->assertTrue( $package->hasFile( 'README.md' ) );
		$this->assertFalse( $package->hasFile( 'nonexistent' ) );
	}
}

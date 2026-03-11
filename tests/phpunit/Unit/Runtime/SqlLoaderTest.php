<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Runtime;

use MediaWiki\Extension\Produnto\Runtime\SqlLoader;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \MediaWiki\Extension\Produnto\Runtime\SqlLoader
 */
class SqlLoaderTest extends \MediaWikiUnitTestCase {
	public function testNoDeployment() {
		$store = $this->createMock( ProduntoStore::class );
		$store->method( 'getActiveDeployment' )->willReturn( null );
		$loader = new SqlLoader( $store );
		$this->assertFalse( $loader->hasPackage( 'a' ) );
		$this->assertNull( $loader->getModuleInfo( 'a' ) );
		$this->assertNull( $loader->getFileContents( 'a', 'init.lua' ) );
	}

	private function getLoader(): SqlLoader {
		$fileAccess = new SimpleFileAccess( [ 1 => [
			'init.lua' => 'return "hello"'
		] ] );
		$package = new PackageAccess(
			$fileAccess, 1, 'a', '1.0', '', '', [],
			ProduntoStore::STATE_READY, null
		);
		$deployment = new DeploymentAccess(
			$fileAccess,
			$this->createNoOpMock( IReadableDatabase::class ),
			1,
			[ 'modules' => [ 'foo' => [ 1, 'init.lua' ] ] ],
			[ 1 => $package ]
		);
		$store = $this->createMock( ProduntoStore::class );
		$store->method( 'getActiveDeployment' )->willReturn( $deployment );
		return new SqlLoader( $store );
	}

	public function testHasPackage() {
		$loader = $this->getLoader();
		$this->assertTrue( $loader->hasPackage( 'a' ) );
		$this->assertFalse( $loader->hasPackage( 'c' ) );
	}

	public function testGetModuleInfo() {
		$loader = $this->getLoader();
		$info = $loader->getModuleInfo( 'foo' );
		$this->assertSame( 'init.lua', $info->path );
		$this->assertNull( $loader->getModuleInfo( 'no' ) );
	}

	public function testGetFileContents() {
		$loader = $this->getLoader();
		$this->assertSame(
			'return "hello"',
			$loader->getFileContents( 'a', 'init.lua' )
		);
		$this->assertNull( $loader->getFileContents( 'a', 'no' ) );
		$this->assertNull( $loader->getFileContents( 'c', 'init.lua' ) );
	}

}

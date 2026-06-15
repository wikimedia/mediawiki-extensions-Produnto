<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\RepoViewer;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Linker\LinkTarget;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\RepoLinker
 */
class RepoLinkerTest extends \MediaWikiIntegrationTestCase {
	public static function provideGetPackageLinkTarget() {
		return [
			'invalid package name' => [
				'{{package',
				null
			],
			'case folding' => [
				'package',
				'Package'
			],
			'uppercase name' => [
				'Package',
				'Package'
			]
		];
	}

	/**
	 * @dataProvider provideGetPackageLinkTarget
	 */
	public function testGetPackageLinkTarget( string $packageName, ?string $expected ) {
		$linker = ( new ProduntoServices( $this->getServiceContainer() ) )->getRepoLinker();
		$result = $linker->getPackageLinkTarget( $packageName );
		if ( $expected === null ) {
			$this->assertNull( $result );
		} else {
			$this->assertInstanceOf( LinkTarget::class, $result );
			$this->assertSame( NS_PACKAGE, $result->getNamespace() );
			$this->assertSame( $expected, $result->getDBkey() );
		}
	}

	public static function provideGetFileLinkTarget() {
		return [
			'invalid package name' => [
				'{{package',
				'path',
				null
			],
			'typical readable link' => [
				'package',
				'path',
				'Package/path'
			],
			'fallback due to whitespace normalization' => [
				'package',
				'path__1',
				'Package//b91e0799ecf5800d'
			],
			'fallback due to invalid title' => [
				'package',
				'{{path/a',
				'Package//a144a61c6d8ef9a0'
			],
		];
	}

	/**
	 * @dataProvider provideGetFileLinkTarget
	 */
	public function testGetFileLinkTarget( string $packageName, string $path, ?string $expected ) {
		$linker = ( new ProduntoServices( $this->getServiceContainer() ) )->getRepoLinker();
		$result = $linker->getFileLinkTarget( $packageName, $path );
		if ( $expected === null ) {
			$this->assertNull( $result );
		} else {
			$this->assertInstanceOf( LinkTarget::class, $result );
			$this->assertSame( NS_PACKAGE, $result->getNamespace() );
			$this->assertSame( $expected, $result->getDBkey() );
		}
	}

	public static function provideGetPathFromFallback() {
		return [
			'path' => [
				'a0af9f865bf637e6',
				null
			],
			'path__1' => [
				'b91e0799ecf5800d',
				'path__1',
			],
			'{{path/a' => [
				'a144a61c6d8ef9a0',
				'{{path/a'
			],
			'nonexistent hash' => [
				'nonexistent',
				null
			],
		];
	}

	/**
	 * @dataProvider provideGetPathFromFallback
	 */
	public function testGetPathFromFallback( string $hash, ?string $expected ) {
		$linker = ( new ProduntoServices( $this->getServiceContainer() ) )->getRepoLinker();
		$files = [
			'path',
			'path__1',
			'{{path/a'
		];
		$package = $this->createMock( PackageAccess::class );
		$package->method( 'getFilePaths' )
			->willReturn( $files );
		$package->method( 'getName' )
			->willReturn( 'package' );
		$result = $linker->getPathFromFallback( $package, $hash );
		$this->assertSame( $expected, $result );
	}
}

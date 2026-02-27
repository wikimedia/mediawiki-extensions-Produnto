<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Store;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Language\LanguageFallback;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\PackageAccess
 */
class PackageAccessTest extends \MediaWikiUnitTestCase {
	private function newPackage() {
		return new PackageAccess(
			new SimpleFileAccess( [ 1 => [
				'init.lua' => 'return {}'
			] ] ),
			1,
			'foo',
			'1.0.0',
			'refs/tags/v1.0.0',
			'http://example.com/foo',
			[
				'type' => 'test',
				'name' => [
					'en' => 'Test',
				],
				'homepage-url' => 'http://http://example.com/homepage/foo',
				'doc-url' => 'http://example.com/doc/foo',
				'collab-url' => 'http://example.com/collab/foo',
				'issue-url' => 'http://example.com/issue/foo',
				'description' => [
					'en' => 'A test package',
				],
				'authors' => [
					'Alice',
				],
				'license' => 'GPL-3.0-or-later',
				'requires' => [
					'MediaWiki' => '>1.0.0',
				],
				'modules' => [
					'test.foo' => 'src/foo.lua',
				],
			],
			2,
			null
		);
	}

	public function testGetId() {
		$this->assertSame( 1, $this->newPackage()->getId() );
	}

	public function testGetName() {
		$this->assertSame( 'foo', $this->newPackage()->getName() );
	}

	public function testGetVersion() {
		$this->assertSame( '1.0.0', $this->newPackage()->getVersion() );
	}

	public function testGetUpstreamRef() {
		$this->assertSame( 'refs/tags/v1.0.0', $this->newPackage()->getUpstreamRef() );
	}

	public function testGetFetchedUrl() {
		$this->assertSame( 'http://example.com/foo', $this->newPackage()->getFetchedUrl() );
	}

	public function testGetType() {
		$this->assertSame( 'test', $this->newPackage()->getType() );
	}

	public function testGetHomepageUrl() {
		$this->assertSame( 'http://http://example.com/homepage/foo', $this->newPackage()->getHomepageUrl() );
	}

	public function testGetDocUrl() {
		$this->assertSame( 'http://example.com/doc/foo', $this->newPackage()->getDocUrl() );
	}

	public function testGetCollabUrl() {
		$this->assertSame( 'http://example.com/collab/foo', $this->newPackage()->getCollabUrl() );
	}

	public function testGetIssueUrl() {
		$this->assertSame( 'http://example.com/issue/foo', $this->newPackage()->getIssueUrl() );
	}

	public function testGetLocalName() {
		$package = $this->newPackage();
		$mockFallback = $this->createMock( LanguageFallback::class );
		$mockFallback->method( 'getAll' )->willReturn( [ 'en' ] );
		$this->assertSame( 'Test', $package->getLocalName( 'en', $mockFallback ) );
		$this->assertSame( 'Test', $package->getLocalName( 'fr', $mockFallback ) );
		$this->assertSame( 'foo', $package->getLocalName( 'fr', null ) );
	}

	public function testGetDescription() {
		$package = $this->newPackage();
		$mockFallback = $this->createMock( LanguageFallback::class );
		$mockFallback->method( 'getAll' )->willReturn( [ 'en' ] );
		$this->assertSame( 'A test package', $package->getDescription( 'en', $mockFallback ) );
		$this->assertSame( 'A test package', $package->getDescription( 'fr', $mockFallback ) );
		$this->assertNull( $package->getDescription( 'fr', null ) );
	}

	public function testGetAuthors() {
		$this->assertSame( [ 'Alice' ], $this->newPackage()->getAuthors() );
	}

	public function testGetLicense() {
		$this->assertSame( 'GPL-3.0-or-later', $this->newPackage()->getLicense() );
	}

	public function testGetRequires() {
		$this->assertSame( [ 'MediaWiki' => '>1.0.0' ], $this->newPackage()->getRequires() );
	}

	public function testGetModules() {
		$this->assertSame( [ 'test.foo' => 'src/foo.lua' ], $this->newPackage()->getModules() );
	}

	public function testGetState() {
		$this->assertSame( 2, $this->newPackage()->getState() );
	}

	public function testGetFileContents() {
		$package = $this->newPackage();
		$this->assertSame( 'return {}', $package->getFileContents( 'init.lua' ) );
		$this->assertNull( $package->getFileContents( 'nonexistent' ) );
	}

	public function testHasFile() {
		$package = $this->newPackage();
		$this->assertTrue( $package->hasFile( 'init.lua' ) );
		$this->assertFalse( $package->hasFile( 'nonexistent' ) );
	}
}

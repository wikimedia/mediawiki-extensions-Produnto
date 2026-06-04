<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Store;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\PackageAccess
 */
class PackageAccessTest extends \MediaWikiUnitTestCase {
	private function newPackage( array $extraFiles = [] ) {
		return new PackageAccess(
			new SimpleFileAccess( [
				1 => [
					'init.lua' => 'return {}'
				] + $extraFiles
			] ),
			1,
			'foo',
			'1.0.0',
			'refs/tags/v1.0.0',
			'http://example.com/foo',
			[],
			2,
			null
		);
	}

	public function testGetId() {
		$this->assertSame( 1, $this->newPackage()->getId() );
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

	public function testGetFileHashes() {
		$package = $this->newPackage();
		$this->assertSame(
			[ 'init.lua' => hash( 'sha256', 'return {}' ) ],
			$package->getFileHashes()
		);
	}

	private function provideGetReadmePath() {
		return [
			'empty' => [ [], null ],
			'README.md' => [ [ 'README.md' => '' ], 'README.md' ],
			'README.wiki' => [
				[
					'README.md' => '...',
					'README.wiki' => '...'
				],
				'README.wiki'
			]
		];
	}

	/**
	 * @dataProvider provideGetReadmePath
	 * @param array<string,string> $extraFiles
	 * @param string|null $expected
	 */
	public function testGetReadmePath( $extraFiles, $expected ) {
		$package = $this->newPackage( $extraFiles );
		$this->assertSame( $expected, $package->getReadmePath() );
	}
}

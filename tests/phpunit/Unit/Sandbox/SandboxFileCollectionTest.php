<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Sandbox;

use MediaWiki\Extension\Produnto\Sandbox\SandboxFileCollection;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Sandbox\SandboxFileCollection
 */
class SandboxFileCollectionTest extends \MediaWikiUnitTestCase {
	public static function provideGetFileContents() {
		return [
			[ 'file1', 'text1' ],
			[ 'file2', 'text2' ],
			[ 'nonexistent', null ]
		];
	}

	/**
	 * @dataProvider provideGetFileContents
	 * @param string $path
	 * @param ?string $expected
	 */
	public function testGetFileContents( $path, $expected ) {
		$collection = $this->getSandboxFileCollection();
		$this->assertSame( $expected, $collection->getFileContents( $path ) );
		if ( $expected === null ) {
			$this->assertFalse( $collection->hasFile( $path ) );
		} else {
			$this->assertTrue( $collection->hasFile( $path ) );
		}
	}

	private function getSandboxFileCollection() {
		$text1 = 'text1';
		$text2 = 'text2';
		$hash1 = hash( 'sha256', $text1 );
		$hash2 = hash( 'sha256', $text2 );
		$fileAccess = new SimpleFileAccess( [], [ $hash1 => $text1 ] );
		return new SandboxFileCollection(
			$fileAccess,
			[
				'file1' => $hash1,
				'file2' => $hash2,
			],
			[ $hash2 => $text2 ]
		);
	}
}

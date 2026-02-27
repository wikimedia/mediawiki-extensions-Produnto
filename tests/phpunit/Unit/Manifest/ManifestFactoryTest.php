<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Manifest;

use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Manifest\ManifestFactory
 */
class ManifestFactoryTest extends \MediaWikiUnitTestCase {

	public static function provideParseManifest() {
		return [
			'no manifest' => [ [], 'produnto-fetch-no-manifest' ],
			'valid produnto manifest' => [
				[ 'produnto.json' => '{}' ],
				null
			],
		];
	}

	/**
	 * @dataProvider provideParseManifest
	 * @param string[] $files
	 * @param string|null $error
	 */
	public function testParseManifest( $files, $error ) {
		$package = new PackageAccess(
			new SimpleFileAccess( [ 1 => $files ] ),
			1,
			'test',
			'1.0.0',
			'',
			'http://example.com',
			[],
			ProduntoStore::STATE_READY,
			null
		);
		$manifestFactory = new ManifestFactory();
		$status = $manifestFactory->parseManifest( $package );
		if ( $error ) {
			$this->assertStatusError( $error, $status );
		} else {
			$this->assertStatusGood( $status );
		}
	}
}

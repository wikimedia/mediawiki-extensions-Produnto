<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Manifest;

use MediaWiki\Extension\Produnto\Manifest\ProduntoJsonManifest;
use MediaWiki\Extension\Produnto\Manifest\ProduntoJsonManifestParser;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Manifest\ProduntoJsonManifestParser
 */
class ProduntoJsonManifestParserTest extends \MediaWikiUnitTestCase {
	private function createPackage( ?string $manifest, array $files = [] ) {
		if ( $manifest !== null ) {
			$files['produnto.json'] = $manifest;
		}
		return new PackageAccess(
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
	}

	private function createParser() {
		return new ProduntoJsonManifestParser();
	}

	public static function provideHasManifest() {
		return [
			'no manifest' => [ null, false ],
			'has manifest' => [ '{}', true ],
		];
	}

	/**
	 * @dataProvider provideHasManifest
	 * @param string|null $manifest
	 * @param bool $expected
	 */
	public function testHasManifest( $manifest, $expected ) {
		$package = $this->createPackage( $manifest );
		$parser = $this->createParser();
		$result = $parser->hasManifest( $package );
		$this->assertSame( $expected, $result );
	}

	public static function provideParse() {
		return [
			'invalid JSON' => [
				'{',
				'produnto-fetch-manifest'
			],
			'schema failure' => [
				'{ "type": [] }',
				'produnto-fetch-manifest-schema'
			],
			'module failure' => [
				'{ "modules": { "foo": "foo.lua" } }',
				'produnto-fetch-module-missing'
			],
			'success with module' => [
				'{ "modules": { "foo": "foo.lua" } }',
				null,
				[ 'foo.lua' => '' ],
			]
		];
	}

	/**
	 * @dataProvider provideParse
	 * @param string $manifest
	 * @param string|null $error
	 * @param string<string,string> $extraFiles
	 */
	public function testParse( $manifest, $error, $extraFiles = [] ) {
		$package = $this->createPackage( $manifest, $extraFiles );
		$parser = $this->createParser();
		$status = $parser->parse( $package );
		if ( $error ) {
			$this->assertStatusError( $error, $status );
		} else {
			$this->assertStatusGood( $status );
			$result = $status->value;
			$this->assertInstanceOf( ProduntoJsonManifest::class, $result );
			$this->assertEquals( new ProduntoJsonManifest( json_decode( $manifest ) ), $result );
		}
	}
}

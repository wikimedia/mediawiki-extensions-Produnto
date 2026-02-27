<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Manifest;

use MediaWiki\Extension\Produnto\Manifest\ProduntoJsonManifestParser;
use MediaWiki\Extension\Produnto\Store\NameStore;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Extension\Produnto\Store\TextStore;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Produnto\Manifest\ProduntoJsonManifest
 */
class ProduntoJsonManifestTest extends \MediaWikiUnitTestCase {
	public function testPopulateProps() {
		$json = <<<JSON
{
	"type": "scribunto",
	"name": { "en": "Test" },
	"description": { "en": "Test" },
	"author": [ "Alice", "Bob" ],
	"license": "PDHPE",
	"url": "http://example.com/home",
	"collab-url": "http://example.com/collab",
	"doc-url": "http://example.com/doc",
	"issue-url": "http://example.com/issue",
	"requires": {
		"core": "1.0.0"
	},
	"modules": {
		"test": "init.lua"
	}
}
JSON;

		$renames = [
			// The internal property is always an array
			'author' => 'authors',
			// Clarify what sort of URL, but in the manifest we'll stay consistent with extension.json
			'url' => 'homepage-url'
		];
		$expected = json_decode( $json, true );
		foreach ( $renames as $old => $new ) {
			$expected[$new] = $expected[$old];
			unset( $expected[$old] );
		}

		$manifestParser = new ProduntoJsonManifestParser();
		$status = $manifestParser->parse(
			new PackageAccess(
				new SimpleFileAccess( [ 1 => [
					'produnto.json' => $json,
					'init.lua' => '',
				] ] ),
				1, '', '', '', '', [], 0, null
			)
		);
		$this->assertStatusGood( $status );
		$builder = new PackageBuilder(
			$this->createNoOpMock( TextStore::class ),
			new SimpleFileAccess(),
			$this->createNoOpMock( NameStore::class ),
			$this->createNoOpMock( IDatabase::class )
		);
		$status->value->populateProps( $builder );
		$result = TestingAccessWrapper::newFromObject( $builder )->props;
		$this->assertEquals( $expected, $result );
	}
}

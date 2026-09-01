<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Runtime;

use MediaWiki\Extension\Produnto\RepoViewer\RepoLinker;
use MediaWiki\Extension\Produnto\Runtime\ModuleInfo;
use MediaWiki\Extension\Produnto\Runtime\ProduntoRuntime;
use MediaWiki\Extension\Produnto\Runtime\SimpleLoader;
use MediaWiki\Language\Language;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;

/**
 * @covers \MediaWiki\Extension\Produnto\Runtime\ProduntoRuntime
 * @covers \MediaWiki\Extension\Produnto\Runtime\SimpleLoader
 */
class ProduntoRuntimeTest extends \MediaWikiUnitTestCase {
	public function testGetModuleInfo() {
		$infoA1 = new ModuleInfo( 'a', 'init.lua', 'return "loader1"' );
		$infoA2 = new ModuleInfo( 'a', 'init.lua', 'return "loader2"' );
		$infoB2 = new ModuleInfo( 'b', 'init.lua', 'return "loader2"' );
		$loader1 = new SimpleLoader( [], [ 'a' => $infoA1 ] );
		$loader2 = new SimpleLoader( [], [ 'a' => $infoA2, 'b' => $infoB2 ] );
		$runtime = new ProduntoRuntime( $this->getContLang(), $this->getLinker(),
			[ $loader1, $loader2 ] );
		$this->assertSame( $infoA1, $runtime->getModuleInfo( 'a' ) );
		$this->assertSame( $infoB2, $runtime->getModuleInfo( 'b' ) );
		$this->assertNull( $runtime->getModuleInfo( 'no' ) );
	}

	public static function provideGetFileInfoByTitle() {
		$s = 'return "foo"';
		return [
			'found' => [ 'Package:foo/init.lua', 'foo', 'init.lua', $s ],
			'initial case fold' => [ 'Package:Foo/init.lua', 'foo', 'init.lua', $s ],
			'no subsequent case fold' => [ 'Package:FOO/init.lua' ],
			'regex mismatch' => [ 'foo/init.lua' ],
			'wrong namespace' => [ 'Foo:foo/init.lua' ],
			'no such package' => [ 'Package:bar/init.lua' ],
		];
	}

	/**
	 * @dataProvider provideGetFileInfoByTitle
	 */
	public function testGetFileInfoByTitle(
		string $titleText,
		?string $expectedPackage = null,
		?string $expectedPath = null,
		?string $expectedContents = null
	) {
		$loader = new SimpleLoader(
			[ 'foo' => [ 'init.lua' => 'return "foo"' ] ],
			[]
		);
		$runtime = new ProduntoRuntime( $this->getContLang(), $this->getLinker(),
			[ $loader ] );
		$info = $runtime->getFileInfoByTitle( $titleText );
		if ( $expectedPackage === null ) {
			$this->assertNull( $info );
		} else {
			$this->assertSame( $expectedPackage, $info->packageName );
			$this->assertSame( $expectedPath, $info->path );
			$this->assertSame( $expectedContents, $info->contents );
		}
	}

	public function testGetFileContents() {
		$loader1 = new SimpleLoader(
			[ 'a' => [ 'init.lua' => 'return "loader1"' ] ],
			[]
		);
		$loader2 = new SimpleLoader(
			[
				'a' => [ 'init.lua' => 'return "loader2"' ],
				'b' => [ 'init.lua' => 'return "loader2"' ],
			],
			[]
		);
		$runtime = new ProduntoRuntime( $this->getContLang(), $this->getLinker(),
			[ $loader1, $loader2 ] );
		$this->assertSame( 'return "loader1"', $runtime->getFileContents( 'a', 'init.lua' ) );
		$this->assertSame( 'return "loader2"', $runtime->getFileContents( 'b', 'init.lua' ) );
		$this->assertNull( $runtime->getFileContents( 'a', 'no' ) );
		$this->assertNull( $runtime->getFileContents( 'c', 'no' ) );
	}

	public function testMaybeAddDependency() {
		$info = new ModuleInfo( 'a', 'init.lua', 'return "loader"' );
		$loader = new SimpleLoader( [], [ 'a' => $info ] );
		$runtime = new ProduntoRuntime( $this->getContLang(), $this->getLinker(), [ $loader ] );
		$parserOutput = new ParserOutput( '' );
		$runtime->maybeAddDependency( $parserOutput, 'a', 'init.lua' );
		$result = $parserOutput->getLinkList( ParserOutputLinkTypes::TEMPLATE );
		$this->assertCount( 1, $result );
		$this->assertSame( NS_PACKAGE, $result[0]['link']->getNamespace() );
		$this->assertSame( 'A/init.lua', $result[0]['link']->getDBkey() );
	}

	private function getLinker() {
		$titleParser = $this->createMock( TitleParser::class );
		$titleParser->method( 'makeTitleValueSafe' )->willReturnCallback(
			static function ( $namespace, $text, $fragment = '', $interwiki = '' ) {
				return new TitleValue( $namespace, ucfirst( $text ) );
			}
		);
		return new RepoLinker( $titleParser );
	}

	private function getContLang() {
		$lang = $this->createMock( Language::class );
		$lang->method( 'lcfirst' )->willReturnCallback( static fn ( $s ) => lcfirst( $s ) );
		$lang->method( 'getNsIndex' )->willReturnCallback(
			static fn ( $s ) => strtolower( $s ) === 'package' ? NS_PACKAGE : false
		);
		return $lang;
	}
}

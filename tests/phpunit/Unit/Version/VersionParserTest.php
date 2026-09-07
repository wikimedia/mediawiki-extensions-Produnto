<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Version;

use MediaWiki\Extension\Produnto\Version\Version;
use MediaWiki\Extension\Produnto\Version\VersionParser;
use MediaWiki\Extension\Produnto\Version\VersionParserError;

/**
 * @covers \MediaWiki\Extension\Produnto\Version\VersionParser
 * @covers \MediaWiki\Extension\Produnto\Version\Version
 */
class VersionParserTest extends \MediaWikiUnitTestCase {
	public static function provideCompare() {
		return [
			'single less' => [ '1', '2', -1 ],
			'single equal' => [ '1', '1', 0 ],
			'single greater' => [ '2', '1', 1 ],
			'single with revision less' => [ '1-1', '1-2', -1 ],
			'single with revision greater' => [ '1-2', '1-1', 1 ],
			'single with revision irrelevant' => [ '2-1', '1-2', 1 ],
			'dev-scm' => [ '1.0.0-dev1', '1.0.0-scm1', 1 ],
			'scm-cvs' => [ '1.0.0-scm1', '1.0.0-cvs1', 1 ],
			'cvs-rc' => [ '1.0.0-cvs1', '1.0.0-rc1', 1 ],
			'rc-pre' => [ '1.0.0-rc1', '1.0.0-pre1', 1 ],
			'pre-beta' => [ '1.0.0-pre1', '1.0.0-beta1', 1 ],
			'beta-alpha' => [ '1.0.0-beta1', '1.0.0-alpha1', 1 ],
			'foo-bar' => [ '1.0.foo', '1.0.bar', 1 ],
			'double 0-1' => [ '1.0', '1.1', -1 ],
			'double major precedence' => [ '2.0', '1.1', 1 ],
			'triple 0-1' => [ '1.0.0', '1.0.1', -1 ],
			'triple minor precedence' => [ '1.1.0', '1.0.1', 1 ],
			'triple major precedence' => [ '2.0.0', '1.0.1', 1 ],
			'two hyphens' => [ '1.0.0-alpha1-1', '1.0.0-alpha2-1', -1 ],
			'WMF release train' => [ '1.47.0-wmf.17', '1.47.0-wmf.18', -1 ],
			'WMF release major' => [ '1.47.0-wmf.17', '1.48.0-wmf.1', -1 ],
		];
	}

	/**
	 * @dataProvider provideCompare
	 */
	public function testCompare( string $s1, string $s2, int $expected ) {
		$versionParser = new VersionParser();
		$v1 = $versionParser->parse( $s1 );
		$v2 = $versionParser->parse( $s2 );
		$result = Version::compare( $v1, $v2 );
		$this->assertSame( $expected, $result );
	}

	public static function provideParseError() {
		return [
			[ '' ],
			[ '!!1@1' ],
			[ '1.!2.3' ],
			[ '1.2.3!' ],
		];
	}

	/**
	 * @dataProvider provideParseError
	 */
	public function testParseError( string $s ) {
		$versionParser = new VersionParser();
		$this->expectException( VersionParserError::class );
		$versionParser->parse( $s );
	}

	public static function provideIsPartialMatch() {
		return [
			[ '1', '1.0.0', true ],
			[ '1.0', '1.0.0', true ],
			[ '1.0.0', '1.0.0', true ],
			[ '1.0.0', '1', false ],
			[ '2', '1.0.0', false ],
			[ '1.0.1', '1.0.0', false ],
		];
	}

	/**
	 * @dataProvider provideIsPartialMatch
	 */
	public function testIsPartialMatch( string $s1, string $s2, bool $expected ) {
		$versionParser = new VersionParser();
		$v1 = $versionParser->parse( $s1 );
		$v2 = $versionParser->parse( $s2 );
		$result = Version::isPartialMatch( $v1, $v2 );
		$this->assertSame( $expected, $result );
	}
}

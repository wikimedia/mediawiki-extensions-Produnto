<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Server;

use MediaWiki\Extension\Produnto\Server\GitlabServer;
use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Produnto\Server\GitlabServer
 */
class GitlabServerTest extends \MediaWikiUnitTestCase {
	public static function provideUrlToName() {
		return [
			'not under base' => [
				'http://example.com',
				[ '' ],
				'http://example.org/foo',
				null
			],
			'not under base due to implied slash' => [
				'http://example.com',
				[ '' ],
				'http://example.comma/foo',
				null
			],
			'match with implied slash' => [
				'http://example.com',
				[ '' ],
				'http://example.com/foo',
				'foo',
			],
			'match with explicit slash' => [
				'http://example.com/',
				[ '' ],
				'http://example.com/foo',
				'foo'
			],
			'match with prefix with implied slash' => [
				'http://example.com',
				[ 'foo' ],
				'http://example.com/foo/bar',
				'bar',
			],
			'non-match due to prefix with implied slash' => [
				'http://example.com',
				[ 'foo' ],
				'http://example.com/foobar',
				null,
			],
			'match with prefix with explicit slash' => [
				'http://example.com',
				[ 'foo/' ],
				'http://example.com/foo/bar',
				'bar'
			]
		];
	}

	/**
	 * @dataProvider provideUrlToName
	 * @param string $baseUrl
	 * @param string[] $prefixes
	 * @param string $testUrl
	 * @param ?string $expected
	 */
	public function testUrlToName( $baseUrl, $prefixes, $testUrl, $expected ) {
		$http = $this->createNoOpMock( HttpRequestFactory::class );
		$server = new GitlabServer(
			$http,
			[
				'type' => 'gitlab',
				'url' => $baseUrl,
				'projectPrefixes' => $prefixes
			]
		);

		$has = $server->hasUrl( $testUrl );
		$this->assertSame( $expected !== null, $has );
		$actual = $server->urlToName( $testUrl );
		$this->assertSame( $expected, $actual );
	}

	public static function provideStripInitialPathSegment() {
		return [
			[ '', null ],
			[ 'foo', null ],
			[ 'foo/', null ],
			[ 'foo/bar', 'bar' ],
			[ 'foo/bar/baz', 'bar/baz' ]
		];
	}

	/**
	 * @dataProvider provideStripInitialPathSegment
	 * @param string $path
	 * @param ?string $expected
	 */
	public function testStripInitialPathSegment( $path, $expected ) {
		$server = new GitlabServer(
			$this->createNoOpMock( HttpRequestFactory::class ),
			[ 'type' => 'gitlab', 'url' => '', 'projectPrefixes' => [] ]
		);
		/** @var GitlabServer $testServer */
		$testServer = TestingAccessWrapper::newFromObject( $server );
		$result = $testServer->stripInitialPathSegment( $path );
		$this->assertSame( $expected, $result );
	}
}

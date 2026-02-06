<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Server;

use MediaWiki\Extension\Produnto\Server\GitlabServer;
use MediaWiki\Http\HttpRequestFactory;

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
		$actual = $server->urlToName( $testUrl );
		$this->assertSame( $expected, $actual );
	}
}

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
			$this->makeFakeResolver(),
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
			$this->makeFakeResolver(),
			[ 'type' => 'gitlab', 'url' => '', 'projectPrefixes' => [] ]
		);
		/** @var GitlabServer $testServer */
		$testServer = TestingAccessWrapper::newFromObject( $server );
		$result = $testServer->stripInitialPathSegment( $path );
		$this->assertSame( $expected, $result );
	}

	public static function provideIsWebhookIp() {
		yield from self::provideIsWebhookIpViaDns();
		yield from [
			'IPv4 network allow' => [ '127.0.0.0/24', '127.0.0.1', true ],
			'IPv4 network deny' => [ '127.0.0.0/24', '127.0.1.1', false ],
			'IPv6 network allow' => [ '::/64', '::1', true ],
			'IPv6 network deny' => [ '::/64', '1::1', false ],
			'Two ranges allow' => [ [ '::/64', '127.0.0.0/24' ], '::1', true ],
			'Two ranges deny' => [ [ '::/64', '127.0.0.0/24' ], '1::1', false ],
		];
	}

	/**
	 * @dataProvider provideIsWebhookIp
	 * @param string|string[] $ranges
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testIsWebhookIp( $ranges, string $ip, bool $expected ) {
		$http = $this->createNoOpMock( HttpRequestFactory::class );
		$server = new GitlabServer(
			$http,
			$this->makeFakeResolver(),
			[
				'url' => 'https://localhost/',
				'projectPrefixes' => [ '' ],
				'webhookIps' => $ranges
			]
		);
		$result = $server->isWebhookIp( $ip );
		$this->assertSame( $expected, $result );
	}

	public static function provideIsWebhookIpViaDns() {
		return [
			'single IP allow' => [ '127.0.0.1', '127.0.0.1', true ],
			'single IP deny' => [ '127.0.0.1', '127.0.0.2', false ],
			'multiple IP allow' => [ [ '127.0.0.1', '127.0.0.2' ], '127.0.0.2', true ],
			'multiple IP deny' => [ [ '127.0.0.1', '127.0.0.2' ], '127.0.0.3', false ],
		];
	}

	/**
	 * @dataProvider provideIsWebhookIpViaDns
	 * @param string|string[] $allowedIps
	 * @param string $ip
	 * @param bool $expected
	 */
	public function testIsWebhookIpViaDns( $allowedIps, $ip, $expected ) {
		$http = $this->createNoOpMock( HttpRequestFactory::class );
		$server = new GitlabServer(
			$http,
			static fn ( $host ) => (array)$allowedIps,
			[
				'url' => 'https://localhost/',
				'projectPrefixes' => [ '' ]
			]
		);

		$result = $server->isWebhookIp( $ip );
		$this->assertSame( $expected, $result );
	}

	private function makeFakeResolver() {
		return static fn ( $host ) => [ '10.1.1.1' ];
	}
}

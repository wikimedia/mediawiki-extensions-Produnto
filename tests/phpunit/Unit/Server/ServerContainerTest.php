<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Server;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\Server\BaseServer;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Http\HttpRequestFactory;

/**
 * @covers \MediaWiki\Extension\Produnto\Server\ServerContainer
 */
class ServerContainerTest extends \MediaWikiUnitTestCase {

	private function createServer( $acceptedUrl ) {
		$server = $this->createMock( BaseServer::class );
		$server->method( 'hasUrl' )
			->willReturnCallback( static function ( $url ) use ( $acceptedUrl ) {
				return $url === $acceptedUrl;
			} );
		return $server;
	}

	public static function provideGetServerForUrl() {
		return [
			[ 'http://example.org/foo', 0 ],
			[ 'http://example.org/bar', 1 ],
			[ 'http://example.org/baz', null ]
		];
	}

	/**
	 * @dataProvider provideGetServerForUrl
	 * @param string $url
	 * @param int|null $expectedIndex
	 */
	public function testGetServerForUrl( $url, $expectedIndex ) {
		$serverContainer = new ServerContainer(
			$this->createNoOpMock( HttpRequestFactory::class ),
			new HashConfig( [
				'ProduntoServers' => []
			] )
		);
		$servers = [
			$this->createServer( 'http://example.org/foo' ),
			$this->createServer( 'http://example.org/bar' ),
		];
		$serverContainer->setServersForTesting( $servers );
		$result = $serverContainer->getServerForUrl( $url );
		if ( $expectedIndex === null ) {
			$this->assertNull( $result );
		} else {
			$this->assertSame( $servers[$expectedIndex], $result );
		}
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Server;

use MediaWiki\Extension\Produnto\Server\GitServer;

/**
 * @covers \MediaWiki\Extension\Produnto\Server\GitServer
 */
class GitServerTest extends \MediaWikiUnitTestCase {
	public static function provideRefToVersion() {
		return [
			[ 'refs/heads/main', null ],
			[ 'refs/tags/1.0', '1.0' ],
			[ 'refs/tags/v1.0.beta5~ubuntu16.04', '1.0.beta5~ubuntu16.04' ],
		];
	}

	/**
	 * @dataProvider provideRefToVersion
	 * @param string $ref
	 * @param string $expected
	 */
	public function testRefToVersion( $ref, $expected ) {
		$server = $this->getMockForAbstractClass( GitServer::class );
		$result = $server->refToVersion( $ref );
		$this->assertSame( $expected, $result );
	}
}

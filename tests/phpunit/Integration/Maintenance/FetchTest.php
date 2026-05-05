<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Maintenance;

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Maintenance\Fetch;
use MediaWiki\Extension\Produnto\Server\GitServer;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers \MediaWiki\Extension\Produnto\Maintenance\Fetch
 */
class FetchTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return Fetch::class;
	}

	public function testExecute() {
		$mockFetcher = $this->createMock( Fetcher::class );
		$mockFetcher->expects( $this->once() )->method( 'asyncFetch' );
		$this->setService( 'Produnto.Fetcher', $mockFetcher );

		$mockServer = $this->createMock( GitServer::class );
		$mockServer->method( 'hasUrl' )->willReturn( true );
		$mockServer->method( 'refToVersion' )->willReturn( '1.0.0' );

		$mockServerContainer = $this->createMock( ServerContainer::class );
		$mockServerContainer->method( 'getServerForUrl' )->willReturn( $mockServer );
		$this->setService( 'Produnto.ServerContainer', $mockServerContainer );

		$this->maintenance->setOption( 'async', true );
		$this->maintenance->setOption( 'name', 'example' );
		$this->maintenance->setOption( 'url', 'https://example.com/' );
		$this->maintenance->setOption( 'ref', 'refs/tags/v1.0.0' );
		$this->maintenance->execute();
	}
}

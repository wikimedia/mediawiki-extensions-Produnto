<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Maintenance;

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Maintenance\ManualFetch;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;

/**
 * @covers MediaWiki\Extension\Produnto\Maintenance\ManualFetch
 */
class ManualFetchTest extends MaintenanceBaseTestCase {
	protected function getMaintenanceClass() {
		return ManualFetch::class;
	}

	public function testExecute() {
		$mockFetcher = $this->createMock( Fetcher::class );
		$mockFetcher->expects( $this->once() )->method( 'asyncFetch' );
		$this->setService( 'Produnto.Fetcher', $mockFetcher );
		$this->maintenance->execute();
	}
}

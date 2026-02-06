<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\Store\NameStore;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\NameStore
 * @group Database
 */
class NameStoreTest extends \MediaWikiIntegrationTestCase {
	private function newStore() {
		return new NameStore(
			$this->getServiceContainer()->getConnectionProvider()
		);
	}

	public function testStore() {
		$store = $this->newStore();

		// Simple insert
		$id = $store->store( 'foo' );
		$this->assertSame( 1, $id );
		$this->assertName( 1, 'foo' );
		$this->newSelectQueryBuilder()
			->select( 'pfn_name' )
			->from( 'produnto_file_name' )
			->where( [ 'pfn_id' => $id ] )
			->assertFieldValue( 'foo' );

		// Insert duplicate
		$id1 = $store->store( 'foo' );
		$this->assertSame( $id, $id1 );
		$this->assertName( 1, 'foo' );

		// Insert non-duplicate
		$id2 = $store->store( 'bar' );
		$this->assertSame( 2, $id2 );
		$this->assertName( 2, 'bar' );
	}

	private function assertName( $id, $name ) {
		$this->newSelectQueryBuilder()
			->select( 'pfn_name' )
			->from( 'produnto_file_name' )
			->where( [ 'pfn_id' => $id ] )
			->assertFieldValue( $name );
	}
}

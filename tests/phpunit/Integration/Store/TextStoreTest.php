<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\Store\TextStore;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\TextStore
 * @group Database
 */
class TextStoreTest extends \MediaWikiIntegrationTestCase {
	public function testStore() {
		$textStore = new TextStore( $this->getServiceContainer()->getConnectionProvider() );
		$hashA = 'ca978112ca1bbdcafac231b39a23dc4da786eff8147c4e72b9807785afee48bb';
		$hashB = '3e23e8160039594a33894f6564e1b1348bbd7a0088d42c4acb73eeaed59c009d';

		$this->assertSame( $hashA, $textStore->store( 'a' ) );
		$this->assertSame( $hashA, $textStore->store( 'a' ) );
		$this->assertSame( $hashB, $textStore->store( 'b' ) );
		$this->newSelectQueryBuilder()
			->select( [ 'pft_hash', 'pft_text' ] )
			->from( 'produnto_file_text' )
			->assertResultSet( [
				[ $hashB, 'b' ],
				[ $hashA, 'a' ],
			] );
	}
}

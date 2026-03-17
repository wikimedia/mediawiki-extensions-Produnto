<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\SqlFileAccess;
use Wikimedia\ObjectCache\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\SqlFileAccess
 * @group Database
 */
class SqlFileAccessTest extends \MediaWikiIntegrationTestCase {
	private ?SqlFileAccess $fileAccess = null;

	public function addDBDataOnce() {
		$store = ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
		$store->createPackageVersion()
			->name( 'test' )
			->version( '1.0.0' )
			->fetchedUrl( '' )
			->addFile( 'exists1', '1' )
			->addFile( 'exists2', '2' )
			->commit();
	}

	private function getFileAccess() {
		// phpcs:ignore MediaWiki.Usage.AssignmentInReturn
		return $this->fileAccess ??= new SqlFileAccess(
			new MapCacheLRU( 100 ),
			$this->getDb()
		);
	}

	private function disableDb() {
		TestingAccessWrapper::newFromObject( $this->getFileAccess() )->db =
			$this->createNoOpMock( IReadableDatabase::class );
	}

	public function testHasFile() {
		$this->assertTrue( $this->getFileAccess()->hasFile( 1, 'exists1' ) );
		$this->assertFalse( $this->getFileAccess()->hasFile( 2, 'exists1' ) );

		$this->disableDb();

		$this->assertTrue( $this->getFileAccess()->hasFile( 1, 'exists1' ) );
		$this->assertTrue( $this->getFileAccess()->hasFile( 1, 'exists1' ) );
		$this->assertFalse( $this->getFileAccess()->hasFile( 1, 'no' ) );
	}

	public function testGetFileContents() {
		$this->assertSame( '1', $this->getFileAccess()->getFileContents( 1, 'exists1' ) );
		$this->assertSame( '2', $this->getFileAccess()->getFileContents( 1, 'exists2' ) );
		$this->assertNull( $this->getFileAccess()->getFileContents( 1, 'no' ) );
		$this->assertNull( $this->getFileAccess()->getFileContents( 2, 'exists1' ) );

		$this->disableDb();

		$this->assertSame( '1', $this->getFileAccess()->getFileContents( 1, 'exists1' ) );
		$this->assertSame( '2', $this->getFileAccess()->getFileContents( 1, 'exists2' ) );
		$this->assertNull( $this->getFileAccess()->getFileContents( 1, 'no' ) );
		$this->assertNull( $this->getFileAccess()->getFileContents( 2, 'exists1' ) );
	}
}

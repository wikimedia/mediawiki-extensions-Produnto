<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Store;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \MediaWiki\Extension\Produnto\Store\PackageAccess
 */
class PackageAccessTest extends \MediaWikiUnitTestCase {
	private function newPackage() {
		$mockDb = $this->createNoOpAbstractMock( IReadableDatabase::class );
		return new PackageAccess(
			new MapCacheLRU( 10 ),
			$mockDb,
			1,
			'foo',
			'1.0.0',
			'http://example.com/foo',
			2,
			null
		);
	}

	public function testGetId() {
		$this->assertSame( 1, $this->newPackage()->getId() );
	}

	public function testGetName() {
		$this->assertSame( 'foo', $this->newPackage()->getName() );
	}

	public function testGetVersion() {
		$this->assertSame( '1.0.0', $this->newPackage()->getVersion() );
	}

	public function testGetUrl() {
		$this->assertSame( 'http://example.com/foo', $this->newPackage()->getUrl() );
	}

	public function testGetState() {
		$this->assertSame( 2, $this->newPackage()->getState() );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Rest;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\Rest\PackagesIndexHandler;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\ResponseFactory;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\PackagesIndexHandler
 */
class PackagesIndexHandlerTest extends \MediaWikiUnitTestCase {
	public function testGetETag() {
		$res1 = $this->getHandler( 1500 )->getETag();
		$res2 = $this->getHandler( 1600 )->getETag();
		$res3 = $this->getHandler( 2500 )->getETag();

		$this->assertMatchesRegularExpression( '/^"\w+"$/', $res1 );
		$this->assertSame( $res1, $res2 );
		$this->assertMatchesRegularExpression( '/^"\w+"$/', $res3 );
		$this->assertNotSame( $res1, $res3 );
	}

	public function testExecute() {
		$handler = $this->getHandler( 2500 );
		$handler->getETag();
		$response = $handler->execute();
		$expected = json_encode( [ 'partitions' => [
			[ 'href' => '0', 'start' => 0, 'end' => 999 ],
			[ 'href' => '1', 'start' => 1000, 'end' => 1999 ],
			[ 'href' => '2', 'start' => 2000, 'end' => 2999 ],
		] ] );

		$this->assertSame( $expected, $response->getBody()->getContents() );
		$this->assertSame( [ 'public' ], $response->getHeader( 'Cache-Control' ) );
	}

	private function getHandler( ?int $maxPackageId ): PackagesIndexHandler {
		$store = $this->createMock( ProduntoStore::class );
		$store->expects( $this->once() )
			->method( 'getMaxPackageId' )
			->willReturn( $maxPackageId );

		$config = new HashConfig( [ 'ProduntoApiBatchSizes' => [ 'packages' => 1000 ] ] );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'isEveryoneAllowed' )
			->willReturn( true );

		$handler = new PackagesIndexHandler( $config, $store, $permissionManager );
		TestingAccessWrapper::newFromObject( $handler )->responseFactory = new ResponseFactory( [] );
		return $handler;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\RecentDeploymentsHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Timestamp\TimestampFormat;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\RecentDeploymentsHandler
 * @group Database
 */
class RecentDeploymentsHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testExecute() {
		$store = $this->getProduntoServices()->getStore();
		$store->createPackageVersion()
			->name( 'package1' )
			->version( '1' )
			->fetchedUrl( '' )
			->commit();
		$store->createPackageVersion()
			->name( 'package2' )
			->version( '1' )
			->fetchedUrl( '' )
			->commit();

		$title = $this->getProduntoServices()->getUpdater()->getPackagesTitleValue();
		$authority = $this->getTestUser()->getAuthority();
		$ts1 = $this->editPage(
			$title,
			'{"package1":"1"}',
			'summary1',
			0,
			$authority
		)->getNewRevision()->getTimestamp();
		$ts2 = $this->editPage(
			$title,
			'{"package2":"1"}',
			'summary2',
			0,
			$authority
		)->getNewRevision()->getTimestamp();

		$services = $this->getServiceContainer();
		$handler = new RecentDeploymentsHandler(
			$store,
			$services->getPermissionManager(),
			$services->getConnectionProvider()
		);

		$response = $this->executeHandler( $handler, new RequestData() );
		$this->assertSame( "\"2\"", $response->getHeader( 'ETag' )[0] );

		$expected = [ 'deployments' => [
			[
				'id' => 2,
				'controlWiki' => WikiMap::getCurrentWikiId(),
				'revision' => 2,
				'active' => true,
				'packages' => [
					'package2' => '1'
				],
				'userText' => $authority->getUser()->getName(),
				'timestamp' => wfTimestamp( TimestampFormat::ISO_8601, $ts2 ),
				'summary' => 'summary2'
			],
			[
				'id' => 1,
				'controlWiki' => WikiMap::getCurrentWikiId(),
				'revision' => 1,
				'packages' => [
					'package1' => '1'
				],
				'userText' => $authority->getUser()->getName(),
				'timestamp' => wfTimestamp( TimestampFormat::ISO_8601, $ts1 ),
				'summary' => 'summary1'
			],
		] ];
		$this->assertSame( $expected, json_decode( $response->getBody()->getContents(), true ) );
	}

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

}

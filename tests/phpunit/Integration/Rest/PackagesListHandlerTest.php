<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\PackagesListHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\PackagesListHandler
 * @group Database
 */
class PackagesListHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	private function getHandler() {
		$services = $this->getServiceContainer();
		return new PackagesListHandler(
			new HashConfig( [ 'ProduntoApiBatchSizes' => [ 'packages' => 10 ] ] ),
			$this->getProduntoServices()->getStore(),
			$services->getPermissionManager(),
			$services->getContentLanguage(),
			$services->getMessageFormatterFactory(),
		);
	}

	public function testGetETag() {
		$etag1 = $this->getETag();

		$store = $this->getProduntoServices()->getStore();
		$package = $store->createPackageVersion()
			->name( 'package1' )
			->version( '1.0' )
			->fetchedUrl( '' )
			->suspend();

		$etag2 = $this->getETag();
		$this->assertNotSame( $etag1, $etag2 );

		$store->resumePackageBuilder( $package )
			->commit();

		$etag3 = $this->getETag();
		$this->assertNotSame( $etag1, $etag3 );
		$this->assertNotSame( $etag2, $etag3 );
	}

	private function getETag() {
		$res = $this->executeHandler(
			$this->getHandler(), new RequestData(),
			validatedParams: [ 'partition' => 0 ],
			authority: $this->mockRegisteredNullAuthority()
		);
		$this->assertSame( 200, $res->getStatusCode() );
		$etag = $res->getHeader( 'ETag' )[0] ?? '';
		$this->assertMatchesRegularExpression( '/^"\w+"$/', $etag );
		return $etag;
	}

	public function testExecute() {
		$store = $this->getProduntoServices()->getStore();
		$package1 = $store->createPackageVersion()
			->name( 'package1' )
			->version( '1.0' )
			->fetchedUrl( 'fetched' )
			->homepageUrl( 'home' )
			->docUrl( 'doc' )
			->collabUrl( 'collab' )
			->issueUrl( 'issue' )
			->localName( 'en', 'en name' )
			->localName( 'fr', 'fr name' )
			->description( 'en', 'en desc' )
			->description( 'fr', 'fr desc' )
			->author( 'Author 1' )
			->author( 'Author 2' )
			->license( 'GPL' )
			->requires( 'package2', '1.0' )
			->commit();

		$package2 = $store->createPackageVersion()
			->name( 'package2' )
			->version( '1.0' )
			->fetchedUrl( 'fetched' )
			->suspend();
		$store->resumePackageBuilder( $package2 )
			->fail( \StatusValue::newFatal( 'produnto-fetch-error', 'some error' ) );

		$res = $this->executeHandler(
			$this->getHandler(), new RequestData(),
			validatedParams: [ 'partition' => 0 ],
			authority: $this->mockRegisteredNullAuthority()
		);

		$expected = [
			'packages' => [
				[
					'name' => 'package1',
					'version' => '1.0',
					'id' => $package1->getId(),
					'fetchedUrl' => 'fetched',
					'upstreamRef' => '',
					'localName' => [
						'en' => 'en name',
						'fr' => 'fr name',
					],
					'description' => [
						'en' => 'en desc',
						'fr' => 'fr desc',
					],
					'homepageUrl' => 'home',
					'collabUrl' => 'collab',
					'docUrl' => 'doc',
					'issueUrl' => 'issue',
					'authors' => [
						'Author 1',
						'Author 2'
					],
					'license' => 'GPL',
					'requires' => [
						'package2' => '1.0',
					],
				],
				[
					'name' => 'package2',
					'version' => '1.0',
					'id' => $package2->getId(),
					'fetchedUrl' => 'fetched',
					'upstreamRef' => '',
					'state' => 'failed',
					'errors' => [ [
						'key' => 'produnto-fetch-error',
						'translations' => [
							'en' => 'Fetch failed: some error'
						]
					] ]
				]
			]
		];

		$this->assertSame(
			$expected,
			json_decode( $res->getBody()->getContents(), true )
		);

		// Empty partition
		$res = $this->executeHandler(
			$this->getHandler(), new RequestData(),
			validatedParams: [ 'partition' => 1 ],
			authority: $this->mockRegisteredNullAuthority()
		);
		$this->assertSame( '{"packages":[]}', $res->getBody()->getContents() );
	}
}

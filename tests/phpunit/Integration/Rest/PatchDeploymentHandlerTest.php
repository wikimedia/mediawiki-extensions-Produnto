<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\PatchDeploymentHandler;
use MediaWiki\Extension\Produnto\Rest\RecentDeploymentsHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\PatchDeploymentHandler
 * @group Database
 */
class PatchDeploymentHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public function testExecute() {
		$store = $this->getProduntoServices()->getStore();
		$store->createPackageVersion()
			->name( 'package1' )
			->version( '1' )
			->fetchedUrl( '' )
			->module( 'module1', 'module.lua' )
			->commit();
		$store->createPackageVersion()
			->name( 'package1' )
			->version( '2' )
			->fetchedUrl( '' )
			->commit();
		$store->createPackageVersion()
			->name( 'package2' )
			->version( '1' )
			->fetchedUrl( '' )
			->module( 'module1', 'module.lua' )
			->commit();

		$data = $this->executePatch( [ 'package1' => '1' ], 's1' );
		$this->assertTrue( $data['ok'] );
		$this->assertSame( [], $data['warnings'] );
		$this->assertSame( [], $data['errors'] );
		$this->assertSame( [ 'package1' => '1' ], $data['deployment']['packages'] );

		// Verify that the same deployment object is returned by the recent handler
		$recentData = $this->executeRecent();
		$this->assertSame( $recentData['deployments'][0], $data['deployment'] );

		// Add package2 with a warning, not ignored
		$data = $this->executePatch( [ 'package2' => '1' ], 's2' );
		$this->assertFalse( $data['ok'] );
		$this->assertSame( 'produnto-update-module-conflict', $data['warnings'][0]['key'] );
		$this->assertSame( [], $data['errors'] );
		$this->assertArrayNotHasKey( 'deployment', $data );

		// Ignore warnings
		$data = $this->executePatch( [ 'package2' => '1' ], 's2', true );
		$this->assertTrue( $data['ok'] );
		$this->assertSame( 'produnto-update-module-conflict', $data['warnings'][0]['key'] );
		$this->assertSame( [], $data['errors'] );
		// Now it was actually deployed
		$this->assertSame( [ 'package1' => '1', 'package2' => '1' ], $data['deployment']['packages'] );

		// Delete a package
		$data = $this->executePatch( [ 'package1' => null ], '' );
		$this->assertSame( [ 'package2' => '1' ], $data['deployment']['packages'] );
		$this->assertSame( '', $data['deployment']['summary'] );
	}

	private function executePatch( $packages, $summary, $ignoreWarnings = false ) {
		$postData = [
			'packages' => json_encode( $packages ),
			'summary' => $summary,
		];
		if ( $ignoreWarnings ) {
			$postData['ignoreWarnings'] = '1';
		}
		$request = new RequestData( [
			'method' => 'POST',
			'postParams' => $postData
		] );
		$services = $this->getServiceContainer();
		$handler = new PatchDeploymentHandler(
			$services->getContentLanguage(),
			$services->getMessageFormatterFactory(),
			$services->getPageUpdaterFactory(),
			$services->getPageStore(),
			$this->getProduntoServices()->getAuthorizingPageSaverFactory(),
			$this->getProduntoServices()->getUpdater()
		);
		$authority = $this->mockRegisteredAuthorityWithPermissions( [ 'produnto-update' ] );
		$response = $this->executeHandler(
			$handler,
			$request,
			validatedBody: $postData,
			authority: $authority,
		);
		$this->assertSame( 200, $response->getStatusCode() );
		return json_decode( $response->getBody()->getContents(), true );
	}

	private function executeRecent() {
		$handler = new RecentDeploymentsHandler(
			$this->getProduntoServices()->getStore(),
			$this->getServiceContainer()->getPermissionManager(),
			$this->getServiceContainer()->getConnectionProvider()
		);
		$response = $this->executeHandler( $handler, new RequestData(),
			authority: $this->mockAnonNullAuthority() );
		$this->assertSame( 200, $response->getStatusCode() );
		return json_decode( $response->getBody()->getContents(), true );
	}

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}
}

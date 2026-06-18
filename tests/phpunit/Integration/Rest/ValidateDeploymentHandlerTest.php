<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Tests\Rest\Handler\HandlerIntegrationTestTrait;
use stdClass;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\ValidateDeploymentHandler
 * @group Database
 */
class ValidateDeploymentHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerIntegrationTestTrait;

	public function addDBDataOnce() {
		$store = $this->getProduntoServices()->getStore();
		$store->createPackageVersion()
			->name( 'good-package' )
			->version( '1' )
			->fetchedUrl( '' )
			->module( 'module1', 'module.lua' )
			->commit();
		$store->createPackageVersion()
			->name( 'conflicting-package' )
			->version( '1' )
			->fetchedUrl( '' )
			->module( 'module1', 'module.lua' )
			->commit();
		$store->createPackageVersion()
			->name( 'unfetched-package' )
			->version( '1' )
			->fetchedUrl( '' )
			->suspend();
	}

	public static function provideExecute() {
		$good = [
			'ok' => true,
			'warnings' => [],
			'errors' => []
		];
		return [
			'empty' => [
				new stdClass,
				$good
			],
			'good package' => [
				[ 'good-package' => '1' ],
				$good
			],
			'conflicting package' => [
				[ 'good-package' => '1', 'conflicting-package' => '1' ],
				[
					'ok' => true,
					'warnings' => [ [
						'key' => 'produnto-update-module-conflict',
						'package' => 'conflicting-package'
					] ],
					'errors' => []
				]
			],
			'unfetched package' => [
				[ 'good-package' => '1', 'unfetched-package' => '1' ],
				[
					'ok' => false,
					'warnings' => [],
					'errors' => [ [
						'key' => 'produnto-update-fetching-package',
						'package' => 'unfetched-package'
					] ]
				]
			],
		];
	}

	/**
	 * @dataProvider provideExecute
	 * @param stdClass|array<string,string> $packages Package to version map
	 * @param array $expected Response JSON
	 */
	public function testExecute( $packages, $expected ) {
		$response = $this->execute( [
			'path' => '/rest.php/produnto/v1/deployment/validate',
			'method' => 'POST',
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $packages )
		] );
		$data = json_decode( $response->getBody()->getContents(), true );
		$this->normalizeErrors( $data['errors'] );
		$this->normalizeErrors( $data['warnings'] );
		$this->assertSame( $expected, $data );
	}

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	/**
	 * Ignore translations
	 */
	private function normalizeErrors( array &$errors ) {
		foreach ( $errors as &$error ) {
			unset( $error['translations'] );
		}
	}
}

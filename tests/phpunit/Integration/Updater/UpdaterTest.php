<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Updater\Updater
 */
class UpdaterTest extends \MediaWikiIntegrationTestCase {
	public function addDBDataOnce() {
		$store = $this->getStore();
		$store->createPackageVersion()
			->name( 'test1' )
			->fetchedUrl( 'http://example.com/' )
			->version( '1.0.0' )
			->addFile( 'src/init.lua', 'return {}' )
			->module( 'test', 'src/init.lua' )
			->commit();
		$store->createPackageVersion()
			->name( 'test2' )
			->fetchedUrl( 'http://example.com/' )
			->version( '1.0.0' )
			->addFile( 'src/init.lua', 'return {}' )
			->module( 'test', 'src/init.lua' )
			->commit();
	}

	private function getUpdater(): Updater {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getUpdater();
	}

	private function getStore(): ProduntoStore {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
	}

	public static function provideValidateDeployment() {
		return [
			'array' => [ [], 'produnto-update-invalid' ],
			'empty deployment' => [ (object)[], null ],
			'good package' => [
				(object)[ 'test1' => '1.0.0' ],
				null
			],
			'nonexistent package' => [
				(object)[ 'nonexistent' => '1.0.0' ],
				'produnto-update-missing-package'
			],
			'nonexistent version' => [
				(object)[ 'test1' => '2.0.0' ],
				'produnto-update-missing-package'
			],
			'duplicate module' => [
				(object)[
					'test1' => '1.0.0',
					'test2' => '1.0.0'
				],
				'produnto-update-module-conflict'
			],
		];
	}

	/**
	 * @dataProvider provideValidateDeployment
	 * @param mixed $input
	 * @param ?string $expectedMessage
	 */
	public function testValidateDeployment( $input, $expectedMessage ) {
		$updater = $this->getUpdater();
		$status = $updater->validateDeployment( $input );
		if ( $expectedMessage ) {
			$this->assertStatusMessage( $expectedMessage, $status );
		} else {
			$this->assertStatusGood( $status );
			foreach ( $status->getPackages() as $package ) {
				$this->assertSame( array_key_first( (array)$input ), $package->getName() );
			}
		}
	}
}

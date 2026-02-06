<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Extension\Produnto\Updater\ValidationStatus;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Updater\Updater
 */
class UpdaterTest extends \MediaWikiIntegrationTestCase {
	public function addDBDataOnce() {
		$store = $this->getStore();
		$store->createPackageVersion()
			->name( 'test1' )
			->url( 'http://example.com/' )
			->version( '1.0.0' )
			->addFile( 'README.MD', 'A file' )
			->commit();
		$store->createPackageVersion()
			->name( 'test2' )
			->url( 'http://example.com/' )
			->version( '1.0.0' )
			->addFile( 'README.MD', 'A file' )
			->commit();
	}

	private function getUpdater(): Updater {
		return $this->getServiceContainer()->get( 'Produnto.Updater' );
	}

	private function getStore(): ProduntoStore {
		return $this->getServiceContainer()->get( 'Produnto.Store' );
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
			'rejected by hook' => [
				(object)[ 'test2' => '1.0.0' ],
				'hook-error'
			],
		];
	}

	/**
	 * @dataProvider provideValidateDeployment
	 * @param mixed $input
	 * @param ?string $expectedMessage
	 */
	public function testValidateDeployment( $input, $expectedMessage ) {
		$this->clearHook( 'ProduntoValidatePackage' );
		$this->clearHook( 'ProduntoCreateDeployment' );
		$this->setTemporaryHook(
			'ProduntoValidatePackage',
			static function ( PackageAccess $package, ValidationStatus $status ) {
				if ( $package->getName() === 'test2' ) {
					$status->fatal( 'hook-error' );
					return false;
				}
				return true;
			}
		);

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

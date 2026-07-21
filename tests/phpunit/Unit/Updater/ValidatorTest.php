<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Extension\Produnto\Updater\Validator;
use MediaWiki\HookContainer\HookContainer;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\Produnto\Updater\Validator
 */
class ValidatorTest extends \MediaWikiUnitTestCase {
	private int $nextId = 1;

	public static function provideValidate() {
		return [
			'array' => [ [], 'produnto-update-invalid' ],
			'empty deployment' => [ (object)[], null ],
			'non-string version' => [
				(object)[ 'test1' => null ],
				'produnto-update-invalid'
			],
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
			'package not ready' => [
				(object)[ 'fetching' => '1.0.0' ],
				'produnto-update-fetching-package'
			],
			'package failed' => [
				(object)[ 'failed' => '1.0.0' ],
				'produnto-update-failed-package'
			],
			'platform dependency passed' => [
				(object)[ 'mw1' => '1.0.0' ],
				null
			],
			'platform dependency failed' => [
				(object)[ 'mw0' => '1.0.0' ],
				'produnto-update-requires-unsatisfied-platform'
			],
			'regular dependency passed' => [
				(object)[
					'test1' => '1.0.0',
					'requires1' => '1.0.0',
				],
				null
			],
			'regular dependency missing' => [
				(object)[
					'test1' => '1.0.0',
					'requires-nonexistent' => '1.0.0',
				],
				'produnto-update-requires-missing'
			],
			'regular dependency failed' => [
				(object)[
					'test1' => '1.0.0',
					'requires-unsatisfied' => '1.0.0',
				],
				'produnto-update-requires-unsatisfied'
			],
		];
	}

	/**
	 * @dataProvider provideValidate
	 * @param mixed $data
	 * @param ?string $expectedMessage
	 */
	public function testValidate( $data, $expectedMessage ) {
		$validator = $this->getValidator( $data );
		$status = $validator->validate();
		if ( $expectedMessage ) {
			$this->assertStatusMessage( $expectedMessage, $status );
		} else {
			$this->assertStatusGood( $status );
			$packageNames = [];
			foreach ( $status->getPackages() as $package ) {
				$packageNames[] = $package->getName();
			}
			$this->assertArrayEquals( array_keys( (array)$data ), $packageNames );
		}
	}

	private function getValidator( $data ): Validator {
		$packages = [];
		$packages[] = $this->createPackage( 'test1', modules: [ 'test' => 'src/init.lua' ] );
		$packages[] = $this->createPackage( 'test2', modules: [ 'test' => 'src/init.lua' ] );
		$packages[] = $this->createPackage( 'fetching', state: ProduntoStore::STATE_FETCHING );
		$packages[] = $this->createPackage( 'failed',
			state: ProduntoStore::STATE_FAILED,
			error: StatusValue::newFatal(
				'produnto-fetch-server-error',
				500,
				'Server error'
			)
		);

		$packages[] = $this->createPackage( 'mw1', requires: [ 'MediaWiki' => '>1.42' ] );
		$packages[] = $this->createPackage( 'mw0', requires: [ 'MediaWiki' => '<1.0' ] );
		$packages[] = $this->createPackage( 'requires1',
			requires: [ 'test1' => '*' ] );
		$packages[] = $this->createPackage( 'requires-nonexistent',
			requires: [ 'nonexistent' => '1.0.0' ] );
		$packages[] = $this->createPackage( 'requires-unsatisfied',
			requires: [ 'test1' => '2.0.0' ] );

		$store = $this->createMock( ProduntoStore::class );
		$store->method( 'getPackageByName' )
			->willReturnCallback( static function ( $name, $version ) use ( $packages ) {
				foreach ( $packages as $package ) {
					if ( $package->getName() === $name && $package->getVersion() === $version ) {
						return $package;
					}
				}
				return null;
			} );

		$hookContainer = $this->createMock( HookContainer::class );
		return new Validator( $store, $hookContainer, $data );
	}

	private function createPackage(
		string $name,
		string $version = '1.0.0',
		array $modules = [],
		array $requires = [],
		int $state = ProduntoStore::STATE_READY,
		?StatusValue $error = null
	) {
		$files = new SimpleFileAccess( [
			1 => [ 'src/init.lua' => '' ],
			2 => [ 'src/init.lua' => '' ],
		] );
		$serializedError = $error ? serialize( $error ) : null;
		$id = $this->nextId++;
		return new PackageAccess(
			$files, $id, $name, $version, '', '',
			[
				'modules' => $modules,
				'requires' => $requires,
			],
			$state,
			$serializedError
		);
	}

}

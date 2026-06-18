<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Extension\Produnto\Updater\UpdateStatus;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityValue;
use StatusValue;

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
		$store->createPackageVersion()
			->name( 'fetching' )
			->fetchedUrl( '' )
			->version( '1.0.0' )
			->suspend();

		$failed = $store->createPackageVersion()
			->name( 'failed' )
			->fetchedUrl( '' )
			->version( '1.0.0' )
			->suspend();
		$store->resumePackageBuilder( $failed )
			->fail( StatusValue::newFatal(
				'produnto-fetch-server-error',
				500,
				'Server error'
			) );
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
			'package not ready' => [
				(object)[ 'fetching' => '1.0.0' ],
				'produnto-update-fetching-package'
			],
			'package failed' => [
				(object)[ 'failed' => '1.0.0' ],
				'produnto-update-failed-package'
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

	public function testDeploy() {
		$input = self::provideValidateDeployment()['good package'][0];
		$updater = $this->getUpdater();
		$status = $updater->validateDeployment( $input );
		$this->assertStatusGood( $status );
		$updater->deploy( $status, 100, new UserIdentityValue( 1, 'User' ) );
		$this->assertNotNull( $this->getStore()->getActiveDeployment() );
		$this->runJobs( [ 'numJobs' => 1 ] );
	}

	public function testGetValidationResult() {
		$updater = $this->getUpdater();
		$data1 = (object)[ 'a' => 1 ];
		$status1 = new UpdateStatus();
		$data2 = (object)[ 'b' => 1 ];
		$status2 = new UpdateStatus();

		$this->assertNull( $updater->getValidationResult( $data1 ) );
		$updater->saveValidationResult( $data1, $status1 );
		$this->assertSame( $status1, $updater->getValidationResult( $data1 ) );
		$this->assertNull( $updater->getValidationResult( $data2 ) );

		$updater->saveValidationResult( $data2, $status2 );
		$this->assertSame( $status2, $updater->getValidationResult( $data2 ) );
	}

	public static function provideGetPackagesTitleValue() {
		return [
			[ null, NS_MEDIAWIKI, 'Packages.json' ],
			[ 'MediaWiki:Produnto.json', NS_MEDIAWIKI, 'Produnto.json' ],
		];
	}

	/**
	 * @dataProvider provideGetPackagesTitleValue
	 */
	public function testGetPackagesTitleValue( ?string $configValue, int $ns, string $dbKey ) {
		$config = new HashConfig( [ 'ProduntoPackagesTitle' => $configValue ] );
		$titleParser = $this->getServiceContainer()->getTitleParser();
		$store = $this->createNoOpMock( ProduntoStore::class );
		$jobs = $this->createNoOpMock( JobQueueGroup::class );

		$updater = new Updater( $config, $titleParser, $store, $jobs );
		$title = $updater->getPackagesTitleValue();
		$this->assertSame( $ns, $title->getNamespace() );
		$this->assertSame( $dbKey, $title->getDBkey() );
	}
}

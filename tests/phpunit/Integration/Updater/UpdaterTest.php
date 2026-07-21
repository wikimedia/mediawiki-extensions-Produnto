<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Extension\Produnto\Updater\UpdateStatus;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityValue;

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
	}

	private function getUpdater(): Updater {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getUpdater();
	}

	private function getStore(): ProduntoStore {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
	}

	public function testValidateDeployment() {
		$updater = $this->getUpdater();
		$status = $updater->validateDeployment( (object)[ 'test1' => '1.0.0' ] );
		$this->assertStatusGood( $status );
		foreach ( $status->getPackages() as $package ) {
			$this->assertSame( 'test1', $package->getName() );
		}
	}

	public function testDeploy() {
		$updater = $this->getUpdater();
		$status = $updater->validateDeployment( (object)[ 'test1' => '1.0.0' ] );
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
		$hooks = $this->createNoOpMock( HookContainer::class );

		$updater = new Updater( $config, $titleParser, $store, $jobs, $hooks );
		$title = $updater->getPackagesTitleValue();
		$this->assertSame( $ns, $title->getNamespace() );
		$this->assertSame( $dbKey, $title->getDBkey() );
	}
}

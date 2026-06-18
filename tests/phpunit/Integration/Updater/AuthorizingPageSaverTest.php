<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\PackageAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Updater\AuthorizingPageSaver
 * @covers \MediaWiki\Extension\Produnto\Updater\AuthorizingPageSaverFactory
 * @group Database
 */
class AuthorizingPageSaverTest extends \MediaWikiIntegrationTestCase {
	public function addDBData() {
		$store = $this->getProduntoServices()->getStore();
		$store->createPackageVersion()
			->name( 'package1' )
			->version( '1' )
			->fetchedUrl( '' )
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
			->commit();
	}

	public function testAuthorizeFail() {
		$status = $this->getSaver( authority: $this->getTestUser()->getAuthority() )
			->patchJson( '{}' )
			->save();
		$this->assertStatusError( 'badaccess-groups', $status );
	}

	/**
	 * Patching a non-JSON content model just overwrites the content
	 */
	public function testPatchNonJson() {
		$this->editPage( $this->getTitle(), 'abc' );
		$status = $this->getSaver()
			->patchJson( '{}' )
			->summary( 'summary' )
			->save();
		$this->assertStatusGood( $status );
		$this->assertSame( 'summary', $status->getRevision()->getComment()->text );
	}

	/**
	 * Patch valid previous content
	 */
	public function testPatchValid() {
		$status = $this->getSaver()
			->patchJson( '{"package1": "1"}' )
			->save();
		$this->assertStatusGood( $status );
		$this->assertVersions( [ 'package1' => '1' ], $status->getPackages() );
		$status = $this->getSaver()
			->patchJson( '{"package1": "2"}' )
			->save();
		$this->assertStatusGood( $status );
		$this->assertVersions( [ 'package1' => '2' ], $status->getPackages() );
	}

	public static function providePatchJson() {
		return [
			'invalid' => [
				'test',
				[],
				'json-error-syntax'
			],
			'empty' => [
				'{}',
				[],
			],
			'simple' => [
				'{"package1": "1"}',
				[ 'package1' => '1' ],
			],
			'not object' => [
				'"test"',
				[],
				'produnto-update-invalid'
			],
			'version not string' => [
				'{"package1": {}}',
				[],
				'produnto-update-invalid'
			],
			'object sort' => [
				'{"package2": "1", "package1": "1"}',
				[ 'package1' => '1', 'package2' => '1' ]
			]
		];
	}

	/**
	 * Apply a patch with various inputs
	 * @dataProvider providePatchJson
	 */
	public function testPatchJson( string $json, array $versions, ?string $error = null ) {
		$status = $this->getSaver()
			->patchJson( $json )
			->save();
		if ( $error ) {
			$this->assertStatusError( $error, $status );
		} else {
			$this->assertStatusGood( $status );
			$this->assertVersions( $versions, $status->getPackages() );
		}
	}

	public function testPatchDelete() {
		$status = $this->getSaver()
			->patchJson( '{"package1": "1"}' )
			->save();
		$this->assertStatusGood( $status );
		$this->assertVersions( [ 'package1' => '1' ], $status->getPackages() );
		$status = $this->getSaver()
			->patchJson( '{"package1": null}' )
			->save();
		$this->assertStatusGood( $status );
		$this->assertVersions( [], $status->getPackages() );
	}

	public function testNullEdit() {
		$this->getSaver()
			->patchJson( '{"package1": "1"}' )
			->save();
		$status = $this->getSaver()
			->patchJson( '{"package1": "1"}' )
			->save();
		$this->assertStatusError( 'produnto-update-null-edit', $status );
	}

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	private function getSaver( $authority = null ) {
		$authority ??= $this->getTestSysop()->getAuthority();
		return $this->getProduntoServices()
			->getAuthorizingPageSaverFactory()
			->create( $authority );
	}

	private function getTitle() {
		return $this->getProduntoServices()->getUpdater()->getPackagesTitleValue();
	}

	/**
	 * @param array<string,string> $expectedVersions
	 * @param PackageAccess[] $packages
	 */
	private function assertVersions( array $expectedVersions, array $packages ) {
		$packageVersions = [];
		foreach ( $packages as $package ) {
			$packageVersions[$package->getName()] = $package->getVersion();
		}
		$this->assertSame( $expectedVersions, $packageVersions );
	}
}

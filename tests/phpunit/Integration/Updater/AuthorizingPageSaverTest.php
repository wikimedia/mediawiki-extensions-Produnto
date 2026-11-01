<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Updater;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\PackageAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Updater\AuthorizingPageSaver
 * @covers \MediaWiki\Extension\Produnto\Updater\AuthorizingPageSaverFactory
 * @covers \MediaWiki\Extension\Produnto\Store\PackageProtectionStore
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
		$this->overrideConfigValue( 'GroupPermissions', [
			'sysop' => [ 'produnto-update' => true ],
		] );
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

	public static function provideProtectedChanges() {
		return [
			'initial deployment denied' => [ [], [ 'package1' => '1' ], 'sysop', [], false ],
			'upgrade denied' => [ [ 'package1' => '1' ], [ 'package1' => '2' ], 'sysop', [], false ],
			'removal denied' => [ [ 'package1' => '1' ], [ 'package1' => null ], 'sysop', [], false ],
			'empty string removal denied' => [ [ 'package1' => '1' ], [ 'package1' => '' ], 'sysop', [], false ],
			'full protection allowed' => [ [], [ 'package1' => '1' ], 'sysop', [ 'editprotected' ], true ],
			'protected removal allowed' => [
				[ 'package1' => '1' ], [ 'package1' => null ], 'sysop', [ 'editprotected' ], true
			],
			'custom protection denied' => [ [], [ 'package1' => '1' ], 'produnto-reviewer', [], false ],
			'custom protection allowed' => [
				[], [ 'package1' => '1' ], 'produnto-reviewer', [ 'produnto-reviewer' ], true
			],
			'editprotected does not bypass custom level' => [
				[], [ 'package1' => '1' ], 'produnto-reviewer', [ 'editprotected' ], false
			],
			'semiprotection allowed' => [
				[], [ 'package1' => '1' ], 'autoconfirmed', [ 'editsemiprotected' ], true
			],
			'unchanged protected package' => [
				[ 'package1' => '1' ], [ 'package1' => '1', 'package2' => '1' ], 'sysop', [], true
			],
			'unrelated deployment allowed' => [
				[ 'package1' => '1' ], [ 'package2' => '1' ], 'sysop', [], true
			],
			'mixed patch is atomic' => [
				[ 'package1' => '1' ], [ 'package1' => '2', 'package2' => '1' ], 'sysop', [], false
			],
		];
	}

	/** @dataProvider provideProtectedChanges */
	public function testProtectedChanges(
		array $initial, array $patch, string $level, array $rights, bool $allowed
	) {
		$this->setGroupPermissions( 'produnto-deployer', 'produnto-update', true );
		foreach ( $rights as $right ) {
			$this->setGroupPermissions( 'produnto-deployer', $right, true );
		}
		$authority = $this->getTestUser( [ 'produnto-deployer' ] )->getAuthority();
		$this->assertStatusGood( $this->getSaver()->patchJson( json_encode( (object)$initial ) )->save() );
		$this->protectPackage( $level );
		$before = $this->getProduntoServices()->getStore()->getActiveDeployment()->getId();

		$status = $this->getSaver( $authority )
			->patchJson( json_encode( (object)$patch ) )
			->ignoreWarnings()
			->save();
		if ( $allowed ) {
			$this->assertStatusGood( $status );
			$this->assertNotSame( $before, $this->getProduntoServices()->getStore()->getActiveDeployment()->getId() );
		} else {
			$this->assertStatusError( 'produnto-update-protected', $status );
			$this->assertSame( $before, $this->getProduntoServices()->getStore()->getActiveDeployment()->getId() );
		}
	}

	public function testExpiredProtection() {
		$this->setGroupPermissions( 'produnto-deployer', 'produnto-update', true );
		$this->protectPackage( 'sysop' );
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'protected_titles' )
			->set( [ 'pt_expiry' => '20000101000000' ] )
			->where( [
				'pt_namespace' => NS_PACKAGE,
				'pt_title' => $this->getProduntoServices()->getRepoLinker()
					->getPackageLinkTarget( 'package1' )->getDBkey(),
			] )
			->caller( __METHOD__ )->execute();
		$status = $this->getSaver( $this->getTestUser( [ 'produnto-deployer' ] )->getAuthority() )
			->patchJson( '{"package1": "1"}' )->save();
		$this->assertStatusGood( $status );
	}

	private function protectPackage( string $level ): void {
		$this->overrideConfigValue( 'RestrictionLevels', [ '', 'autoconfirmed', 'sysop', 'produnto-reviewer' ] );
		$this->setGroupPermissions( 'sysop', 'produnto-reviewer', true );
		$this->getProduntoServices()->getPackageProtectionStore()->setProtection(
			$this->getTestSysop()->getUser(), 'package1', $level, 'infinity', 'test'
		);
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

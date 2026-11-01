<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\RepoViewer;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\RepoViewer\PackageProtectAction;
use MediaWiki\Page\Article;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\PackageProtectAction
 * @group Database
 */
class PackageProtectActionTest extends \MediaWikiIntegrationTestCase {
	private function newAction( ?Authority $authority = null ): PackageProtectAction {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_PACKAGE, 'Example' ) );
		$context->setAuthority( $authority ?? $this->getTestSysop()->getAuthority() );
		$context->setLanguage( 'en' );
		$article = new Article( $context->getTitle() );
		$article->setContext( $context );
		$services = $this->getServiceContainer();
		return new PackageProtectAction(
			$article, $context->getTitle(), $services->get( 'Produnto.PackageProtectionStore' ),
			$services->getPermissionManager()
		);
	}

	public function testDisplayDoesNotIncrementRateLimit() {
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )->willReturn( $this->getTestSysop()->getUser() );
		$authority->expects( $this->once() )->method( 'isDefinitelyAllowed' )->with( 'protect' )->willReturn( true );
		$authority->expects( $this->never() )->method( 'authorizeAction' );
		$this->newAction( $authority )->show();
	}

	/** @dataProvider provideInfiniteExpiry */
	public function testInfiniteExpiry( string $expiry ) {
		$this->assertStatusGood( $this->newAction()->onSubmit( [
			'level' => 'sysop', 'expiry' => $expiry, 'reason' => '',
		] ) );
		$this->assertSame( 'infinity', $this->getServiceContainer()->get( 'Produnto.PackageProtectionStore' )
			->getProtection( 'Example' )['expiry'] );
	}

	public static function provideInfiniteExpiry() {
		return [ [ 'infinity' ], [ 'infinite' ], [ 'indefinite' ] ];
	}

	public function testSubmitRequiresPermission() {
		$this->assertStatusError( 'badaccess-groups',
			$this->newAction( $this->getTestUser()->getAuthority() )->onSubmit( [
				'level' => 'sysop', 'expiry' => 'infinity', 'reason' => '',
			] ) );
		$this->assertNull( $this->getServiceContainer()->get( 'Produnto.PackageProtectionStore' )
			->getProtection( 'Example' ) );
	}

	/** @dataProvider provideInvalidProtection */
	public function testInvalidProtection( string $level, string $expiry, string $error ) {
		$this->assertStatusError( $error, $this->newAction()->onSubmit( [
			'level' => $level, 'expiry' => $expiry, 'reason' => '',
		] ) );
		$this->assertNull( $this->getServiceContainer()->get( 'Produnto.PackageProtectionStore' )
			->getProtection( 'Example' ) );
	}

	public static function provideInvalidProtection() {
		return [
			'unknown level' => [ 'invalid-level', 'infinity', 'badaccess-group0' ],
			'expired' => [ 'sysop', '2000-01-01', 'protect_expiry_invalid' ],
			'invalid expiry' => [ 'sysop', 'not a time', 'protect_expiry_invalid' ],
		];
	}
}

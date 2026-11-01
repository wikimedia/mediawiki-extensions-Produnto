<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\RepoViewer;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\Produnto\RepoViewer\RepoViewerHooks;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Page\Article;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\RepoViewerHooks
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\PackageProtectAction
 * @group Database
 */
class RepoViewerHooksTest extends \MediaWikiIntegrationTestCase {

	private function runAction( RequestContext $context, string $action ) {
		$article = new Article( $context->getTitle() );
		$article->setContext( $context );
		$entryPoint = $this->createNoOpMock( ActionEntryPoint::class, [ 'getAction' ] );
		$entryPoint->method( 'getAction' )->willReturn( $action );
		$runner = new HookRunner( $this->getServiceContainer()->getHookContainer() );
		return $runner->onMediaWikiPerformAction(
			$context->getOutput(), $article, $context->getTitle(), $context->getUser(),
			$context->getRequest(), $entryPoint
		);
	}

	private function newContext( int $namespace, string $title ): RequestContext {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( $namespace, $title ) );
		$context->setUser( $this->getTestSysop()->getUser() );
		$context->setLanguage( 'en' );
		return $context;
	}

	/** @dataProvider provideAction */
	public function testAction( int $namespace, string $title, string $action, bool $handled ) {
		$context = $this->newContext( $namespace, $title );
		$this->assertSame( !$handled, $this->runAction( $context, $action ) );
		if ( $handled ) {
			$this->assertSame( '', $context->getOutput()->getRedirect() );
			$html = $context->getOutput()->getHTML();
			$this->assertStringContainsString( 'Deployment protection level', $html );
		}
	}

	public static function provideAction() {
		return [
			'package' => [ NS_PACKAGE, 'Example', 'protect', true ],
			'package file' => [ NS_PACKAGE, 'Example/file.lua', 'protect', true ],
			'package view' => [ NS_PACKAGE, 'Example', 'view', false ],
			'regular page' => [ NS_MAIN, 'Example', 'protect', false ],
			'package talk' => [ NS_PACKAGE_TALK, 'Example', 'protect', false ],
		];
	}

	public function testSubmitFromPackageFile() {
		$protection = $this->getServiceContainer()->get( 'Produnto.PackageProtectionStore' );
		$context = $this->newContext( NS_PACKAGE, 'Example/file.lua' );
		$request = new FauxRequest( [
			'wplevel' => 'sysop',
			'wpexpiry' => '1 week',
			'wpreason' => 'Protection test',
			'wppackage' => 'Other',
		], true );
		$context->setRequest( $request );
		$request->setVal( 'wpEditToken', (string)$context->getCsrfTokenSet()->getToken() );
		$this->runAction( $context, 'protect' );
		$this->assertSame( $context->getTitle()->getFullURL(), $context->getOutput()->getRedirect() );
		$current = $protection->getProtection( 'Example' );
		$this->assertSame( 'sysop', $current['level'] );
		$this->assertNull( $protection->getProtection( 'Other' ) );

		$context = $this->newContext( NS_PACKAGE, 'Example' );
		$this->runAction( $context, 'protect' );
		$html = $context->getOutput()->getHTML();
		$this->assertStringContainsString( 'Protection test', $html );
		$this->assertStringContainsString( '[Deploy=Allow only administrators]', $html );
	}

	public function testPackageFileProtectionLink() {
		$context = $this->newContext( NS_PACKAGE, 'Example/file.lua' );
		$services = $this->getServiceContainer();
		$protection = $services->get( 'Produnto.PackageProtectionStore' );
		$hooks = new RepoViewerHooks( $services->getPermissionManager(), $protection );
		$skin = $context->getSkin();
		$links = [];
		$hooks->onSkinTemplateNavigation__Universal( $skin, $links );
		$this->assertSame( 'Protect', $links['actions']['produnto-protect']['text'] );

		$protection->setProtection( $context->getUser(), 'Example', 'sysop', 'infinity', '' );
		$hooks->onSkinTemplateNavigation__Universal( $skin, $links );
		$this->assertSame( 'Change protection', $links['actions']['produnto-protect']['text'] );
	}

	public function testProtectRequiresPermission() {
		$context = $this->newContext( NS_PACKAGE, 'Example' );
		$context->setUser( $this->getTestUser()->getUser() );
		$this->expectException( PermissionsError::class );
		$this->runAction( $context, 'protect' );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\RepoViewer;

use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\RepoViewer\RepoPage;
use MediaWiki\Extension\Produnto\RepoViewer\RepoProvider;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Page\Article;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\RepoPage
 * @group Database
 */
class RepoPageTest extends \MediaWikiIntegrationTestCase {
	public static function provideGetContentForTransclusion() {
		return [
			'too large' => [
				[ 'content' => 'large' ],
				null
			],
			'binary' => [
				[ 'content' => 'binary' ],
				null,
			],
			'missing' => [
				[ 'dbKey' => 'Package1', 'content' => 'null' ],
				null
			],
			'dot wiki' => [
				[
					'dbKey' => 'Package1/test.wiki',
				],
				'test'
			],
			'needs NFC' => [
				[ 'content' => 'nfd' ],
				"<pre>\u{00E9}</pre>"
			],
			'default' => [
				[
					'dbKey' => 'Package1/test',
					'content' => 'test</pre>',
				],
				'<pre>test&lt;/pre&gt;</pre>'
			],
		];
	}

	/**
	 * @dataProvider provideGetContentForTransclusion
	 */
	public function testGetContentForTransclusion( array $options, ?string $expected ) {
		$page = $this->getPage( $options );
		$content = $page->getContentForTransclusion();
		if ( $expected === null ) {
			$this->assertNull( $content );
		} else {
			$this->assertInstanceOf( WikitextContent::class, $content );
			$this->assertSame( $expected, $content->getText() );
		}
	}

	public static function provideGetPreloadContent() {
		return [
			'too large' => [
				[ 'content' => 'large' ],
				null
			],
			'binary' => [
				[ 'content' => 'binary' ],
				null,
			],
			'missing' => [
				[ 'dbKey' => 'Package1', 'content' => 'null' ],
				null
			],
			'needs NFC' => [
				[ 'content' => 'nfd' ],
				"\u{00E9}"
			],
			'default' => [
				[],
				'test'
			],
		];
	}

	/**
	 * @dataProvider provideGetPreloadContent
	 */
	public function testGetPreloadContent( array $options, ?string $expected ) {
		$page = $this->getPage( $options );
		$content = $page->getPreloadContent();
		if ( $expected === null ) {
			$this->assertNull( $content );
		} else {
			$this->assertInstanceOf( TextContent::class, $content );
			$this->assertSame( $expected, $content->getText() );
		}
	}

	public static function provideGetView() {
		return [
			'too large' => [
				[ 'content' => 'large' ],
				[ 'exceeds the maximum' ]
			],
			'binary' => [
				[ 'content' => 'binary' ],
				[ 'not a text file' ]
			],
			'missing' => [
				[ 'dbKey' => 'Package1', 'content' => 'null' ],
				[ 'Add a README.wiki file' ]
			],
			'needs NFC' => [
				[ 'content' => 'nfd' ],
				[ "\u{00E9}" ]
			],
			'default' => [
				[],
				[
					'This file is from',
					'class="ext-produnto-viewer-file"'
				]
			],
			'index' => [
				[ 'dbKey' => 'Package1' ],
				[
					'1.0.0',
					'test'
				]
			],
			'valid URL' => [
				[
					'dbKey' => 'Package1',
					'collabUrl' => 'http://collab/',
				],
				[ 'href="http://collab/"' ]
			],
			'invalid URL' => [
				[
					'dbKey' => 'Package1',
					'collabUrl' => 'collab://collab/',
				],
				[ '<td>collab://collab/</td>' ]
			],
		];
	}

	/**
	 * @dataProvider provideGetView
	 */
	public function testGetView( array $options, array $expectedPatterns ) {
		$page = $this->getPage( $options );
		$parserOptions = ParserOptions::newFromAnon();
		$view = $page->getView( $parserOptions );
		$html = $view->getParserOutput()->getContentHolder()->getAsHtmlString();
		foreach ( $expectedPatterns as $pattern ) {
			$this->assertStringContainsString( $pattern, $html );
		}
	}

	public static function provideProtectionNotice() {
		return [
			'indefinite protection' => [ 'sysop', 'infinity', true ],
			'custom level and expiry' => [ 'reviewer', '20990101000000', true ],
			'expired protection' => [ 'sysop', '20000101000000', false ],
			'unprotected' => [ '', 'infinity', false ],
		];
	}

	/** @dataProvider provideProtectionNotice */
	public function testProtectionNotice( string $level, string $expiry, bool $visible ) {
		$this->overrideConfigValue( 'RestrictionLevels', [ '', 'sysop', 'reviewer' ] );
		$this->setGroupPermissions( 'sysop', 'reviewer', true );
		$protection = $this->getServiceContainer()->get( 'Produnto.PackageProtectionStore' );
		$authority = $this->getTestSysop()->getAuthority();
		$protection->setProtection( $authority->getUser(), 'package1', $level, 'infinity', '' );
		if ( $expiry !== 'infinity' ) {
			$this->getDb()->newUpdateQueryBuilder()->update( 'protected_titles' )
				->set( [ 'pt_expiry' => $expiry ] )->where( [ 'pt_namespace' => NS_PACKAGE ] )
				->caller( __METHOD__ )->execute();
		}
		$this->getPage( [ 'dbKey' => 'Package1' ] );
		$title = Title::makeTitle( NS_PACKAGE, 'Package1' );
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setLanguage( 'en' );
		$article = new Article( $title );
		$article->setContext( $context );
		$article->showMissingArticle();
		$html = $context->getOutput()->getHTML();
		$this->assertStringContainsString( 'ext-produnto-viewer-prop-table', $html );
		$this->assertStringNotContainsString( 'This page does not exist', $html );
		if ( $visible ) {
			$this->assertStringContainsString( 'ext-produnto-viewer-protected', $html );
			$this->assertStringContainsString( "level: $level", $html );
			$this->assertStringContainsString( $expiry === 'infinity' ? 'indefinite' : '(UTC)', $html );
		} else {
			$this->assertStringNotContainsString( 'ext-produnto-viewer-protected', $html );
		}
	}

	private function getPage( array $options ): RepoPage {
		$content = $options['content'] ?? 'test';
		$content = match ( $content ) {
			'large' => str_repeat( 'x', 2_000_000 ),
			'binary' => "\000",
			'nfd' => "e\u{0301}",
			'null' => null,
			default => $content
		};
		$title = PageReferenceValue::localReference(
			NS_PACKAGE, $options['dbKey'] ?? 'Package1/file1' );
		$files = $content === null ? [] : [
			1 => [
				'file1' => $content,
				'README.wiki' => $content,
				'test.wiki' => $content,
				'test' => $content,
			]
		];
		$fileAccess = new SimpleFileAccess( $files );
		$package = new PackageAccess(
			$fileAccess,
			1,
			'package1',
			'1.0.0',
			'refs/tags/v1.0.0',
			'http://example.com/package1',
			[
				'collab-url' => $options['collabUrl'] ?? null
			],
			ProduntoStore::STATE_READY,
			null
		);
		$deployment = new DeploymentAccess(
			$fileAccess,
			$this->createNoOpMock( IReadableDatabase::class ),
			1, '', 1, [],
			[ 1 => $package ]
		);
		$store = $this->createMock( ProduntoStore::class );
		$store->method( 'getActiveDeployment' )
			->willReturn( $deployment );
		$this->setService( 'Produnto.Store', $store );
		return $this->getServiceContainer()->getShadowPageLoader()
			->getExtensionProvider( RepoProvider::class )
			->get( $title );
	}
}

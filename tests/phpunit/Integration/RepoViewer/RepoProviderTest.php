<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\RepoViewer;

use MediaWiki\Extension\Produnto\RepoViewer\RepoProvider;
use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Title\TitleValue;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\RepoProvider
 */
class RepoProviderTest extends \MediaWikiIntegrationTestCase {

	public static function provideGet() {
		return [
			'no active deployment' => [
				[ 'hasActiveDeployment' => false ],
				'Package1',
				null
			],
			'no such package' => [
				[],
				'Nonexistent',
				null
			],
			'index with readme' => [
				[],
				'Package1',
				[
					'isIndex' => true,
					'path' => 'README.wiki',
					'contents' => 'readme'
				]
			],
			'index with no readme' => [
				[ 'hasReadme' => false ],
				'Package1',
				[
					'isIndex' => true,
					'path' => 'README.wiki',
					'contents' => null
				]
			],
			'no such file' => [
				[],
				'Package1/nonexistent',
				null
			],
			'existing file' => [
				[],
				'Package1/file1',
				[
					'isIndex' => false,
					'path' => 'file1',
					'contents' => 'contents1'
				]
			],
		];
	}

	/**
	 * @dataProvider provideGet
	 */
	public function testGet( array $options, string $dbKey, ?array $expected ) {
		$provider = $this->createProvider( $options );
		$title = $this->getTitle( $dbKey );
		$page = $provider->get( $title );
		if ( $expected === null ) {
			$this->assertNull( $page );
		} else {
			$this->assertNotNull( $page );
			$tpage = TestingAccessWrapper::newFromObject( $page );
			$this->assertSame( $title, $tpage->title );
			foreach ( $expected as $prop => $value ) {
				$this->assertSame( $tpage->$prop, $value, $prop );
			}
		}
	}

	public static function provideExistsForLink() {
		return [
			'no active deployment' => [
				[ 'hasActiveDeployment' => false ],
				'Package1',
				false
			],
			'no such package' => [
				[],
				'Nonexistent',
				false
			],
			'index with readme' => [
				[],
				'Package1',
				true
			],
			'index with no readme' => [
				[ 'hasReadme' => false ],
				'Package1',
				true
			],
			'no such file' => [
				[],
				'Package1/nonexistent',
				false
			],
			'existing file' => [
				[],
				'Package1/file1',
				true
			],
		];
	}

	/**
	 * @dataProvider provideExistsForLink
	 */
	public function testExistsForLink( array $options, string $dbKey, bool $expected ) {
		$provider = $this->createProvider( $options );
		$title = $this->getLinkTarget( $dbKey );
		$this->assertSame( $expected, $provider->existsForLink( $title ) );
	}

	private function getTitle( string $dbKey ): PageReference {
		return PageReferenceValue::localReference( NS_PACKAGE, $dbKey );
	}

	private function getLinkTarget( string $dbKey ): LinkTarget {
		return new TitleValue( NS_PACKAGE, $dbKey );
	}

	private function createProvider( array $options ): RepoProvider {
		$store = $this->createMock( ProduntoStore::class );
		if ( $options['hasActiveDeployment'] ?? true ) {
			$files = [
				1 => [
					'file1' => 'contents1'
				]
			];
			if ( $options['hasReadme'] ?? true ) {
				$files[1]['README.wiki'] = 'readme';
			}
			$fileAccess = new SimpleFileAccess( $files );
			$package = new PackageAccess(
				$fileAccess,
				1,
				'package1',
				'', '', '', [],
				ProduntoStore::STATE_READY, null
			);
			$deployment = new DeploymentAccess(
				$fileAccess,
				$this->createNoOpMock( IReadableDatabase::class ),
				1,
				[],
				[ 1 => $package ]
			);
			$store->method( 'getActiveDeployment' )
				->willReturn( $deployment );
		}
		$this->setService( 'Produnto.Store', $store );
		return $this->getServiceContainer()->getShadowPageLoader()
			->getExtensionProvider( RepoProvider::class );
	}
}

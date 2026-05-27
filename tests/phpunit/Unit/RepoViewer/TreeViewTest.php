<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\RepoViewer;

use MediaWiki\Extension\Produnto\RepoViewer\TreeView;
use MediaWiki\Html\Html;

/**
 * @covers \MediaWiki\Extension\Produnto\RepoViewer\TreeView
 */
class TreeViewTest extends \MediaWikiUnitTestCase {
	public static function provideGetHtml() {
		return [
			'empty' => [
				[],
				'<ul></ul>'
			],
			'single level' => [
				[ 'file' ],
				'<ul><li style="margin-left: 0px"><a href="file">file</a></li></ul>'
			],
			'two levels' => [
				[ 'dir/file' ],
				'<ul>' .
				'<li class="ext-produnto-viewer-folder" style="margin-left: 0px">dir</li>' .
				'<li style="margin-left: 16px"><a href="dir/file">file</a></li>' .
				'</ul>'
			],
			'sorting' => [
				[
					'b/a',
					'a'
				],
				'<ul>' .
				'<li style="margin-left: 0px"><a href="a">a</a></li>' .
				'<li class="ext-produnto-viewer-folder" style="margin-left: 0px">b</li>' .
				'<li style="margin-left: 16px"><a href="b/a">a</a></li>' .
				'</ul>'
			]
		];
	}

	/**
	 * @dataProvider provideGetHtml
	 * @param array $paths
	 * @param string $expected
	 */
	public function testGetHtml( $paths, $expected ) {
		$treeView = new TreeView;
		$treeView->paths( $paths )
			->leafLinker( static function ( $path, $label ) {
				return Html::rawElement( 'a', [ 'href' => $path ], $label );
			} );
		$html = $treeView->getHtml();
		$this->assertSame( $expected, $html );
	}
}

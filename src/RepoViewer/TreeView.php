<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Html\Html;

/**
 * A static HTML tree view formatter for showing a list of files in a package
 */
class TreeView {
	/** @var string[] */
	private $paths;

	/** @var callable(string,string):(string)|null */
	private $leafLinker;

	/**
	 * Set the file paths. These must have slash-separated names.
	 *
	 * @param string[] $paths
	 * @return $this
	 */
	public function paths( array $paths ): self {
		$this->paths = $paths;
		return $this;
	}

	/**
	 * Set a callback to wrap leaf nodes (files) of the tree.
	 *
	 * @param callable(string,string):(string) $linker The parameters to the
	 *   callback are the file path and the HTML-escaped final component of
	 *   the path, which can be used as a link label. The callback can
	 *   wrap the label and return the HTML.
	 * @return $this
	 */
	public function leafLinker( $linker ) {
		$this->leafLinker = $linker;
		return $this;
	}

	/**
	 * Render the tree view.
	 *
	 * @return string HTML
	 */
	public function getHtml() {
		$paths = $this->toRecursive( $this->paths );
		$this->sort( $paths );
		$html = '';
		$this->doPath( $html, '', $paths, 0 );
		return Html::rawElement( 'ul', [], $html );
	}

	/**
	 * Convert an array of slash-separated paths to a recursive array
	 * where the component names are in the keys, and the value is
	 * either the child path array or true for a leaf node.
	 *
	 * @param array $paths
	 * @return array
	 */
	private function toRecursive( array $paths ) {
		$root = [];
		foreach ( $paths as $path ) {
			$dir =& $root;
			$components = explode( '/', $path );
			$n = count( $components );
			for ( $i = 0; $i < $n - 1; $i++ ) {
				$component = $components[$i];
				if ( !isset( $dir[$component] ) ) {
					$dir[$component] = [];
				}
				$dir =& $dir[$component];
			}
			$dir[$components[$n - 1]] = true;
		}
		return $root;
	}

	/**
	 * Sort a recursive array
	 *
	 * @param array &$paths
	 */
	private function sort( array &$paths ) {
		foreach ( $paths as &$child ) {
			if ( is_array( $child ) ) {
				$this->sort( $child );
			}
		}
		ksort( $paths );
	}

	/**
	 * Recursively render a directory
	 *
	 * @param string &$html
	 * @param string $basePath
	 * @param array $curPaths
	 * @param int $indent
	 */
	private function doPath( &$html, $basePath, $curPaths, $indent ) {
		$indentPx = $indent * 16;
		foreach ( $curPaths as $component => $children ) {
			$fullPath = $basePath === '' ? $component : $basePath . '/' . $component;
			$labelHtml = htmlspecialchars( $component, ENT_NOQUOTES );
			if ( $children === true ) {
				$linkHtml = $this->leafLinker ? ( $this->leafLinker )( $fullPath, $labelHtml ) : null;
				if ( $linkHtml === null ) {
					$linkHtml = $labelHtml;
				}
				$html .= Html::rawElement(
					'li',
					[ 'style' => "margin-left: {$indentPx}px" ],
					$linkHtml
				);
			} else {
				$html .= Html::rawElement(
					'li',
					[
						'class' => 'ext-produnto-viewer-folder',
						'style' => "margin-left: {$indentPx}px"
					],
					$labelHtml
				);
				$this->doPath( $html, $fullPath, $children, $indent + 1 );
			}
		}
	}

}

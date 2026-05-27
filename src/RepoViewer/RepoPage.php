<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Content\Content;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Html\Html;
use MediaWiki\Language\LanguageFactory;
use MediaWiki\Language\LanguageFallback;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Message\MessageFormatterFactory;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\ShadowPage\BaseShadowPage;
use MediaWiki\ShadowPage\ParseHelper;
use MediaWiki\ShadowPage\ShadowPageView;
use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use MediaWiki\Xml\Xml;
use UtfNormal\Validator;
use Wikimedia\HtmlArmor\HtmlArmor;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Message\MessageValue;
use Wikimedia\Message\ParamType;
use Wikimedia\Message\ScalarParam;

/**
 * Shadow page for pages in the Package namespace
 */
class RepoPage extends BaseShadowPage {
	private const VIEWER_MAX_LENGTH = 1024 * 1024;

	public function __construct(
		private LanguageFallback $fallbackProvider,
		private MessageFormatterFactory $messageFormatterFactory,
		private LanguageFactory $languageFactory,
		private TitleParser $titleParser,
		private LinkRenderer $linkRenderer,
		private ?SyntaxHighlight $syntaxHighlight,
		private ParseHelper $parseHelper,
		private bool $isIndex,
		private PageReference $title,
		private PackageAccess $package,
		private string $path,
		private ?string $contents
	) {
	}

	public function getContentForTransclusion(): ?Content {
		if ( $this->isTooLarge() || !$this->isUtf8() || $this->contents === null ) {
			return null;
		}
		$ext = $this->getExtension();
		if ( $ext === 'wiki' ) {
			// The condition could be relaxed to provide a global templates
			// feature, although this interface doesn't provide link tracking
			$text = $this->getNormalizedText();
		} elseif ( $this->syntaxHighlight?->isSupportedLanguage( $ext )
			&& !str_contains( $this->contents, '</syntaxhighlight' )
		) {
			$text =
				Xml::openElement( 'syntaxhighlight', [ 'lang' => $ext ] ) .
				$this->getNormalizedText() .
				Xml::closeElement( 'syntaxhighlight' );
		} else {
			$text = Xml::element( 'pre', [], $this->getNormalizedText() );
		}
		return new WikitextContent( $text );
	}

	/**
	 * This is just for the "view source" action
	 */
	public function getPreloadContent(): ?Content {
		if ( $this->isTooLarge() || !$this->isUtf8() || $this->contents === null ) {
			return null;
		}
		// TODO: make syntax highlighting work
		// Just returning ScribuntoContent here doesn't seem to be enough to
		// enable CodeMirror syntax highlighting.
		return new TextContent( $this->getNormalizedText() );
	}

	public function getView( ParserOptions $parserOptions ): ?ShadowPageView {
		$textFormatter = $this->messageFormatterFactory->getTextFormatter(
			$parserOptions->getUserLang() );

		$view = $this->parseHelper->newFromHtml( '', $parserOptions );
		$view->getParserOptions()->setWrapOutputClass( 'ext-produnto-viewer' );
		if ( $this->contents === null ) {
			$fileViewHtml = $this->getFilePlaceholder( $textFormatter, 'produnto-viewer-no-readme' );
		} elseif ( $this->isTooLarge() ) {
			$fileViewHtml = $this->getFilePlaceholder( $textFormatter,
				new MessageValue( 'produnto-viewer-too-large', [
					new ScalarParam( ParamType::SIZE, strlen( $this->contents ) ),
					new ScalarParam( ParamType::SIZE, self::VIEWER_MAX_LENGTH )
				] )
			);
		} elseif ( !$this->isUtf8() ) {
			$fileViewHtml = $this->getFilePlaceholder( $textFormatter, 'produnto-viewer-binary' );
		} else {
			$fileViewHtml = $this->getFileView( $parserOptions, $view );
		}

		if ( $this->isIndex ) {
			$header = $this->getIndexHeader( $textFormatter );
		} else {
			$header = $this->getFileInfoHeader( $textFormatter );
		}

		$view->getParserOutput()->getContentHolder()->appendHtmlString(
			$header .
			Html::rawElement(
				'div',
				[ 'class' => 'ext-produnto-viewer-file-container' ],
				$fileViewHtml . $this->getListing()
			)
		);
		$view->getParserOutput()->addModuleStyles( [ 'ext.produnto.RepoViewer' ] );

		return $view;
	}

	/**
	 * Check if the contents is valid HTML
	 * @return true
	 */
	private function isUtf8() {
		return $this->contents !== null
			&& mb_check_encoding( $this->contents, 'UTF-8' )
			&& preg_match( '/^[^\x00-\x08\x0e-\x1f\x7f]*$/', $this->contents );
	}

	/**
	 * Check if the contents is too large to be parsed by SyntaxHighlight
	 * @return bool
	 */
	private function isTooLarge() {
		return $this->contents !== null
			&& strlen( $this->contents ) > self::VIEWER_MAX_LENGTH;
	}

	/**
	 * Get the file extension
	 * @return string
	 */
	private function getExtension() {
		if ( preg_match( '!\.([^/.]+)$!', $this->path, $m ) ) {
			return $m[1];
		} else {
			return '';
		}
	}

	/**
	 * Get the header to be used on the package index page
	 *
	 * @param ITextFormatter $textFormatter
	 * @return string
	 */
	private function getIndexHeader( ITextFormatter $textFormatter ) {
		$p = $this->package;
		$langCode = $textFormatter->getLangCode();
		$lang = $this->languageFactory->getLanguage( $langCode );
		$data = [
			'package' => $p->getName(),
			'version' => $p->getVersion(),
			'name' => $p->getLocalName( $langCode, $this->fallbackProvider ),
			'description' => $p->getDescription( $langCode, $this->fallbackProvider ),
			'authors' => $lang->commaList( $p->getAuthors() ),
			'license' => $p->getLicense(),
			'fetched-url' => $p->getFetchedUrl(),
			'type' => $p->getType(),
			'homepage-url' => $p->getHomepageUrl(),
			'doc-url' => $p->getDocUrl(),
			'collab-url' => $p->getCollabUrl(),
			'issue-url' => $p->getIssueUrl(),
			'requires' => $p->getRequires()
		];

		$rows = '';
		foreach ( $data as $name => $value ) {
			if ( $value === null ) {
				$formattedValue = null;
			} elseif ( str_ends_with( $name, '-url' ) ) {
				$formattedValue = Html::linkButton( $value, [ 'href' => $value ] );
			} elseif ( $name === 'requires' && $value !== [] ) {
				$parts = [];
				foreach ( $value as $package => $constraint ) {
					$parts[] = "$package $constraint";
				}
				$formattedValue = $lang->commaList( $parts );
			} elseif ( is_string( $value ) ) {
				$formattedValue = htmlspecialchars( $value, ENT_NOQUOTES );
			} else {
				$formattedValue = null;
			}
			if ( $formattedValue !== null ) {
				$nameMsg = new MessageValue( 'produnto-viewer-index-' . $name );
				$rows .= Html::openElement( 'tr' ) .
					Html::element( 'td', [], $textFormatter->format( $nameMsg ) ) .
					Html::rawElement( 'td', [], $formattedValue ) .
					Html::closeElement( 'tr' ) . "\n";
			}
		}
		return "<table class='ext-produnto-viewer-prop-table'><tbody>$rows</tbody></table>";
	}

	/**
	 * Get the notice box at the top of the page notifying the user that the
	 * page is from a package.
	 *
	 * @param ITextFormatter $textFormatter
	 * @return string HTML
	 */
	private function getFileInfoHeader( ITextFormatter $textFormatter ) {
		$langCode = $textFormatter->getLangCode();
		$name = $this->package->getLocalName( $langCode, $this->fallbackProvider );
		$info = $textFormatter->format( new MessageValue( 'produnto-viewer-package-info' ) );
		$infoHtml = str_replace(
			'$1',
			Html::element( 'strong', [], $name ),
			htmlspecialchars( $info, ENT_NOQUOTES )
		);
		$html = Html::rawElement(
			'div',
			[ 'class' => 'ext-produnto-viewer-info-text' ],
			$infoHtml
		);
		$url = $this->package->getCollabUrl();
		if ( $url ) {
			$html .= ' ' .
			Html::rawElement(
				'div',
				[ 'class' => 'ext-produnto-viewer-collab-link' ],
				Html::linkButton(
					$textFormatter->format( new MessageValue( 'produnto-viewer-collab-link' ) ),
					[ 'href' => $url ]
				)
			);
		}
		$html = Html::rawElement( 'div', [ 'class' => 'ext-produnto-viewer-info' ], $html );
		return Html::noticeBox( $html );
	}

	/**
	 * Get the file listing
	 * @return string HTML
	 */
	private function getListing() {
		$baseLinkTarget = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $this->package->getName() );
		$baseDbKey = $baseLinkTarget->getDBkey() . '/';
		$treeView = ( new TreeView )
			->paths( $this->package->getFilePaths() )
			->leafLinker( function ( $basePath, $labelHtml ) use ( $baseDbKey ) {
				$target = $this->makeRepoViewLinkTarget( $baseDbKey . $basePath );
				if ( !$this->isIndex && $target && $basePath === $this->path ) {
					return Linker::makeSelfLinkObj( $target, $labelHtml );
				}
				if ( $target ) {
					return $this->linkRenderer->makeKnownLink(
						$target,
						new HtmlArmor( $labelHtml )
					);
				} else {
					return $labelHtml;
				}
			} );
		return Html::rawElement(
			'div',
			[ 'class' => 'ext-produnto-viewer-listing' ],
			$treeView->getHtml()
		);
	}

	/**
	 * Try to convert a DB key in the Package namespace to a LinkTarget
	 * @param string $dbKey
	 * @return LinkTarget|null
	 */
	private function makeRepoViewLinkTarget( $dbKey ): ?LinkTarget {
		try {
			$target = $this->titleParser->makeTitleValueSafe( NS_PACKAGE, $dbKey );
		} catch ( MalformedTitleException ) {
			return null;
		}

		if ( $target->getDBkey() === $dbKey ) {
			return $target;
		} else {
			return null;
		}
	}

	/**
	 * Get the file view HTML
	 *
	 * @param ParserOptions $parserOptions
	 * @param ShadowPageView $view
	 * @return string HTML
	 */
	private function getFileView( ParserOptions $parserOptions, ShadowPageView $view ) {
		$fileView = $this->parseHelper->getParsedContentView(
			$this->getContentForTransclusion() ?? '',
			$this->title,
			$parserOptions
		);
		$fileView->getParserOutput()->collectMetadata( $view->getParserOutput() );
		return Html::rawElement(
			'div',
			[ 'class' => 'ext-produnto-viewer-file' ],
			$fileView->getParserOutput()->getContentHolderText()
		);
	}

	/**
	 * Get the file view container but with a warning message instead of the actual contents
	 *
	 * @param ITextFormatter $textFormatter
	 * @param MessageSpecifier|string $message
	 * @return string HTML
	 */
	private function getFilePlaceholder( ITextFormatter $textFormatter, MessageSpecifier|string $message ) {
		if ( !( $message instanceof MessageSpecifier ) ) {
			$message = new MessageValue( $message );
		}
		return Html::rawElement(
			'div',
			[ 'class' => 'ext-produnto-viewer-file' ],
			Html::warningBox(
				htmlspecialchars( $textFormatter->format( $message ), ENT_NOQUOTES )
			)
		);
	}

	/**
	 * Get the file contents, normalized to normal form C as is conventional for
	 * text on the wiki.
	 */
	private function getNormalizedText(): string {
		return TextContent::normalizeLineEndings(
			Validator::cleanUp( $this->contents ?? '' ) );
	}
}

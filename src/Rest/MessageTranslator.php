<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Message\MessageFormatterFactory;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageSpecifier;

/**
 * Message formatting helper for REST API handlers
 */
class MessageTranslator {
	/** @var ITextFormatter[] */
	private array $textFormatters;

	/**
	 * @param Language $contLang A language carrying a code which will be
	 *   included in the translations
	 * @param MessageFormatterFactory $formatterFactory
	 * @param bool $useUserLang Whether to use the request context language
	 * @param bool $useBcp47 If true, the keys will be BCP 47 codes. If false,
	 *   MediaWiki codes will be used.
	 * @param array<int,string> $paramInterpretations A map from the message
	 *   parameter index to the name of the resulting key in the message info
	 *   arrays. If a message has a parameter which is present in this array,
	 *   the parameter value will be placed in the corresponding result property.
	 */
	public function __construct(
		Language $contLang,
		MessageFormatterFactory $formatterFactory,
		bool $useUserLang,
		bool $useBcp47,
		private array $paramInterpretations = []
	) {
		$langCodes = [
			$contLang->getCode(),
			'en'
		];
		if ( $useUserLang ) {
			$langCodes[] = RequestContext::getMain()->getLanguage()->getCode();
		}
		$this->textFormatters = [];
		foreach ( array_unique( $langCodes ) as $langCode ) {
			$resultCode = $useBcp47 ? LanguageCode::bcp47( $langCode ) : $langCode;
			$this->textFormatters[$resultCode] = $formatterFactory->getTextFormatter( $langCode );
		}
	}

	/**
	 * Format a single message as an associative array
	 *
	 * @param MessageSpecifier $message
	 * @return array{key:string,translations:array<string,string>}
	 */
	public function formatMessage( MessageSpecifier $message ) {
		return $this->formatMessages( [ $message ] )[0];
	}

	/**
	 * Format messages as an array for returning via the REST API
	 *
	 * @param MessageSpecifier[] $messages
	 * @return array
	 */
	public function formatMessages( array $messages ) {
		$results = [];
		foreach ( $messages as $message ) {
			$translations = [];
			foreach ( $this->textFormatters as $lang => $formatter ) {
				$translations[$lang] = $formatter->format( $message );
			}
			$formatted = [ 'key' => $message->getKey(), 'translations' => $translations ];

			$params = $message->getParams();
			foreach ( $this->paramInterpretations as $index => $name ) {
				if ( isset( $params[$index] ) ) {
					$formatted[$name] = $params[$index]->getValue();
				}
			}
			$results[] = $formatted;
		}
		return $results;
	}
}

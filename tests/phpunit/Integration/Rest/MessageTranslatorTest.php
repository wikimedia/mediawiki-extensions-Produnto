<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\Rest\MessageTranslator;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\MessageTranslator
 */
class MessageTranslatorTest extends \MediaWikiIntegrationTestCase {

	public static function provideFormatMessage() {
		return [
			'defaults' => [
				[],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p'
					]
				]
			],
			'with content language' => [
				[ 'contLang' => 'fr' ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'fr' => 'FR p',
						'en' => 'EN p',
					]
				]
			],
			'user language' => [
				[ 'userLang' => 'de', 'useUserLang' => true ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p',
						'de' => 'DE p',
					]
				]
			],
			'unused user language' => [
				[ 'userLang' => 'de' ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p',
					]
				]
			],
			'mapped code' => [
				[ 'userLang' => 'map-bms', 'useBcp47' => true, 'useUserLang' => true ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p',
						'jv-x-bms' => 'MAP p',
					]
				]
			],
			'unmapped code' => [
				[ 'userLang' => 'map-bms', 'useUserLang' => true ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p',
						'map-bms' => 'MAP p',
					]
				]
			],
			'interpretation' => [
				[ 'paramInterpretations' => [ 0 => 'param' ] ],
				[
					'key' => 'produnto-message-test',
					'translations' => [
						'en' => 'EN p'
					],
					'param' => 'p'
				]
			],
		];
	}

	/**
	 * @dataProvider provideFormatMessage
	 * @param array $opts Associative array:
	 *   - contLang: The content language code (default en)
	 *   - userLang: The user language code (default en)
	 *   - useUserLang: Whether to use the user language (default false)
	 *   - useBcp47: Whether to use BCP 47 codes (default false)
	 *   - paramInterpretations: Interpretations map
	 * @param array $expected Formatted message
	 */
	public function testFormatMessage( $opts, $expected ) {
		$services = $this->getServiceContainer();
		$lc = $this->getServiceContainer()->getLocalisationCache();
		$lc->setSubitemForTesting( 'en', 'messages', 'produnto-message-test', 'EN $1' );
		$lc->setSubitemForTesting( 'fr', 'messages', 'produnto-message-test', 'FR $1' );
		$lc->setSubitemForTesting( 'de', 'messages', 'produnto-message-test', 'DE $1' );
		$lc->setSubitemForTesting( 'map-bms', 'messages', 'produnto-message-test', 'MAP $1' );

		$opts += [
			'contLang' => 'en',
			'userLang' => 'en',
			'useUserLang' => false,
			'useBcp47' => false,
			'paramInterpretations' => [],
		];
		$langFactory = $services->getLanguageFactory();

		RequestContext::getMain()->setLanguage( $langFactory->getLanguage( $opts['userLang'] ) );

		$translator = new MessageTranslator(
			$langFactory->getLanguage( $opts['contLang'] ),
			$services->getMessageFormatterFactory(),
			$opts['useUserLang'],
			$opts['useBcp47'],
			$opts['paramInterpretations'],
		);
		$message = MessageValue::newFromJsonArray( [ 'key' => 'produnto-message-test', 'params' => [ 'p' ] ] );
		$result = $translator->formatMessage( $message );
		$this->assertSame( $expected, $result );
	}

	public function testFormatMessages() {
		$services = $this->getServiceContainer();
		$lc = $this->getServiceContainer()->getLocalisationCache();
		$lc->setSubitemForTesting( 'en', 'messages', 'produnto-message-test-1', '1' );
		$lc->setSubitemForTesting( 'en', 'messages', 'produnto-message-test-2', '2' );
		$langFactory = $services->getLanguageFactory();

		$translator = new MessageTranslator(
			$langFactory->getLanguage( 'en' ),
			$services->getMessageFormatterFactory(),
			false,
			false,
			[],
		);
		$result = $translator->formatMessages( [
			wfMessage( 'produnto-message-test-1' ),
			wfMessage( 'produnto-message-test-2' )
		] );
		$this->assertSame(
			[
				[ 'key' => 'produnto-message-test-1', 'translations' => [ 'en' => '1' ] ],
				[ 'key' => 'produnto-message-test-2', 'translations' => [ 'en' => '2' ] ],
			],
			$result
		);
	}
}

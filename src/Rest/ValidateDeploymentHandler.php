<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Language\Language;
use MediaWiki\Message\MessageFormatterFactory;
use MediaWiki\Rest\Handler;

class ValidateDeploymentHandler extends Handler {
	public function __construct(
		private Updater $updater,
		private Language $contLang,
		private MessageFormatterFactory $formatterFactory,
	) {
	}

	/** @inheritDoc */
	public function execute() {
		$jsonString = $this->getRequest()->getBody()->getContents();
		$data = json_decode( $jsonString );
		$status = $this->updater->validateDeployment( $data );
		$messageTranslator = new MessageTranslator(
			$this->contLang,
			$this->formatterFactory,
			useUserLang: true,
			// Codes are for client-side comparison with MW language codes
			useBcp47: false,
			paramInterpretations: [ 0 => 'package' ]
		);
		return [
			'ok' => $status->isOK(),
			'warnings' => $messageTranslator->formatMessages( $status->getMessages( 'warning' ) ),
			'errors' => $messageTranslator->formatMessages( $status->getMessages( 'error' ) ),
		];
	}

	protected function getRequestBodySchema( string $mediaType ): array {
		return [
			'type' => 'object',
			'description' => 'Map of package names to versions. All packages to be deployed ' .
				'must be included, including packages which will be unchanged.',
			'additionalProperties' => [
				'type' => 'string'
			],
			'example' => [ 'some-package' => '1.0' ],
		];
	}

	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'properties' => [
				'ok' => [
					'type' => 'boolean',
					'description' => 'This will be true if there were no errors, even if there ' .
						'were warnings. Warnings are significant and need to be acknowledged by ' .
						'the user before deployment.'
				],
				'warnings' => [
					'type' => 'array',
					'description' => 'An array of warnings. This should be empty before ' .
						'proceeding with an automated deployment.',
					'items' => Schema::MESSAGE,
				],
				'errors' => [
					'type' => 'array',
					'description' => 'An array of errors.',
					'items' => Schema::MESSAGE,
				],
			]
		];
	}
}

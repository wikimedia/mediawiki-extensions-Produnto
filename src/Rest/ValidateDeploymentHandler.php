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
}

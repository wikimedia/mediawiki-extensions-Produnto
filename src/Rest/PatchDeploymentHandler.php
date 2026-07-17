<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Updater\AuthorizingPageSaverFactory;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Extension\Produnto\Updater\UpdateStatus;
use MediaWiki\Language\Language;
use MediaWiki\Message\MessageFormatterFactory;
use MediaWiki\Page\PageStore;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use MediaWiki\Storage\PageUpdaterFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Timestamp\TimestampFormat;

class PatchDeploymentHandler extends Handler {
	use TokenAwareHandlerTrait;

	public function __construct(
		private Language $contLang,
		private MessageFormatterFactory $formatterFactory,
		private PageUpdaterFactory $pageUpdaterFactory,
		private PageStore $pageStore,
		private AuthorizingPageSaverFactory $pageSaverFactory,
		private Updater $updater,
	) {
	}

	/** @inheritDoc */
	public function execute() {
		$params = $this->getValidatedBodyArray();
		$status = $this->pageSaverFactory->create( $this->getAuthority() )
			->ignoreWarnings( $params['ignoreWarnings'] ?? false )
			->patchJson( $params['packages'] )
			->summary( $params['summary'] ?? '' )
			->save();
		$messageTranslator = new MessageTranslator(
			$this->contLang,
			$this->formatterFactory,
			useUserLang: true,
			// Codes are for client-side comparison with MW language codes
			useBcp47: false,
		);
		$res = [
			'ok' => $status->isOK(),
			'warnings' => $messageTranslator->formatMessages( $status->getMessages( 'warning' ) ),
			'errors' => $messageTranslator->formatMessages( $status->getMessages( 'error' ) ),
		];
		if ( $status instanceof UpdateStatus && $status->isOK() ) {
			$deployment = $status->getDeployment();
			$revision = $status->getRevision();

			// Same object shape as RecentDeploymentsHandler
			$res['deployment'] = [
				'id' => $deployment->getId(),
				'controlWiki' => $deployment->getControlWikiId(),
				'revision' => $revision->getId(),
				'active' => true,
				'packages' => $status->getData(),
				'userText' => $revision->getUser()->getName(),
				'timestamp' => wfTimestamp( TimestampFormat::ISO_8601, $revision->getTimestamp() ),
				'summary' => $revision->getComment()->text,
			];
		}
		return $res;
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'packages' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'JSON serialized object mapping package names to ' .
					'versions. If a deployed package is absent, it will remain unchanged. ' .
					'If a version is null or the empty string, that package will be undeployed.',
				self::PARAM_EXAMPLE => '{"some-package":"1.0"}',
			],
			'summary' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'A comment for the revision.',
				self::PARAM_EXAMPLE => 'Updated some-package from 1.0 to 2.0',
			],
			'ignoreWarnings' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Whether to ignore validation warnings, ' .
					'such as package requirements not satisfied.',
			],
		] + $this->getTokenParamDefinition();
	}

	/** @inheritDoc */
	public function getSupportedRequestTypes(): array {
		return RequestInterface::FORM_DATA_CONTENT_TYPES;
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'required' => [ 'ok' ],
			'properties' => [
				'ok' => [
					'type' => 'boolean',
					'description' => 'Whether the deployment was successful',
				],
				'warnings' => [
					'type' => 'array',
					'description' => 'Validation warnings',
					'items' => Schema::MESSAGE,
				],
				'errors' => [
					'type' => 'array',
					'description' => 'Fatal errors',
					'items' => Schema::MESSAGE,
				],
				'deployment' => Schema::DEPLOYMENT,
			]
		];
	}
}

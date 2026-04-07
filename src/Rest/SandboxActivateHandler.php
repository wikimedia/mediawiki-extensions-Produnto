<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use stdClass;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Activate a sandbox in the context of the current session. Use it for subsequent previews.
 */
class SandboxActivateHandler extends Handler {
	use TokenAwareHandlerTrait;

	public function __construct(
		private SandboxStore $store
	) {
	}

	/** @inheritDoc */
	public function execute() {
		$userId = $this->getAuthority()->getUser()->getId();
		if ( !$userId ) {
			return $this->getResponseFactory()
				->createHttpError( 403, [ 'message' => 'Login required' ] );
		}

		$params = $this->getValidatedParams();
		$sandboxId = $params['id'];
		$sandboxes = $this->store->getSandboxNames( $userId );
		if ( !in_array( $sandboxId, $sandboxes ) ) {
			return $this->getResponseFactory()->createHttpError(
				404,
				[ 'message' => 'no such ID' ]
			);
		}
		$this->getSession()->set( 'ProduntoSandbox', $sandboxId );
		return new stdClass();
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return $this->getTokenParamDefinition();
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

}

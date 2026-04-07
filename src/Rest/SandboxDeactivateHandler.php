<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use stdClass;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Deactivate a sandbox in the context of the current session. Stop using it for previews.
 */
class SandboxDeactivateHandler extends Handler {
	use TokenAwareHandlerTrait;

	/** @inheritDoc */
	public function execute() {
		$userId = $this->getAuthority()->getUser()->getId();
		if ( !$userId ) {
			return $this->getResponseFactory()
				->createHttpError( 403, [ 'message' => 'Login required' ] );
		}
		$this->getValidatedParams();
		$this->getSession()->remove( 'ProduntoSandbox' );
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

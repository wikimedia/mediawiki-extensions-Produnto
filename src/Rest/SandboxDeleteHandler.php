<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Rest\Handler;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Delete a user sandbox
 */
class SandboxDeleteHandler extends Handler {
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
		$this->store->delete( $userId, $sandboxId );
		return $this->getResponseFactory()->createNoContent();
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

}

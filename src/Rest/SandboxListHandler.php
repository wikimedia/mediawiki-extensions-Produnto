<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Rest\Handler;

/**
 * List available sandboxes for the current user
 */
class SandboxListHandler extends Handler {
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
		$response = $this->getResponseFactory()->createFromReturnValue(
			$this->store->getSandboxNames( $userId ) );
		$response->setHeader( 'Cache-Control', 'private,must-revalidate,s-maxage=0' );
		return $response;
	}

}

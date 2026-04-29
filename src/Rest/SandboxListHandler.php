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
		$sandboxes = [];
		$activeId = $this->getSession()->get( 'ProduntoSandbox' );
		foreach ( $this->store->getSandboxNames( $userId ) as $name ) {
			$sandbox = $this->store->get( $userId, $name );
			$sandboxes[] = [
				'id' => $name,
				'packageNames' => $sandbox->getPackageNames(),
				'active' => $activeId === $name
			];
		}
		$response = $this->getResponseFactory()->createFromReturnValue( $sandboxes );
		$response->setHeader( 'Cache-Control', 'private,must-revalidate,s-maxage=0' );
		return $response;
	}

}

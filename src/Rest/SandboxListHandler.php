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
		$metas = $this->store->getMetadata( $userId );
		$activeId = $this->getSession()->get( 'ProduntoSandbox' );
		$sandboxes = [];
		foreach ( $metas as $meta ) {
			$sandboxes[] = [
				'id' => $meta['id'],
				'size' => $meta['size'],
				'mtime' => date( 'c', $meta['mtime'] ),
				'active' => $activeId === $meta['id'],
			];
		}
		$response = $this->getResponseFactory()->createFromReturnValue( $sandboxes );
		$response->setHeader( 'Cache-Control', 'private,must-revalidate,s-maxage=0' );
		return $response;
	}

}

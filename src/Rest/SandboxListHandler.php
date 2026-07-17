<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Rest\Handler;

/**
 * List available sandboxes for the current user
 */
class SandboxListHandler extends Handler {
	public function __construct(
		private readonly SandboxStore $store,
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

	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'array',
			'items' => [
				'type' => 'object',
				'required' => [ 'id', 'size', 'mtime', 'active' ],
				'properties' => [
					'id' => [
						'type' => 'integer',
						'description' => 'The sandbox ID',
						'example' => 1,
					],
					'size' => [
						'type' => 'integer',
						'description' => 'The size of the sandbox in bytes, counted towards a per-user limit',
						'example' => 128599,
					],
					'mtime' => [
						'type' => 'string',
						'description' => 'The ISO 8601 combined date and time of last modification',
						'example' => '2026-07-17T06:18:14+00:00',
					],
					'active' => [
						'type' => 'boolean',
						'description' => 'Whether the sandbox is used on preview',
					],
				]
			]
		];
	}

}

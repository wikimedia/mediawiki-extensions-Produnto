<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Handler;

class PackagesIndexHandler extends Handler {
	private int $partitionSize;
	private ?array $partitions = null;

	public function __construct(
		Config $config,
		private ProduntoStore $store,
		private PermissionManager $permissionManager,
	) {
		$this->partitionSize = $config->get( 'ProduntoApiBatchSizes' )['packages'];
	}

	/** @inheritDoc */
	public function getETag() {
		return '"' . hash( 'sha256', json_encode( $this->getPartitions() ) ) . '"';
	}

	/** @inheritDoc */
	public function execute() {
		$response = $this->getResponseFactory()->createJson(
			[ 'partitions' => $this->getPartitions() ]
		);
		// Permit public caching with revalidation.
		// We use {credentials:omit} on the client side to work around T264631
		if ( $this->permissionManager->isEveryoneAllowed( 'read' ) ) {
			$response->setHeader( 'Cache-Control', 'public' );
		}
		return $response;
	}

	private function getPartitions(): array {
		if ( $this->partitions === null ) {
			$maxId = $this->store->getMaxPackageId() ?? 1;
			$numPartitions = (int)ceil( $maxId / $this->partitionSize );
			$partitions = [];
			for ( $i = 0; $i < $numPartitions; $i++ ) {
				$partitions[] = [
					'href' => "$i",
					'start' => $i * $this->partitionSize,
					'end' => ( $i + 1 ) * $this->partitionSize - 1
				];
			}
			$this->partitions = $partitions;
		}
		return $this->partitions;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Fetcher;

use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobSpecification;

/**
 * Job for deferred source code fetching
 */
class FetchJob extends Job {
	public const TYPE = 'ProduntoFetch';

	private int $packageId;

	public function __construct(
		array $params,
		private Fetcher $fetcher
	) {
		parent::__construct( self::TYPE, $params );
		$this->packageId = $params['id'];
		$this->removeDuplicates = true;
	}

	public static function newSpec( int $packageId ): JobSpecification {
		return new JobSpecification(
			self::TYPE,
			[ 'id' => $packageId ]
		);
	}

	/** @inheritDoc */
	public function run() {
		return $this->fetcher->fetch( $this->packageId );
	}
}

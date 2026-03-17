<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Store;

use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class ValidateDomainDbProvider implements IConnectionProvider {
	public function __construct( private ?IConnectionProvider $provider ) {
	}

	/**
	 * @param string|false $domain
	 */
	private function validateDomain( $domain ) {
		if ( $domain !== 'virtual-produnto' ) {
			throw new \InvalidArgumentException( 'Domain is not virtual-produnto' );
		}
	}

	/** @inheritDoc */
	public function getPrimaryDatabase( $domain = false ): IDatabase {
		$this->validateDomain( $domain );
		return $this->provider->getPrimaryDatabase( $domain );
	}

	/** @inheritDoc */
	public function getReplicaDatabase( string|false $domain = false, $group = null
	): IReadableDatabase {
		$this->validateDomain( $domain );
		return $this->provider->getReplicaDatabase( $domain, $group );
	}

	/** @inheritDoc */
	public function commitAndWaitForReplication( $fname, $ticket, array $opts = [] ) {
		return $this->commitAndWaitForReplication( $fname, $ticket, $opts );
	}

	/** @inheritDoc */
	public function getEmptyTransactionTicket( $fname ) {
		return $this->getEmptyTransactionTicket( $fname );
	}
}

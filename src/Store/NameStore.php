<?php

namespace MediaWiki\Extension\Produnto\Store;

use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Class for writing to the produnto_file_name table, which abbreviates relative
 * paths inside packages, mapping each path to an integer.
 */
class NameStore {
	public function __construct(
		private IConnectionProvider $dbProvider
	) {
	}

	/**
	 * Add a name and return its ID, or get the ID for an existing name
	 *
	 * @param string $name
	 * @return int
	 */
	public function store( string $name ): int {
		$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-produnto' );
		$id = $dbr->newSelectQueryBuilder()
			->select( 'pfn_id' )
			->from( 'produnto_file_name' )
			->where( [ 'pfn_name' => $name ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $id ) {
			return (int)$id;
		}

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-produnto' );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_file_name' )
			->row( [
				'pfn_name' => $name,
			] )
			->ignore()
			->caller( __METHOD__ )
			->execute();
		if ( $dbw->affectedRows() ) {
			return $dbw->insertId();
		}

		$id = $dbw->newSelectQueryBuilder()
			->select( 'pfn_id' )
			->from( 'produnto_file_name' )
			->where( [ 'pfn_name' => $name ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$id ) {
			throw new \RuntimeException( 'Unable to find ID for file name' );
		}
		return $id;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Write access to the produnto_file_text table
 */
class TextStore {
	public function __construct(
		private IConnectionProvider $dbProvider
	) {
	}

	/**
	 * Store text in the database, if it's not there already, and return the
	 * hash used to refer to it.
	 *
	 * @param string $text The text to store
	 * @return string The hash
	 */
	public function store( string $text ): string {
		$hash = hash( 'sha256', $text );
		$dbr = $this->dbProvider->getReplicaDatabase( 'virtual-produnto' );

		$exists = $dbr->newSelectQueryBuilder()
			->select( '1' )
			->from( 'produnto_file_text' )
			->where( [ 'pft_hash' => $hash ] )
			->caller( __METHOD__ )
			->fetchField();
		if ( $exists ) {
			return $hash;
		}

		$dbw = $this->dbProvider->getPrimaryDatabase( 'virtual-produnto' );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'produnto_file_text' )
			->row( [
				'pft_hash' => $hash,
				'pft_text' => $text
			] )
			->ignore()
			->caller( __METHOD__ )
			->execute();
		return $hash;
	}
}

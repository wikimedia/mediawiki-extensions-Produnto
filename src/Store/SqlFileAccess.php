<?php

namespace MediaWiki\Extension\Produnto\Store;

use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;

class SqlFileAccess implements FileAccess {
	public function __construct(
		private MapCacheLRU $textCache,
		private IReadableDatabase $db
	) {
	}

	/** @inheritDoc */
	public function hasFile( int $packageId, string $path ): bool {
		$func = __METHOD__;
		$cacheEntry = $this->textCache->get( $this->textCache->makeKey( $packageId, $path ) );
		if ( $cacheEntry === false ) {
			return false;
		} elseif ( is_string( $cacheEntry ) ) {
			return true;
		}

		$paths = $this->textCache->getWithSetCallback(
			$this->textCache->makeKey( $packageId, '//PATHS' ),
			function () use ( $packageId, $func ) {
				return $this->db->newSelectQueryBuilder()
					->select( 'pfn_name' )
					->from( 'produnto_file' )
					->join( 'produnto_file_name', null, 'pf_name_id=pfn_id' )
					->where( [ 'pf_package_version' => $packageId ] )
					->caller( $func )
					->fetchFieldValues();
			}
		);
		return in_array( $path, $paths, true );
	}

	/** @inheritDoc */
	public function getFileContents( int $packageId, string $path ): ?string {
		$func = __METHOD__;
		$text = $this->textCache->getWithSetCallback(
			$this->textCache->makeKey( $packageId, $path ),
			function () use ( $packageId, $path, $func ) {
				return $this->db->newSelectQueryBuilder()
					->select( 'pft_text' )
					->from( 'produnto_file_text' )
					->join( 'produnto_file', null, 'pf_hash=pft_hash' )
					->join( 'produnto_file_name', null, 'pf_name_id=pfn_id' )
					->where( [
						'pfn_name' => $path,
						'pf_package_version' => $packageId
					] )
					->caller( $func )
					->fetchField();
			}
		);
		return $text === false ? null : $text;
	}

	/** @inheritDoc */
	public function setCache( int $packageId, string $path, string $contents ): void {
		$this->textCache->set(
			$this->textCache->makeKey( $packageId, $path ),
			$contents
		);
	}
}

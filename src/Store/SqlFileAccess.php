<?php

namespace MediaWiki\Extension\Produnto\Store;

use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;

class SqlFileAccess implements FileAccess {
	private const TEXTS = 'texts';
	private const PATHS = 'paths';
	private const HASHES = 'hashes';

	/** Marker for missing files */
	private const MISSING = [];

	public function __construct(
		private MapCacheLRU $textCache,
		private IReadableDatabase $db
	) {
	}

	/** @inheritDoc */
	public function hasFile( int $packageId, string $path ): bool {
		$func = __METHOD__;
		$hash = $this->textCache->get( $this->textCache->makeKey( self::HASHES, $packageId, $path ) );
		if ( $hash === self::MISSING ) {
			return false;
		} elseif ( is_string( $hash ) ) {
			return true;
		}

		$paths = $this->textCache->getWithSetCallback(
			$this->textCache->makeKey( self::PATHS, $packageId ),
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
		$hash = $this->textCache->get( $this->textCache->makeKey( self::HASHES, $packageId, $path ) );
		if ( $hash === self::MISSING ) {
			return null;
		}
		if ( $hash ) {
			return $this->getFileContentsByHash( $hash );
		}

		$row = $this->db->newSelectQueryBuilder()
			->select( [ 'pft_hash', 'pft_text' ] )
			->from( 'produnto_file_text' )
			->join( 'produnto_file', null, 'pf_hash=pft_hash' )
			->join( 'produnto_file_name', null, 'pf_name_id=pfn_id' )
			->where( [
				'pfn_name' => $path,
				'pf_package_version' => $packageId
			] )
			->caller( $func )
			->fetchRow();

		$this->textCache->set(
			$this->textCache->makeKey( self::HASHES, $packageId, $path ),
			$row ? $row->pft_hash : self::MISSING
		);

		if ( $row ) {
			$this->textCache->set(
				$this->textCache->makeKey( self::TEXTS, $row->pft_hash ),
				$row->pft_text
			);
		}
		return $row ? $row->pft_text : null;
	}

	/** @inheritDoc */
	public function getFileContentsByHash( string $hash ): ?string {
		$func = __METHOD__;
		$text = $this->textCache->getWithSetCallback(
			$this->textCache->makeKey( self::TEXTS, $hash ),
			function () use ( $hash, $func ) {
				$text = $this->db->newSelectQueryBuilder()
					->select( 'pft_text' )
					->from( 'produnto_file_text' )
					->where( [ 'pft_hash' => $hash ] )
					->caller( $func )
					->fetchField();
				return $text !== false ? $text : self::MISSING;
			}
		);
		return $text === self::MISSING ? null : $text;
	}

	/** @inheritDoc */
	public function setCache( int $packageId, string $path, string $hash, string $contents ): void {
		$this->textCache->set(
			$this->textCache->makeKey( self::HASHES, $packageId, $path ),
			$hash
		);
		$this->textCache->set(
			$this->textCache->makeKey( self::TEXTS, $hash ),
			$contents
		);
	}
}

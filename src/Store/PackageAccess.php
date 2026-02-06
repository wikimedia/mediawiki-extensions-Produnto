<?php

namespace MediaWiki\Extension\Produnto\Store;

use StatusValue;
use Wikimedia\MapCacheLRU\MapCacheLRU;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * Read-only access to data relating to a package version
 */
class PackageAccess {
	public function __construct(
		private MapCacheLRU $textCache,
		private IReadableDatabase $db,
		private int $id,
		private string $name,
		private string $version,
		private string $url,
		private int $state,
		private ?string $error
	) {
	}

	public function getName(): string {
		return $this->name;
	}

	public function getVersion(): string {
		return $this->version;
	}

	public function getUrl(): string {
		return $this->url;
	}

	public function getId(): int {
		return $this->id;
	}

	public function getState(): int {
		return $this->state;
	}

	public function getStatus(): StatusValue {
		if ( $this->error ) {
			return unserialize(
				$this->error,
				[
					'allowed_classes' => [ StatusValue::class ]
				]
			);
		} else {
			return StatusValue::newGood();
		}
	}

	public function getFileContents( string $path ): ?string {
		$func = __METHOD__;
		$text = $this->textCache->getWithSetCallback(
			$this->textCache->makeKey( $this->id, $path ),
			function () use ( $path, $func ) {
				return $this->db->newSelectQueryBuilder()
					->select( 'pft_text' )
					->from( 'produnto_file_text' )
					->join( 'produnto_file', null, 'pf_hash=pft_hash' )
					->join( 'produnto_file_name', null, 'pf_name_id=pfn_id' )
					->where( [
						'pfn_name' => $path,
						'pf_package_version' => $this->id
					] )
					->caller( $func )
					->fetchField();
			}
		);
		return $text === false ? null : $text;
	}
}

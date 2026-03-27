<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use MediaWiki\Extension\Produnto\Store\PackageBuilder;

class ProduntoJsonManifest implements Manifest {
	public function __construct( private \stdClass $data ) {
	}

	/** @inheritDoc */
	public function populateProps( PackageBuilder $builder ) {
		if ( isset( $this->data->url ) ) {
			$builder->homepageUrl( $this->data->url );
		}
		if ( isset( $this->data->{'collab-url'} ) ) {
			$builder->collabUrl( $this->data->{'collab-url'} );
		}
		if ( isset( $this->data->{'doc-url'} ) ) {
			$builder->docUrl( $this->data->{'doc-url'} );
		}
		if ( isset( $this->data->{'issue-url'} ) ) {
			$builder->issueUrl( $this->data->{'issue-url'} );
		}
		if ( isset( $this->data->type ) ) {
			$builder->type( $this->data->type );
		}

		if ( isset( $this->data->name ) ) {
			foreach ( $this->data->name as $lang => $text ) {
				$builder->localName( $lang, $text );
			}
		}

		if ( isset( $this->data->description ) ) {
			foreach ( $this->data->description as $lang => $text ) {
				$builder->description( $lang, $text );
			}
		}

		if ( isset( $this->data->author ) ) {
			$authors = $this->data->author;
			if ( is_string( $authors ) ) {
				$authors = [ $authors ];
			}
			foreach ( $authors as $author ) {
				$builder->author( $author );
			}
		}

		if ( isset( $this->data->license ) ) {
			$builder->license( $this->data->license );
		}

		if ( isset( $this->data->requires ) ) {
			foreach ( $this->data->requires as $name => $constraint ) {
				$builder->requires( $name, $constraint );
			}
		}

		if ( isset( $this->data->modules ) ) {
			foreach ( $this->data->modules as $name => $path ) {
				$builder->module( $name, $path );
			}
		}
	}

	/** @inheritDoc */
	public function getModules(): array {
		return (array)( $this->data->modules ?? [] );
	}
}

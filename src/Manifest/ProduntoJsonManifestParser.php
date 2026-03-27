<?php

namespace MediaWiki\Extension\Produnto\Manifest;

use JsonSchema\Validator;
use MediaWiki\Extension\Produnto\Store\FileCollection;
use MediaWiki\Json\FormatJson;
use stdClass;

class ProduntoJsonManifestParser implements ManifestParser {
	/** @inheritDoc */
	public function hasManifest( FileCollection $package ): bool {
		return $package->getFileContents( 'produnto.json' ) !== null;
	}

	/** @inheritDoc */
	public function parse( FileCollection $package ): ManifestStatus {
		$status = new ManifestStatus;
		$data = $this->parseJson( $package, $status );
		if ( $data === null ) {
			return $status;
		}

		if ( !$this->validateJson( $data, $status ) ) {
			return $status;
		}

		if ( !$this->validateModules( $data, $package, $status ) ) {
			return $status;
		}

		$status->value = new ProduntoJsonManifest( $data );
		return $status;
	}

	/**
	 * Get produnto.json and parse it. Note that existence of the manifest file is
	 * conventionally already verified by hasManifest().
	 *
	 * @param FileCollection $package
	 * @param ManifestStatus $status
	 * @return stdClass|null
	 */
	private function parseJson( FileCollection $package, ManifestStatus $status ) {
		$json = $package->getFileContents( 'produnto.json' );
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$jsonStatus = FormatJson::parse( $json );
		if ( !$jsonStatus->isOK() ) {
			foreach ( $jsonStatus->getMessages() as $message ) {
				$status->fatal( 'produnto-fetch-manifest', $message );
			}
			return null;
		}
		return $jsonStatus->value;
	}

	/**
	 * Validate the data against the schema. An exception is only thrown if
	 * the schema itself is invalid.
	 *
	 * @param stdClass $data
	 * @param ManifestStatus $status
	 * @return bool
	 */
	private function validateJson( stdClass $data, ManifestStatus $status ) {
		$validator = new Validator;
		$schema = json_decode(
			file_get_contents( __DIR__ . '/../../docs/package.schema.json' ),
			flags: JSON_THROW_ON_ERROR
		);
		$validator->validate( $data, $schema );
		if ( !$validator->isValid() ) {
			foreach ( $validator->getErrors() as $error ) {
				$status->fatal( 'produnto-fetch-manifest-schema', $error['property'],
					$error['message'] );
			}
			return false;
		}
		return true;
	}

	/**
	 * Validate the modules property, if there is one.
	 *
	 * @param stdClass $data
	 * @param FileCollection $package
	 * @param ManifestStatus $status
	 * @return bool
	 */
	private function validateModules( stdClass $data, FileCollection $package, ManifestStatus $status ) {
		if ( !isset( $data->modules ) ) {
			return true;
		}
		$ok = true;
		foreach ( $data->modules as $name => $path ) {
			if ( !$package->hasFile( $path ) ) {
				$status->fatal( 'produnto-fetch-module-missing', $name, $path );
				$ok = false;
			}
		}
		return $ok;
	}
}

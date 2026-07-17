<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\Sandbox\SandboxBuilder;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\Handler\Helper\RestStatusTrait;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\TokenAwareHandlerTrait;
use MediaWiki\Rest\Validator\Validator;
use Psr\Http\Message\UploadedFileInterface;
use StatusValue;
use Wikimedia\ParamValidator\ParamValidator;

class SandboxPostHandler extends Handler {
	use RestStatusTrait;
	use TokenAwareHandlerTrait;

	public function __construct(
		private readonly ProduntoStore $store,
		private readonly SandboxStore $sandboxStore,
		private readonly ManifestFactory $manifestFactory,
	) {
	}

	/** @inheritDoc */
	public function execute() {
		$userId = $this->getAuthority()->getUser()->getId();
		if ( !$userId ) {
			return $this->getResponseFactory()
				->createHttpError( 403, [ 'message' => 'Login required' ] );
		}

		$sandboxId = $this->getValidatedParams()['id'];
		if ( strlen( $sandboxId ) > 32 ) {
			return $this->getResponseFactory()->createHttpError(
				400,
				[ 'message' => 'ID is too long' ]
			);
		}

		/** @var UploadedFileInterface[] $files */
		$files = $this->getRequest()->getUploadedFiles()['file'] ?? [];
		$missingHashes = [];
		$sandbox = $this->sandboxStore->createOrUpdate( $userId, $sandboxId );

		$hashesByPackage = $this->getValidatedBody()['hash'] ?? [];

		foreach ( $hashesByPackage as $package => $hashes ) {
			if ( !is_array( $hashes ) ) {
				return $this->getResponseFactory()->createHttpError(
					400,
					[ 'message' => 'hash must be a 2-d array' ]
				);
			}
			foreach ( $hashes as $path => $hash ) {
				if ( !is_string( $hash ) ) {
					return $this->getResponseFactory()->createHttpError(
						400,
						[ 'message' => 'hash must be a 2-d array' ]
					);
				}
				if ( isset( $files[$hash] ) ) {
					$text = $files[$hash]->getStream()->getContents();
					$realHash = hash( 'sha256', $text );
					if ( $hash !== $realHash ) {
						return $this->getResponseFactory()->createHttpError(
							400,
							[ 'message' => 'hash mismatch' ]
						);
					}
					$sandbox->addFile( $package, $path, $hash, $text );
				} elseif ( $sandbox->hasHash( $hash ) ) {
					$sandbox->addFileReference( $package, $path, $hash );
				} else {
					$missingHashes[] = $hash;
				}
			}
		}

		$exists = $this->store->hasFileHashBatch( $missingHashes );
		$stillMissing = [];

		foreach ( $hashesByPackage as $package => $hashes ) {
			foreach ( $hashes as $path => $hash ) {
				if ( !$sandbox->hasHash( $hash ) ) {
					if ( $exists[$hash] ) {
						$sandbox->addFileReference( $package, $path, $hash );
					} else {
						$stillMissing[] = $hash;
					}
				}
			}
		}

		if ( !$stillMissing ) {
			$status = $this->populateModuleInfo( $sandbox );
			if ( !$status->isOK() ) {
				$this->throwExceptionForStatus( $status, 'produnto-sandbox-error', 400 );
			}
			$ok = true;
		} else {
			$ok = false;
		}

		if ( !$sandbox->commit() ) {
			return $this->getResponseFactory()->createHttpError(
				413,
				[ 'message' => 'the supplied files exceed the maximum size' ]
			);
		}

		sort( $stillMissing );
		$stillMissing = array_unique( $stillMissing );

		return [
			'ok' => $ok,
			'missingHashes' => $stillMissing
		];
	}

	private function populateModuleInfo( SandboxBuilder $sandboxBuilder ): StatusValue {
		$overallStatus = StatusValue::newGood();
		$sandboxAccess = $sandboxBuilder->access();
		$modules = [];
		foreach ( $sandboxAccess->getPackageNames() as $packageName ) {
			$package = $sandboxAccess->getPackage( $packageName );
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$status = $this->manifestFactory->parseManifest( $package );
			if ( !$status->isOK() ) {
				$overallStatus->merge( $status );
				continue;
			}
			foreach ( $status->getValue()->getModules() as $moduleName => $path ) {
				$modules[$moduleName] = [ $packageName, $path ];
			}
		}
		$sandboxBuilder->modules( $modules );
		return $overallStatus;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'id' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'An arbitrary user-scoped identifier for the ' .
					'sandbox being created or updated.',
				self::PARAM_EXAMPLE => 'sandbox1',
			]
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'hash' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				self::PARAM_DESCRIPTION => 'An associative array of associative arrays, with ' .
					'the package name in the first key, the path in the second key and the ' .
					'lowercase hexadecimal SHA-256 hash of the file contents of that path as ' .
					'the value. For example `?hash[package1][src/init.lua]=' .
					'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`',
			]
		] + $this->getTokenParamDefinition();
	}

	public function getRequestBodySchema( string $mediaType ): array {
		return [
			'type' => 'object',
			'properties' => [
				'hash' => [
					'description' => 'An associative array of associative arrays, with ' .
						'the package name in the first key, the path in the second key and the ' .
						'lowercase hexadecimal SHA-256 hash of the file contents of that path as ' .
						'the value. For example `hash[package1][src/init.lua]=' .
						"e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`\n\n" .
						'All hashes present in the sandbox must be sent in every request. ' .
						"File deletion is done by omitting a hash.\n\n" .
						'NOTE: OpenAPI and Swagger do not support this format. We present a ' .
						'schema here as a rough human-readable explanation of the request body.',
					'type' => 'object',
					'additionalProperties' => [
						'type' => 'object',
						'additionalProperties' => [
							'type' => 'string'
						]
					],
					'example' => 'one does not simply create a sandbox with Swagger',
				],
				'file' => [
					'description' => 'An array of files posted as multipart, where the name ' .
						'of each file is of the form `file[<hash>]` where `<hash>` is the ' .
						"SHA-256 hash of the content as referenced in the `hash` parameter.\n\n" .
						'It is optional to post the contents of a file. The server will respond ' .
						'with a list of missing hashes so that the client can post them on ' .
						"demand.\n\n" .
						'NOTE: OpenAPI and Swagger do not support this format. We present a ' .
						'schema here as a rough human-readable explanation of the request body.',
					'type' => 'object',
					'additionalProperties' => [
						'type' => 'string',
						'format' => 'binary',
					],
					'example' => 'one does not simply create a sandbox with Swagger',
				],
			]
		];
	}

	/** @inheritDoc */
	public function getSupportedRequestTypes(): array {
		return RequestInterface::FORM_DATA_CONTENT_TYPES;
	}

	/** @inheritDoc */
	public function validate( Validator $restValidator ) {
		parent::validate( $restValidator );
		$this->validateToken();
	}

	protected function getResponseBodySchema( string $method ): ?array {
		return [
			'type' => 'object',
			'description' => 'OK',
			'properties' => [
				'ok' => [
					'type' => 'boolean',
					'description' => 'Whether the sandbox has all file contents and ' .
						'is ready to use.',
					'example' => false,
				],
				'missingHashes' => [
					'type' => 'array',
					'items' => [ 'type' => 'string' ],
					'description' => 'The hashes of files that were not found in the ' .
						'database or in previously posted sandbox update requests.',
					'example' => [ 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca' .
						'495991b7852b855' ],
				]
			]
		];
	}
}

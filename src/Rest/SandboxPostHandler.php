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
use Wikimedia\Message\MessageValue;
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
				self::PARAM_DESCRIPTION => new MessageValue( 'rest-param-desc-produnto-sandbox-post-id' ),
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
				self::PARAM_DESCRIPTION =>
					new MessageValue( 'rest-param-desc-produnto-sandbox-hash' ),
			]
		] + $this->getTokenParamDefinition();
	}

	public function getRequestBodySchema( string $mediaType ): array {
		return [
			'type' => 'object',
			'properties' => [
				'hash' => [
					'description' => new MessageValue( 'rest-param-desc-produnto-sandbox-hash' ),
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
					'description' => new MessageValue( 'rest-param-desc-produnto-sandbox-file' ),
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
					'x-i18n-description' => 'rest-property-desc-produnto-sandbox-ok',
					'example' => false,
				],
				'missingHashes' => [
					'type' => 'array',
					'items' => [ 'type' => 'string' ],
					'x-i18n-description' => 'rest-property-desc-produnto-sandbox-missing-hashes',
					'example' => [ 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca' .
						'495991b7852b855' ],
				]
			]
		];
	}
}

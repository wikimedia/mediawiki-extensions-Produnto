<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Server\GitlabServer;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\PackageBuilderError;
use MediaWiki\Extension\Produnto\Store\VersionAlreadyExistsError;
use MediaWiki\ParamValidator\TypeDef\ArrayDef;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Validator\Validator;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;

class GitlabTagHandler extends Handler {
	public function __construct(
		private ServerContainer $serverContainer,
		private Fetcher $fetcher,
		private LoggerInterface $logger
	) {
	}

	/** @inheritDoc */
	public function execute() {
		$body = $this->getRequest()->getParsedBody();
		'@phan-var array $body';
		$ref = $body['ref'];
		$projectUrl = $body['project']['web_url'];
		$server = $this->serverContainer->getServerForUrl( $projectUrl );
		if ( !$server ) {
			$this->logger->info( "GitLab web hook received unknown project URL {url}",
				[ 'url' => $projectUrl ] );
			throw new HttpException(
				'Unknown project URL',
				400
			);
		}
		if ( !( $server instanceof GitlabServer ) ) {
			$this->logger->info( 'GitLab web hook received non-GitLab project URL {url}',
				[ 'url' => $projectUrl ] );
			throw new HttpException(
				'Not a GitLab URL',
				400
			);
		}
		$name = $server->urlToName( $projectUrl );
		if ( $name === null ) {
			$this->logger->info( 'GitLab web hook received non-project URL {url}',
				[ 'url' => $projectUrl ] );
			throw new HttpException(
				'Wrong URL format',
				400
			);
		}
		$version = $server->refToVersion( $ref );
		if ( !$version ) {
			$this->logger->info( 'GitLab web hook received bad ref {ref}',
				[ 'ref' => $ref ] );
			throw new HttpException(
				'Wrong ref format',
				400
			);
		}
		try {
			$this->fetcher->asyncFetch( $name, $projectUrl, $version, $ref );
			$response = $this->getResponseFactory()->createJson( [ 'status' => 'accepted' ] );
			$response->setStatus( 202 );
		} catch ( VersionAlreadyExistsError ) {
			$response = $this->getResponseFactory()->createJson( [ 'status' => 'already fetched' ] );
			$response->setStatus( 202 );
		} catch ( PackageBuilderError $e ) {
			$this->logger->info( 'GitLab web hook failed to queue fetch: {builderError}',
				[ 'builderError' => $e->getMessage() ] );
			throw new HttpException(
				$e->getMessage(),
				400
			);
		}
		return $response;
	}

	public function getBodyParamSettings(): array {
		return [
			'object_kind' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => [ 'tag_push' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'event_name' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => [ 'tag_push' ],
				ParamValidator::PARAM_REQUIRED => true,
			],
			'ref' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'project' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ArrayDef::PARAM_SCHEMA => ArrayDef::makeObjectSchema(
					[ 'web_url' => 'string' ],
					[],
					true
				)
			],
		];
	}

	protected function detectExtraneousBodyFields( Validator $restValidator ) {
		// Many extra body fields are expected
	}
}

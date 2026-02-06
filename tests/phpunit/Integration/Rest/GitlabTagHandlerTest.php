<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Rest\GitlabTagHandler;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @covers \MediaWiki\Extension\Produnto\Rest\GitlabTagHandler
 */
class GitlabTagHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	public static function provideExecute() {
		$defaultBodyData = [
			'object_kind' => 'tag_push',
			'event_name' => 'tag_push',
			'before' => '0000000000000000000000000000000000000000',
			'after' => '8f95bf774c32084a03f073c08f8e93dffca7a537',
			'ref' => 'refs/tags/v1.0',
			'ref_protected' => false,
			'checkout_sha' => '8f95bf774c32084a03f073c08f8e93dffca7a537',
			'message' => '',
			'user_id' => 129,
			'user_name' => 'Tim Starling',
			'user_username' => 'tstarling',
			'user_email' => '',
			'user_avatar' => null,
			'project_id' => 4127,
			'project' => [
				'id' => 4127,
				'name' => 'produnto-test',
				'description' => null,
				'web_url' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
				'avatar_url' => null,
				'git_ssh_url' => 'git@gitlab.wikimedia.org:tstarling/produnto-test.git',
				'git_http_url' => 'https://gitlab.wikimedia.org/tstarling/produnto-test.git',
				'namespace' => 'Tim Starling',
				'visibility_level' => 20,
				'path_with_namespace' => 'tstarling/produnto-test',
				'default_branch' => 'main',
				'ci_config_path' => '',
				'homepage' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
				'url' => 'git@gitlab.wikimedia.org:tstarling/produnto-test.git',
				'ssh_url' => 'git@gitlab.wikimedia.org:tstarling/produnto-test.git',
				'http_url' => 'https://gitlab.wikimedia.org/tstarling/produnto-test.git',
			],
			'commits' => [
				0 => [
					'id' => '8f95bf774c32084a03f073c08f8e93dffca7a537',
					'message' => 'Update file README.md',
					'title' => 'Update file README.md',
					'timestamp' => '2026-02-10T02:15:04+00:00',
					// @phpcs:ignore Generic.Files.LineLength.TooLong
					'url' => 'https://gitlab.wikimedia.org/tstarling/produnto-test/-/commit/8f95bf774c32084a03f073c08f8e93dffca7a537',
					'author' => [
						'name' => 'Tim Starling',
						'email' => 'tstarling@wikimedia.org',
					],
					'added' => [],
					'modified' => [
						0 => 'README.md',
					],
					'removed' => [],
				],
			],
			'total_commits_count' => 1,
			'push_options' => [],
			'repository' => [
				'name' => 'produnto-test',
				'url' => 'git@gitlab.wikimedia.org:tstarling/produnto-test.git',
				'description' => null,
				'homepage' => 'https://gitlab.wikimedia.org/tstarling/produnto-test',
				'git_http_url' => 'https://gitlab.wikimedia.org/tstarling/produnto-test.git',
				'git_ssh_url' => 'git@gitlab.wikimedia.org:tstarling/produnto-test.git',
				'visibility_level' => 20,
			],
		];

		$cases = [
			'success' => [ [], 202, null ],
			'unknown url' => [
				[ 'project' => [ 'web_url' => 'foo' ] ],
				400,
				'Unknown project URL'
			],
			'wrong ref format' => [
				[ 'ref' => 'refs/heads/main' ],
				400,
				'Wrong ref format'
			]
		];

		foreach ( $cases as $caseName => [ $overrides, $code, $msg ] ) {
			$bodyData = $defaultBodyData;
			foreach ( $overrides as $name => $value ) {
				if ( isset( $bodyData[$name] ) && is_array( $value ) ) {
					$bodyData[$name] = $value + $bodyData[$name];
				} else {
					$bodyData[$name] = $value;
				}
			}
			$body = json_encode( $bodyData );
			$reqData = [
				'method' => 'POST',
				'uri' => '/w/rest.php/produnto/v1/gitlab/tag',
				'bodyContents' => $body,
				'headers' => [
					'Content-Type' => 'application/json',
					'User-Agent' => 'GitLab/18.6.5',
					'Idempotency-Key' => 'fc62c548-8a40-462f-ac59-568a4d9cea66',
					'X-Gitlab-Event' => 'Tag Push Hook',
					'X-Gitlab-Webhook-Uuid' => '9f6a211b-cc4b-413e-9a69-b100d9800465',
					'X-Gitlab-Instance' => 'https://gitlab.wikimedia.org',
					'X-Gitlab-Event-Uuid' => '54ce3c86-01bb-41bc-8f9d-f7ef433fb066',
					'Accept-Encoding' => 'gzip;q=1.0,deflate;q=0.6,identity;q=0.3',
					'Accept' => '*/*',
				],
			];
			yield $caseName => [ $reqData, $code, $msg ];
		}
	}

	/**
	 * @dataProvider provideExecute
	 * @param array $reqData
	 * @param int $expectedCode
	 * @param ?string $expectedMessage
	 */
	public function testExecute( $reqData, $expectedCode, $expectedMessage ) {
		$this->overrideConfigValue( 'ProduntoServers', [
			[
				'type' => 'gitlab',
				'url' => 'https://gitlab.wikimedia.org',
				'projectPrefixes' => [ 'tstarling' ]
			]
		] );

		$fetcher = $this->createNoOpMock( Fetcher::class, [ 'asyncFetch' ] );
		$fetcher->expects( $this->any() )->method( 'asyncFetch' )->willReturn( null );

		$services = $this->getServiceContainer();
		$handler = new GitlabTagHandler(
			$services->get( 'Produnto.ServerContainer' ),
			$fetcher,
			LoggerFactory::getInstance( 'Produnto' )
		);
		$request = new RequestData( $reqData );
		if ( $expectedMessage !== null ) {
			$exception = $this->executeHandlerAndGetHttpException( $handler, $request );
			$this->assertSame( $expectedCode, $exception->getCode() );
			$this->assertSame( $expectedMessage, $exception->getMessage() );
		} else {
			$response = $this->executeHandler( $handler, $request );
			$this->assertSame( $expectedCode, $response->getStatusCode() );
		}
	}
}

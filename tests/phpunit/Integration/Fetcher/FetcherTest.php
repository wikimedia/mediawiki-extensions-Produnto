<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Fetcher;

use GuzzleHttp;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\RequestOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @covers \MediaWiki\Extension\Produnto\Fetcher\Fetcher
 * @covers \MediaWiki\Extension\Produnto\Fetcher\FetchJob
 * @covers \MediaWiki\Extension\Produnto\Fetcher\FetchStatus
 * @covers \MediaWiki\Extension\Produnto\Server\GitlabServer::fetch
 * @covers \MediaWiki\Extension\Produnto\Server\GitlabServer::urlToProjectPath
 * @group Database
 */
class FetcherTest extends \MediaWikiIntegrationTestCase {
	use \MockHttpTrait;

	private function setupServer() {
		$this->overrideConfigValue( 'ProduntoServers', [
			[
				'type' => 'gitlab',
				'url' => 'https://gitlab.wikimedia.org',
				'projectPrefixes' => [ 'tstarling' ]
			]
		] );
	}

	private function setupHttp( $code, $body ) {
		$response = new GuzzleHttp\Psr7\Response( $code, [], $body );
		$client = $this->createNoOpMock( GuzzleHttp\Client::class, [ 'get' ] );
		$client->method( 'get' )->willReturnCallback(
			static function ( $uri, array $options = [] ) use ( $body, $response ) {
				fwrite( $options[RequestOptions::SINK], $body );
				return $response;
			} );
		$this->installMockHttp( [ $client ] );
	}

	public function testAsyncFetch() {
		$this->setupServer();
		$body = file_get_contents( __DIR__ . '/../../data/archive.zip' );
		$this->setupHttp( 200, $body );

		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);
		$this->runJobs();

		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$this->assertSame( 'produnto-test', $package->getName() );
		$this->assertSame( '1.1', $package->getVersion() );
		$this->assertSame( 'refs/tags/v1.1', $package->getUpstreamRef() );
		$this->assertNull( $package->getFileContents( 'src/' ) );
		$this->assertIsString( $package->getFileContents( 'src/init.lua' ) );

		$readme = <<<EOT
# produnto-test

Test package project for Produnto
EOT;
		$this->assertSame( $readme, $package->getFileContents( 'README.md' ) );

		$this->assertSame( 'scribunto', $package->getType() );
		$this->assertSame( 'https://gitlab.wikimedia.org/tstarling/produnto-test',
			$package->getCollabUrl() );
		$this->assertSame( 'Produnto test', $package->getLocalName( 'en' ) );
		$this->assertSame( 'Produnto test description',
			$package->getDescription( 'en' ) );
	}

	private function getFetcher(): Fetcher {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getFetcher();
	}

	private function getStore(): ProduntoStore {
		return ( new ProduntoServices( $this->getServiceContainer() ) )->getStore();
	}

	public function testNonexistentPackage() {
		$fetcher = $this->getFetcher();
		$logger = new \TestLogger( true );
		$fetcher->setLogger( $logger );
		$res = $fetcher->fetch( 1 );
		$this->assertTrue( $res );

		$entries = $logger->getBuffer();
		$this->assertGreaterThan( 0, count( $entries ) );
		$this->assertStringContainsString( 'no such package', $entries[0][1] );
	}

	public function testFetchException() {
		$this->setupServer();
		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);
		try {
			$fetcher->fetch( 1 );
			$this->fail( 'expected exception' );
		} catch ( AssertionFailedError $e ) {
		}
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();

		$this->assertStatusError( 'produnto-fetch-error', $status );
		$this->assertStringStartsWith(
			'Fetch failed: caught exception of class &#34;PHPUnit\Framework\AssertionFailedError&#34;',
			$this->statusToString( $status ) );
	}

	private function statusToString( $status ) {
		$formatterFactory = $this->getServiceContainer()->getFormatterFactory();
		$formatter = $formatterFactory->getStatusFormatter( RequestContext::getMain() );
		return $formatter->getMessage( $status )->text();
	}

	public function testNoSuchServer() {
		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);
		$serverContainer = ( new ProduntoServices )->getServerContainer();
		$serverContainer->setServersForTesting( [] );

		$res = $fetcher->fetch( 1 );
		$this->assertTrue( $res );
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();
		$this->assertSame(
			'Fetch failed: Server is no longer configured',
			$this->statusToString( $status ) );
	}

	public function testTemporaryServerError() {
		$this->setupServer();
		$this->setupHttp( 503, 'Try again' );
		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);

		$res = $fetcher->fetch( 1 );
		$this->assertFalse( $res );
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();
		$this->assertSame(
			'Fetch failed due to a server error: 503 Service Unavailable',
			$this->statusToString( $status ) );
	}

	public function testPermanentServerError() {
		$this->setupServer();
		$this->setupHttp( 404, 'No such project' );
		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);

		$res = $fetcher->fetch( 1 );
		$this->assertTrue( $res );
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();
		$this->assertSame(
			'Fetch failed with an error: 404 Not Found',
			$this->statusToString( $status ) );
	}

	public function testConnectError() {
		$this->setupServer();
		$client = $this->createNoOpMock( GuzzleHttp\Client::class, [ 'get' ] );
		$client->method( 'get' )->willThrowException(
			new ConnectException(
				'connect',
				new GuzzleHttp\Psr7\Request( 'GET', '' )
			)
		);
		$this->installMockHttp( [ $client ] );

		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);

		$res = $fetcher->fetch( 1 );
		$this->assertFalse( $res );
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();
		$this->assertSame(
			'Fetch failed due to a server error: connection failed',
			$this->statusToString( $status ) );
	}

	public function testManifestError() {
		$this->setupServer();
		$body = file_get_contents( __DIR__ . '/../../data/manifest-error.zip' );
		$this->setupHttp( 200, $body );

		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1',
			'refs/tags/v1.1'
		);
		$res = $fetcher->fetch( 1 );
		$this->assertTrue( $res );
		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$status = $package->getStatus();
		$this->assertSame(
			'Error in produnto.json: Syntax error',
			$this->statusToString( $status ) );
	}
}

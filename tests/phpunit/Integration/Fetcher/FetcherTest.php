<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Fetcher;

use GuzzleHttp;
use GuzzleHttp\RequestOptions;
use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;

/**
 * @covers \MediaWiki\Extension\Produnto\Fetcher\Fetcher
 * @covers \MediaWiki\Extension\Produnto\Fetcher\FetchJob
 * @covers \MediaWiki\Extension\Produnto\Server\GitlabServer::fetch
 * @group Database
 */
class FetcherTest extends \MediaWikiIntegrationTestCase {
	use \MockHttpTrait;

	public function testAsyncFetch() {
		$this->overrideConfigValue( 'ProduntoServers', [
			[
				'type' => 'gitlab',
				'url' => 'https://gitlab.wikimedia.org',
				'projectPrefixes' => [ 'tstarling' ]
			]
		] );

		$body = file_get_contents( __DIR__ . '/../../data/archive.zip' );
		$response = new GuzzleHttp\Psr7\Response( 200, [], $body );
		$client = $this->createNoOpMock( GuzzleHttp\Client::class, [ 'get' ] );
		$client->method( 'get' )->willReturnCallback(
			static function ( $uri, array $options = [] ) use ( $body, $response ) {
				fwrite( $options[RequestOptions::SINK], $body );
				return $response;
			} );
		$this->installMockHttp( [ $client ] );

		$fetcher = $this->getFetcher();
		$fetcher->asyncFetch(
			'produnto-test',
			'https://gitlab.wikimedia.org/tstarling/produnto-test',
			'1.1'
		);
		$this->runJobs();

		$store = $this->getStore();
		$package = $store->getPackageById( 1 );
		$this->assertSame( 'produnto-test', $package->getName() );
		$this->assertSame( '1.1', $package->getVersion() );

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
		return $this->getServiceContainer()->get( 'Produnto.Fetcher' );
	}

	private function getStore(): ProduntoStore {
		return $this->getServiceContainer()->get( 'Produnto.Store' );
	}
}

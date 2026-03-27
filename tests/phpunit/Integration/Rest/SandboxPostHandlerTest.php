<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use GuzzleHttp\Psr7\UploadedFile;
use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\SandboxDeleteHandler;
use MediaWiki\Extension\Produnto\Rest\SandboxListHandler;
use MediaWiki\Extension\Produnto\Rest\SandboxPostHandler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\StringStream;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Rest\SandboxPostHandler
 * @covers \MediaWiki\Extension\Produnto\Rest\SandboxListHandler
 * @covers \MediaWiki\Extension\Produnto\Rest\SandboxDeleteHandler
 */
class SandboxPostHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private const MANIFEST_TEXT = '{}';
	private const MANIFEST_FILES = [ 'produnto.json' => self::MANIFEST_TEXT ];

	public function testPostListDelete() {
		$this->assertSame( [], $this->listSandboxes() );
		$res = $this->postSandbox(
			'sandbox1',
			[ 'package1' => self::MANIFEST_FILES ],
			[ self::MANIFEST_TEXT ]
		);
		$this->assertTrue( $res->ok );
		$this->assertSame( [], $res->missingHashes );

		$this->assertSame( [ 'sandbox1' ], $this->listSandboxes() );
		$this->deleteSandbox( 'sandbox1' );
		$this->assertSame( [], $this->listSandboxes() );
	}

	public function testIncrementalPost() {
		$res = $this->postSandbox(
			'sandbox1',
			[ 'package1' => self::MANIFEST_FILES ],
			[]
		);
		$this->assertFalse( $res->ok );
		$this->assertCount( 1, $res->missingHashes );
		$this->assertSame( hash( 'sha256', self::MANIFEST_TEXT ), $res->missingHashes[0] );

		$res = $this->postSandbox(
			'sandbox1',
			[ 'package1' => self::MANIFEST_FILES ],
			[ self::MANIFEST_TEXT ]
		);
		$this->assertTrue( $res->ok );
		$this->assertSame( [], $res->missingHashes );
	}

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	private function listSandboxes() {
		$handler = new SandboxListHandler( $this->getProduntoServices()->getSandboxStore() );
		$request = new RequestData( [
			'method' => 'GET',
			'uri' => '/w/rest.php/produnto/v1/sandbox',
		] );
		$authority = $this->mockRegisteredNullAuthority();
		$response = $this->executeHandler( $handler, $request, authority: $authority );
		$this->assertSame( 200, $response->getStatusCode() );
		return json_decode( $response->getBody()->getContents() );
	}

	private function postSandbox( $id, $filesByPackage, $texts ) {
		$services = $this->getProduntoServices();
		$handler = new SandboxPostHandler(
			$services->getStore(),
			$services->getSandboxStore(),
			new ManifestFactory()
		);

		$hashes = [];
		foreach ( $filesByPackage as $package => $files ) {
			foreach ( $files as $name => $text ) {
				$hashes[$package][$name] = hash( 'sha256', $text );
			}
		}

		$uploadedFiles = [];
		foreach ( $texts as $text ) {
			$uploadedFiles['file'][ hash( 'sha256', $text ) ] =
				new UploadedFile( new StringStream( $text ), strlen( $text ), UPLOAD_ERR_OK );
		}

		$request = new RequestData( [
			'method' => 'POST',
			'uri' => "/w/rest.php/produnto/v1/sandbox/$id",
			'postParams' => [
				'hash' => $hashes,
				'token' => '',
			],
			'uploadedFiles' => $uploadedFiles
		] );
		$authority = $this->mockRegisteredNullAuthority();

		$response = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => $id ],
			validatedBody: [ 'hash' => $hashes ],
			authority: $authority
		);
		$this->assertSame( 200, $response->getStatusCode() );
		return json_decode( $response->getBody()->getContents() );
	}

	private function deleteSandbox( $id ) {
		$handler = new SandboxDeleteHandler( $this->getProduntoServices()->getSandboxStore() );
		$request = new RequestData( [
			'method' => 'DELETE',
			'uri' => "/w/rest.php/produnto/v1/sandbox/$id",
		] );
		$authority = $this->mockRegisteredNullAuthority();
		$response = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => $id ],
			authority: $authority
		);
		$this->assertSame( 204, $response->getStatusCode() );
		$this->assertSame( '', $response->getBody()->getContents() );
	}
}

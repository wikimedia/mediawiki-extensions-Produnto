<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\SandboxActivateHandler;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Rest\SandboxActivateHandler
 */
class SandboxActivateHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	private function getHandler() {
		return new SandboxActivateHandler(
			$this->getProduntoServices()->getSandboxStore()
		);
	}

	public function testLoginRequired() {
		$handler = $this->getHandler();
		$request = new RequestData();
		$res = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => 'sandbox1' ]
		);
		$this->assertSame( 403, $res->getStatusCode() );
		$this->assertStringContainsString( 'Login required', $res->getBody()->getContents() );
	}

	public function testNotFound() {
		$handler = $this->getHandler();
		$request = new RequestData();
		$res = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => 'sandbox1' ],
			authority: $this->mockRegisteredNullAuthority()
		);
		$this->assertSame( 404, $res->getStatusCode() );
		$this->assertStringContainsString( 'no such ID', $res->getBody()->getContents() );
	}

	public function testSuccess() {
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$authority = $testUser->getAuthority();
		$userId = $user->getId();

		$sandboxStore = $this->getProduntoServices()->getSandboxStore();
		$sandboxStore->createOrUpdate( $userId, 'sandbox1' )->commit();

		$session = $this->getServiceContainer()->getSessionManager()
			->getEmptySession( new FauxRequest() );
		$session->setUser( $user );

		$handler = $this->getHandler();
		$request = new RequestData();
		$res = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => 'sandbox1' ],
			validatedBody: [ 'token' => (string)$session->getToken() ],
			authority: $authority,
			session: $session
		);
		$this->assertSame( 200, $res->getStatusCode() );
		$sandbox = $this->getProduntoServices()->getRuntimeFactory()
			->getActiveSandbox( $userId, $session );
		$this->assertNotNull( $sandbox );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Integration\Rest;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Rest\SandboxDeactivateHandler;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentityValue;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Produnto\Rest\SandboxDeactivateHandler
 */
class SandboxDeactivateHandlerTest extends \MediaWikiIntegrationTestCase {
	use HandlerTestTrait;

	private function getProduntoServices() {
		return new ProduntoServices( $this->getServiceContainer() );
	}

	private function getHandler() {
		return new SandboxDeactivateHandler();
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

	public function testSuccess() {
		$sandboxStore = $this->getProduntoServices()->getSandboxStore();
		$sandboxStore->createOrUpdate( 1, 'sandbox1' )->commit();
		$authority = new SimpleAuthority( new UserIdentityValue( 1, 'User' ), [] );

		$handler = $this->getHandler();
		$request = new RequestData();
		$res = $this->executeHandler(
			$handler, $request,
			validatedParams: [ 'id' => 'sandbox1' ],
			authority: $authority
		);
		$this->assertSame( 200, $res->getStatusCode() );
	}

}

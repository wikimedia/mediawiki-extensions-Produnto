<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\Sandbox\SandboxAccess;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Session\Session;

/**
 * Service providing access to modules active in the context of a parse operation
 */
class RuntimeFactory {
	public function __construct(
		private ProduntoStore $store,
		private SandboxStore $sandboxStore
	) {
	}

	/**
	 * Create a runtime instance
	 *
	 * @param ParserOptions|null $options The parser options, used for preview detection
	 * @return ProduntoRuntime
	 */
	public function create( ?ParserOptions $options = null ) {
		$loaders = [];
		$sandboxLoader = $this->createSandboxLoader( $options );
		if ( $sandboxLoader ) {
			$loaders[] = $sandboxLoader;
		}
		$loaders[] = new SqlLoader( $this->store );
		return new ProduntoRuntime( $loaders );
	}

	/**
	 * Get the currently active sandbox
	 *
	 * @param int $userId
	 * @param Session $session
	 * @return SandboxAccess|null
	 */
	public function getActiveSandbox( int $userId, Session $session ): ?SandboxAccess {
		$sandboxId = $session->get( 'ProduntoSandbox' );
		if ( $sandboxId === null ) {
			return null;
		}
		return $this->sandboxStore->get( $userId, $sandboxId );
	}

	/**
	 * Create a Loader for access to the currently active sandbox, if there is
	 * an active sandbox.
	 *
	 * @param ParserOptions|null $options
	 * @return Loader|null
	 */
	private function createSandboxLoader( ?ParserOptions $options ): ?Loader {
		if ( !$options?->getIsPreview() ) {
			return null;
		}
		$userId = $options->getUserIdentity()->getId();
		if ( !$userId ) {
			return null;
		}
		$session = RequestContext::getMain()->getRequest()->getSession();
		return $this->getActiveSandbox( $userId, $session );
	}
}

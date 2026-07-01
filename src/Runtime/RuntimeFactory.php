<?php

namespace MediaWiki\Extension\Produnto\Runtime;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\RepoViewer\RepoLinker;
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
		private readonly ProduntoStore $store,
		private readonly SandboxStore $sandboxStore,
		private readonly RepoLinker $repoLinker,
	) {
	}

	/**
	 * Create a runtime instance
	 *
	 * @param ParserOptions|null $options The parser options, used for preview detection
	 */
	public function create( ?ParserOptions $options = null ): ProduntoRuntime {
		$loaders = [];
		$sandboxLoader = $this->createSandboxLoader( $options );
		if ( $sandboxLoader ) {
			$loaders[] = $sandboxLoader;
		}
		$loaders[] = new SqlLoader( $this->store );
		return new ProduntoRuntime( $this->repoLinker, $loaders );
	}

	/**
	 * Get the currently active sandbox
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

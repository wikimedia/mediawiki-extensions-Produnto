<?php

namespace MediaWiki\Extension\Produnto\Server;

use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigException;
use MediaWiki\Http\HttpRequestFactory;

/**
 * A collection of servers, providing access to client methods.
 */
class ServerContainer {
	private ?array $servers = null;

	public function __construct(
		private HttpRequestFactory $httpRequestFactory,
		private Config $config,
	) {
	}

	public function getServerForUrl( string $projectUrl ): ?BaseServer {
		foreach ( $this->getServers() as $server ) {
			if ( $server->hasUrl( $projectUrl ) ) {
				return $server;
			}
		}
		return null;
	}

	/**
	 * @internal
	 * @return BaseServer[]
	 */
	public function getServers() {
		if ( $this->servers === null ) {
			$this->servers = [];
			foreach ( $this->config->get( 'ProduntoServers' ) as $serverConfig ) {
				if ( $serverConfig['type'] === 'gitlab' ) {
					$this->servers[] = new GitlabServer(
						$this->httpRequestFactory,
						$serverConfig
					);
				} else {
					throw new ConfigException( 'Invalid type in $wgProduntoServers' );
				}
			}
		}
		return $this->servers;
	}

	/**
	 * @internal
	 * @param BaseServer[] $servers
	 */
	public function setServersForTesting( array $servers ) {
		$this->servers = $servers;
	}

}

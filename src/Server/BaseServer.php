<?php

namespace MediaWiki\Extension\Produnto\Server;

use MediaWiki\Extension\Produnto\Fetcher\FetchStatus;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Extension\Produnto\Store\PackageMetaAccess;

/**
 * The base class for server instances. These are clients that encapsulate a
 * single server with a protocol and URL prefix.
 */
abstract class BaseServer {
	/**
	 * Fetch the contents of the specified package
	 *
	 * @param PackageMetaAccess $package
	 * @param PackageBuilder $dest
	 * @return FetchStatus
	 */
	abstract public function fetch( PackageMetaAccess $package, PackageBuilder $dest ): FetchStatus;

	/**
	 * Is the URL on this server?
	 *
	 * @param string $url
	 * @return bool
	 */
	abstract public function hasUrl( string $url ): bool;
}

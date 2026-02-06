<?php

namespace MediaWiki\Extension\Produnto\Server;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use StatusValue;

/**
 * The base class for server instances. These are clients that encapsulate a
 * single server with a protocol and URL prefix.
 */
abstract class BaseServer {
	/**
	 * Fetch the contents of the specified package
	 *
	 * @param PackageAccess $package
	 * @param PackageBuilder $dest
	 * @return StatusValue
	 */
	abstract public function fetch( PackageAccess $package, PackageBuilder $dest ): StatusValue;

	/**
	 * Is the URL on this server?
	 *
	 * @param string $url
	 * @return bool
	 */
	abstract public function hasUrl( string $url ): bool;
}

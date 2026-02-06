<?php

namespace MediaWiki\Extension\Produnto\Server;

/**
 * Shared code for Git clients
 */
abstract class GitServer extends BaseServer {
	/**
	 * Convert a git ref to a version. Permit tag names starting with a number,
	 * optionally prefixed with "v".
	 *
	 * @param string $ref
	 * @return string|null
	 */
	public function refToVersion( string $ref ): ?string {
		if ( preg_match( '!^refs/tags/v?([0-9].*)$!', $ref, $m ) ) {
			return $m[1];
		} else {
			return null;
		}
	}
}

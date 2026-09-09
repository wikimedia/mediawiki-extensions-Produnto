<?php

namespace MediaWiki\Extension\Produnto\Version;

/**
 * Parse version strings, following LuaRocks conventions.
 */
class VersionParser {
	private const DELTAS = [
		'dev' => 120000000,
		'scm' => 110000000,
		'cvs' => 100000000,
		'rc' => -1000,
		'pre' => -10000,
		'beta' => -100000,
		'alpha' => -1000000
	];

	/** @var array<string,Version> */
	private array $cache = [];

	/**
	 * Parse a version string, with caching
	 *
	 * @param string $input
	 * @return Version
	 * @throws VersionParserError
	 */
	public function parse( string $input ): Version {
		// phpcs:ignore MediaWiki.Usage.AssignmentInReturn.AssignmentInReturn
		return $this->cache[$input] ??= $this->parseUncached( $input );
	}

	private function parseUncached( string $input ): Version {
		$version = new Version;

		// Handle and strip revision
		if ( preg_match( '/(.*)-([0-9]+)$/s', $input, $m ) ) {
			$version->setRevision( (int)$m[2] );
			$input = $m[1];
		}

		// Split into tokens
		$offset = 0;
		do {
			$found = preg_match( '/(?:([0-9]+)|([a-zA-Z]+))[._-]*/A', $input, $token, 0, $offset );
			if ( !$found ) {
				throw new VersionParserError( 'invalid version' );
			}
			$offset += strlen( $token[0] );
			$num = $token[1] ?? '';
			$word = $token[2] ?? '';
			if ( $num !== '' ) {
				$version->addNumber( (int)$num );
			} elseif ( $word !== '' ) {
				if ( isset( self::DELTAS[$word] ) ) {
					$version->setDelta( self::DELTAS[$word] );
				} else {
					$version->setDelta( ord( $word[0] ) / 1000 );
				}
			} else {
				throw new \LogicException( 'unreachable' );
			}
		} while ( $offset < strlen( $input ) );
		return $version;
	}

	/**
	 * Get configuration for compareVersions.js
	 *
	 * @return array[]
	 */
	public static function getJsConfig() {
		return [ 'deltas' => self::DELTAS ];
	}
}

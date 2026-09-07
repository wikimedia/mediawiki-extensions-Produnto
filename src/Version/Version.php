<?php

namespace MediaWiki\Extension\Produnto\Version;

/**
 * A version for comparison, similar to the version class in LuaRocks.
 */
class Version {
	private ?int $revision = null;
	private array $parts = [];
	private int|float|null $delta = null;

	/**
	 * Set the revision number, which is the last number in the string after a
	 * hyphen.
	 */
	public function setRevision( int $rev ) {
		$this->revision = $rev;
	}

	/**
	 * Add a numeric component to the version
	 */
	public function addNumber( int $num ) {
		$this->parts[] = $num + ( $this->delta ?? 0 );
		$this->delta = null;
	}

	/**
	 * Set an offset to be used for the next numeric component. This is used for
	 * stability tokens so that e.g. 1.0alpha5 sorts before 1.0.0.
	 */
	public function setDelta( int|float $delta ) {
		$this->delta = $delta;
	}

	/**
	 * If a stability token occurs at the end of the string, like "1.0alpha",
	 * we follow LuaRocks in treating it like "1.0alpha0", as if there were a
	 * zero at the end. Adjust the numeric parts for this.
	 */
	private function flushDelta() {
		if ( $this->delta !== null ) {
			$this->addNumber( 0 );
		}
	}

	/**
	 * Compare version strings
	 *
	 * @param Version $a
	 * @param Version $b
	 * @return int A number less than, equal to or greater than 0 if $a is less
	 *   than, equal to or greater than $b, like $a <=> $b
	 */
	public static function compare( self $a, self $b ): int {
		$a->flushDelta();
		$b->flushDelta();
		$n = max( count( $a->parts ), count( $b->parts ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$comparison = ( $a->parts[$i] ?? 0 ) <=> ( $b->parts[$i] ?? 0 );
			if ( $comparison ) {
				return $comparison;
			}
		}
		if ( $a->revision && $b->revision ) {
			return $a->revision <=> $b->revision;
		}
		return 0;
	}

	/**
	 * Check if a version is a partial match for another version. Unspecified
	 * components in the required version may match specified components in the
	 * present version, for example 1.0 may match 1.0.0.
	 */
	public static function isPartialMatch( self $required, self $have ): bool {
		$required->flushDelta();
		$have->flushDelta();
		foreach ( $required->parts as $i => $part ) {
			if ( $part !== ( $have->parts[$i] ?? null ) ) {
				return false;
			}
		}
		return true;
	}
}

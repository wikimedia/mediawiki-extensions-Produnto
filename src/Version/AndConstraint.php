<?php

namespace MediaWiki\Extension\Produnto\Version;

/**
 * A set of constraints, all of which must pass
 */
class AndConstraint implements Constraint {
	/**
	 * @param Constraint[] $constraints
	 */
	public function __construct(
		private array $constraints
	) {
	}

	public function isSatisfiedBy( Version $version ): bool {
		foreach ( $this->constraints as $constraint ) {
			if ( !$constraint->isSatisfiedBy( $version ) ) {
				return false;
			}
		}
		return true;
	}
}

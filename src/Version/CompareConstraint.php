<?php

namespace MediaWiki\Extension\Produnto\Version;

/**
 * A constraint for a comparison operator
 */
class CompareConstraint implements Constraint {
	public function __construct(
		private string $op,
		private Version $required,
		private bool $noUpgrade
	) {
	}

	public function isSatisfiedBy( Version $version ): bool {
		$comp = Version::compare( $version, $this->required );
		return match ( $this->op ) {
			'==' => !$comp,
			'~=' => (bool)$comp,
			'>' => $comp > 0,
			'<' => $comp < 0,
			'>=' => $comp >= 0,
			'<=' => $comp <= 0,
			'~>' => !$comp || $this->isPartialMatch( $version ),
		};
	}

	private function isPartialMatch( Version $version ): bool {
		return Version::isPartialMatch( $this->required, $version );
	}
}

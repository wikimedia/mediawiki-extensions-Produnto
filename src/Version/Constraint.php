<?php

namespace MediaWiki\Extension\Produnto\Version;

interface Constraint {
	/**
	 * Check if the specified version satisfies this constraint.
	 */
	public function isSatisfiedBy( Version $version ): bool;
}

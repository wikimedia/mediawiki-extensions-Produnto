<?php

namespace MediaWiki\Extension\Produnto\Version;

class AnyConstraint implements Constraint {
	public function isSatisfiedBy( Version $version ): bool {
		return true;
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

class InvalidVersionError extends PackageBuilderError {
	public function __construct() {
		parent::__construct( 'Invalid package version' );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Store;

class InvalidNameError extends PackageBuilderError {
	public function __construct() {
		parent::__construct( 'Invalid package name' );
	}
}

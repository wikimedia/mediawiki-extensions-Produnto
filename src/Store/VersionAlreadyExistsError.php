<?php

namespace MediaWiki\Extension\Produnto\Store;

class VersionAlreadyExistsError extends PackageBuilderError {
	public function __construct() {
		parent::__construct( 'The package version already exists.' );
	}
}

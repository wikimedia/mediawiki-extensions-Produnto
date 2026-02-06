<?php

namespace MediaWiki\Extension\Produnto\Store;

class WrongUrlError extends PackageBuilderError {
	public function __construct() {
		parent::__construct( 'The package already exists with a different URL. ' .
			'Update the URL before fetching a new version.' );
	}
}

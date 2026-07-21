<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\HookContainer\HookContainer;

class HookRunner implements ProduntoPlatformVersionsHook {

	public function __construct( private HookContainer $hookContainer ) {
	}

	public function onProduntoPlatformVersions( array &$versions ): void {
		$this->hookContainer->run(
			'ProduntoPlatformVersions',
			[ &$versions ],
			[ 'abortable' => false ]
		);
	}
}

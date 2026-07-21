<?php

namespace MediaWiki\Extension\Produnto\Updater;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "ProduntoPlatformVersions" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ProduntoPlatformVersionsHook {
	/**
	 * Extensions can implement this hook to add a platform version that
	 * packages can require in their manifest. Add an element to the array
	 * where the key is the name of the thing being provided, and the value
	 * is the version.
	 *
	 * @param array<string,string> &$versions
	 */
	public function onProduntoPlatformVersions( array &$versions ): void;
}

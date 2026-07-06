<?php

namespace MediaWiki\Extension\Produnto\Dashboard;

use MediaWiki\SpecialPage\SpecialPage;

class SpecialProduntoPackages extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ProduntoPackages' );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'produnto-dashboard-special-desc' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'produnto-update';
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$out = $this->getOutput();
		$out->addModules( 'ext.produnto.Dashboard' );

		// For the early progress spinner
		$out->addModuleStyles( 'codex-styles' );

		$out->addElement( 'noscript', [],
			$this->msg( 'produnto-javascript-required' )->text()
		);

		$out->addJsConfigVars( [
			'wgProduntoPackagesTitle' => $this->getConfig()->get( 'ProduntoPackagesTitle' ),
		] );

		$out->addHTML(
			<<<HTML
<div id="ext-produnto-dashboard-vue-root" class="ext-produnto-dashboard-vue-app">
  <div class="cdx-progress-indicator">
    <div class="cdx-progress-indicator__indicator">
      <progress
        class="cdx-progress-indicator__indicator__progress"
        aria-label="ProgressIndicator label"
      ></progress>
    </div>
  </div>
</div>
HTML
		);
	}
}

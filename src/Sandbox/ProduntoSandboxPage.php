<?php

namespace MediaWiki\Extension\Produnto\Sandbox;

use MediaWiki\SpecialPage\SpecialPage;

class ProduntoSandboxPage extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ProduntoSandbox' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->requireLogin();
		$out = $this->getOutput();
		$out->addModules( 'ext.produnto.SpecialProduntoSandbox' );
		// For the early progress spinner
		$out->addModuleStyles( 'codex-styles' );
		$out->addElement( 'noscript', [],
			$this->msg( 'produnto-javascript-required' )->text()
		);
		$out->addHTML(
			<<<HTML
<div id="ext-produnto-sandbox-vue-root" class="ext-produnto-sandbox-vue-app">
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

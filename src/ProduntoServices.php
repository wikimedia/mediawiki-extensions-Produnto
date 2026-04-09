<?php

namespace MediaWiki\Extension\Produnto;

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Runtime\RuntimeFactory;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\MediaWikiServices;
use Wikimedia\Services\ServiceContainer;

class ProduntoServices {
	private ServiceContainer $services;

	public function __construct(
		?ServiceContainer $services = null
	) {
		$this->services = $services ?? MediaWikiServices::getInstance();
	}

	public function getFetcher(): Fetcher {
		return $this->services->get( 'Produnto.Fetcher' );
	}

	public function getRuntimeFactory(): RuntimeFactory {
		return $this->services->get( 'Produnto.RuntimeFactory' );
	}

	public function getSandboxStore(): SandboxStore {
		return $this->services->get( 'Produnto.SandboxStore' );
	}

	public function getServerContainer(): ServerContainer {
		return $this->services->get( 'Produnto.ServerContainer' );
	}

	public function getStore(): ProduntoStore {
		return $this->services->get( 'Produnto.Store' );
	}

	public function getUpdater(): Updater {
		return $this->services->get( 'Produnto.Updater' );
	}
}

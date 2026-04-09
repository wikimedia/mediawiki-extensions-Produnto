<?php

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\Runtime\RuntimeFactory;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Extension\Produnto\Updater\Updater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'Produnto.Fetcher' => static function ( MediaWikiServices $services ) {
		return new Fetcher(
			$services->get( 'Produnto.Store' ),
			$services->get( 'Produnto.ServerContainer' ),
			$services->getJobQueueGroup(),
			LoggerFactory::getInstance( 'Produnto' ),
			$services->get( 'Produnto.ManifestFactory' ),
		);
	},

	'Produnto.Logger' => static function ( MediaWikiServices $services ) {
		return LoggerFactory::getInstance( 'Produnto' );
	},

	'Produnto.ManifestFactory' => static function ( MediaWikiServices $services ) {
		return new ManifestFactory();
	},

	'Produnto.RuntimeFactory' => static function ( MediaWikiServices $services ) {
		return new RuntimeFactory(
			$services->get( 'Produnto.Store' ),
			$services->get( 'Produnto.SandboxStore' )
		);
	},

	'Produnto.ServerContainer' => static function ( MediaWikiServices $services ) {
		return new ServerContainer(
			$services->getHttpRequestFactory(),
			$services->getMainConfig()
		);
	},

	'Produnto.SandboxStore' => static function ( MediaWikiServices $services ) {
		return new SandboxStore(
			$services->get( 'Produnto.Store' ),
			$services->getMainObjectStash()
		);
	},

	'Produnto.Store' => static function ( MediaWikiServices $services ) {
		return new ProduntoStore(
			$services->getConnectionProvider()
		);
	},

	'Produnto.Updater' => static function ( MediaWikiServices $services ) {
		return new Updater(
			$services->get( 'Produnto.Store' )
		);
	}
];

<?php

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
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
			new ManifestFactory()
		);
	},

	'Produnto.Logger' => static function ( MediaWikiServices $services ) {
		return LoggerFactory::getInstance( 'Produnto' );
	},

	'Produnto.ServerContainer' => static function ( MediaWikiServices $services ) {
		return new ServerContainer(
			$services->getHttpRequestFactory(),
			$services->getMainConfig()
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

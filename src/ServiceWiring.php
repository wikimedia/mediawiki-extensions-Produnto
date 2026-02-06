<?php

use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'Produnto.Fetcher' => static function ( MediaWikiServices $services ) {
		return new Fetcher(
			$services->get( 'Produnto.Store' ),
			$services->get( 'Produnto.ServerContainer' ),
			$services->getJobQueueGroup(),
			LoggerFactory::getInstance( 'Produnto' )
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
	}
];

<?php

namespace MediaWiki\Extension\Produnto\Maintenance;

use Maintenance;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Produnto\Fetcher\Fetcher;
use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Server\GitServer;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\PackageBuilderError;
use MediaWiki\Language\FormatterFactory;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class Fetch extends Maintenance {
	/** @var Fetcher */
	private $fetcher;
	/** @var FormatterFactory */
	private $formatterFactory;
	/** @var ServerContainer */
	private $serverContainer;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Produnto' );
		$this->addDescription( 'Fetch a package' );
		$this->addOption( 'async', 'Fetch asynchronously' );
		$this->addOption( 'name', 'Project name to fetch', true, withArg: true );
		$this->addOption( 'url', 'URL to fetch', true, withArg: true );
		$this->addOption( 'version', 'Version to fetch', withArg: true );
		$this->addOption( 'ref', 'ref to fetch', true, withArg: true );
	}

	/** @inheritDoc */
	public function execute() {
		$this->initServices();

		$name = $this->getOption( 'name' );
		$url = $this->getOption( 'url' );
		$ref = $this->getOption( 'ref' );

		$server = $this->serverContainer->getServerForUrl( $url );
		if ( !$server ) {
			$this->fatalError( "No server known for that URL" );
		}
		if ( $server instanceof GitServer ) {
			$version = $server->refToVersion( $ref );
			if ( $version === null ) {
				$this->fatalError( "Invalid ref \"$ref\", can't convert it to a version" );
			}
			if ( $this->hasOption( 'version' )
				&& $version !== $this->getOption( 'version' )
			) {
				$this->fatalError( "Incorrect version for ref, must be \"$version\"" );
			}
		} else {
			$this->fatalError( "Don't know how to fetch from server of type \"" . get_class( $server ) );
		}

		if ( $this->getOption( 'async' ) ) {
			return $this->fetchAsync( $name, $url, $version, $ref );
		} else {
			return $this->fetchSync( $name, $url, $version, $ref );
		}
	}

	private function initServices() {
		$services = $this->getServiceContainer();
		$prodSvc = new ProduntoServices( $services );
		$this->fetcher = $prodSvc->getFetcher();
		$this->serverContainer = $prodSvc->getServerContainer();
		$this->formatterFactory = $services->getFormatterFactory();
	}

	/**
	 * @param string $name
	 * @param string $url
	 * @param string $version
	 * @param string $ref
	 * @return bool
	 */
	private function fetchAsync( $name, $url, $version, $ref ) {
		try {
			$this->fetcher->asyncFetch( $name, $url, $version, $ref );
			$this->output( "Fetch queued\n" );
		} catch ( PackageBuilderError $e ) {
			$this->output( "Error fetching package: {$e->getMessage()}\n" );
			return false;
		}
		return true;
	}

	/**
	 * @param string $name
	 * @param string $url
	 * @param string $version
	 * @param string $ref
	 * @return bool
	 */
	private function fetchSync( $name, $url, $version, $ref ) {
		try {
			$status = $this->fetcher->immediateFetch( $name, $url, $version, $ref );
		} catch ( PackageBuilderError $e ) {
			$this->output( "{$e->getMessage()}\n" );
			return false;
		}
		if ( $status->isOK() ) {
			$this->output( "Fetch successful\n" );
			return true;
		} else {
			$statusFormatter = $this->formatterFactory
				->getStatusFormatter( RequestContext::getMain() );
			$this->output( $statusFormatter->getWikiText( $status ) . "\n" );
			return false;
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = Fetch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd

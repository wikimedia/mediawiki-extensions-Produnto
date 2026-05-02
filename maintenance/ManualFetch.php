<?php

namespace MediaWiki\Extension\Produnto\Maintenance;

use Maintenance;
use MediaWiki\Extension\Produnto\ProduntoServices;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class ManualFetch extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'Produnto' );
		$this->addDescription( 'Manually Fetch a package asynchronously' );
		$this->addOption( 'name', 'Project name to fetch', true, withArg: true );
		$this->addOption( 'url', 'URL to fetch', true, withArg: true );
		$this->addOption( 'version', 'Version to fetch', true, withArg: true );
		$this->addOption( 'ref', 'ref to fetch', true, withArg: true );
	}

	public function execute() {
		$fetcher = ( new ProduntoServices( $this->getServiceContainer() ) )->getFetcher();
		$fetcher->asyncFetch(
			$this->getOption( 'name' ),
			$this->getOption( 'url' ),
			$this->getOption( 'version' ),
			$this->getOption( 'ref' )
		);
	}
}

// @codeCoverageIgnoreStart
$maintClass = ManualFetch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd

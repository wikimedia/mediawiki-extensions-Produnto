<?php

namespace MediaWiki\Extension\Produnto\Maintenance;

use MediaWiki\Extension\Produnto\ProduntoServices;
use MediaWiki\Extension\Produnto\Store\LocalFileCollection;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

class Import extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Import packages from the local filesystem' );
		$this->addArg( 'path', 'paths to import', multi: true );
		$this->addOption( 'version',
			'"<name>:<version>" specifying the version number to use for a given path',
			withArg: true,
			multiOccurrence: true
		);
	}

	public function execute() {
		$services = new ProduntoServices( $this->getServiceContainer() );
		$store = $services->getStore();
		$manifestFactory = $services->getManifestFactory();
		$paths = $this->getArgs();

		$packages = [];

		foreach ( $paths as $path ) {
			if ( !is_dir( $path ) ) {
				$this->fatalError( "Path \"$path\" is not a directory" );
			}
			if ( !is_file( "$path/produnto.json" ) ) {
				$this->fatalError( "Manifest file \"$path/produnto.json\" not found" );
			}
			$name = basename( $path );
			if ( isset( $packages[$name] ) ) {
				$this->fatalError( "Duplicate package name \"$name\"" );
			}
			$packages[$name] = [
				'name' => $name,
				'path' => $path,
				'version' => '0.0',
			];
		}

		$versionsOpt = (array)$this->getOption( 'version', [] );
		foreach ( $versionsOpt as $v ) {
			$parts = explode( ':', $v, 2 );
			if ( count( $parts ) !== 2 ) {
				$this->fatalError(
					"Invalid version specification \"$v\", must be in the form name:version" );
			}
			[ $name, $version ] = $parts;
			if ( !isset( $packages[$name] ) ) {
				$this->fatalError( "No package found for version \"$v\"" );
			}
			$packages[$name]['version'] = $version;
		}

		foreach ( $packages as $package ) {
			[ 'name' => $name, 'path' => $path, 'version' => $version ] = $package;
			$builder = $store->createPackageVersion()
				->name( $name )
				->version( $version )
				->fetchedUrl( "file://" . realpath( $path ) );

			$files = new LocalFileCollection( $path );
			$status = $manifestFactory->parseManifest( $files );
			if ( !$status->isOK() ) {
				$this->fatalError( $status );
			}
			$status->value->populateProps( $builder );
			foreach ( $files->getFilePaths() as $path ) {
				$contents = $files->getFileContents( $path );
				if ( $contents === null ) {
					$this->fatalError( "Error reading file \"$path\"" );
				}
				$builder->addFile( $path, $contents );
			}
			$builder->commit();
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = Import::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd

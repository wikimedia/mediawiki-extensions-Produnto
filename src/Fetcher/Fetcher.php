<?php

namespace MediaWiki\Extension\Produnto\Fetcher;

use Exception;
use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Extension\Produnto\Store\PackageBuilderError;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Control class for fetching code from a server
 */
class Fetcher {
	public function __construct(
		private ProduntoStore $store,
		private ServerContainer $serverContainer,
		private JobQueueGroup $jobQueueGroup,
		private LoggerInterface $logger,
		private ManifestFactory $manifestFactory,
	) {
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param string $projectName
	 * @param string $url
	 * @param string $version
	 * @param string $ref
	 * @throws PackageBuilderError
	 */
	public function asyncFetch( $projectName, $url, $version, $ref ) {
		$packageBuilder = $this->store->createPackageVersion();
		$package = $packageBuilder
			->name( $projectName )
			->fetchedUrl( $url )
			->version( $version )
			->upstreamRef( $ref )
			->suspend();
		$this->jobQueueGroup->push( FetchJob::newSpec( $package->getId() ) );
	}

	public function fetch( int $packageId ): bool {
		$package = $this->store->getPackageById( $packageId, IDBAccessObject::READ_LOCKING );
		if ( !$package ) {
			$this->logger->error( 'Fetcher: no such package ID {packageId}',
				[ 'packageId' => $packageId ] );
			// Can't report failure on a non-existent package
			return true;
		}
		$packageBuilder = $this->store->resumePackageBuilder( $package );
		try {
			return $this->fetchPackage( $package, $packageBuilder );
		} catch ( DBError $e ) {
			// Writing the status is unsafe
			throw $e;
		} catch ( Exception $e ) {
			$status = new FetchStatus();
			$status->genericError( 'caught exception of class "' .
				get_class( $e ) . '" [' . WebRequest::getRequestId() . ']' );
			$packageBuilder->fail( $status );
			throw $e;
		}
	}

	/**
	 * Fetch in an exception guarded context
	 *
	 * @param PackageAccess $package
	 * @param PackageBuilder $packageBuilder
	 * @return bool
	 */
	private function fetchPackage( PackageAccess $package, PackageBuilder $packageBuilder ) {
		$status = new FetchStatus();
		$server = $this->serverContainer->getServerForUrl( $package->getFetchedUrl() );
		if ( !$server ) {
			$this->logger->error( 'Fetcher: server not found for URL {url}',
				[ 'url' => $package->getFetchedUrl() ] );
			$status->genericError( 'Server is no longer configured' );
			$packageBuilder->fail( $status );
			// Do not retry
			return true;
		}
		$status = $server->fetch( $package, $packageBuilder );
		if ( !$status->isOK() ) {
			$this->logger->error( 'Fetch failure: {status}',
				[ 'status' => (string)$status ] );
			$packageBuilder->fail( $status );
			if ( $status->hasMessage( 'produnto-fetch-server-error' )
				|| $status->hasMessage( 'produnto-fetch-connect-error' )
			) {
				// Retry
				return false;
			} else {
				return true;
			}
		}

		$manifestStatus = $this->manifestFactory->parseManifest( $packageBuilder->suspend() );
		if ( !$manifestStatus->isOK() ) {
			$packageBuilder->fail( $manifestStatus );
			return true;
		}

		$manifestStatus->value->populateProps( $packageBuilder );
		$packageBuilder->commit();

		return true;
	}
}

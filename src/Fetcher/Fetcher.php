<?php

namespace MediaWiki\Extension\Produnto\Fetcher;

use Exception;
use MediaWiki\Extension\Produnto\Manifest\ManifestFactory;
use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Extension\Produnto\Store\PackageBuilderError;
use MediaWiki\Extension\Produnto\Store\PackageMetaAccess;
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
		private readonly ProduntoStore $store,
		private readonly ServerContainer $serverContainer,
		private readonly JobQueueGroup $jobQueueGroup,
		private LoggerInterface $logger,
		private readonly ManifestFactory $manifestFactory,
	) {
	}

	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Queue a job which will fetch a package and report any failure via the DB.
	 *
	 * @throws PackageBuilderError
	 */
	public function asyncFetch( string $projectName, string $url, string $version, string $ref ): void {
		$packageBuilder = $this->store->createPackageVersion();
		$package = $packageBuilder
			->name( $projectName )
			->fetchedUrl( $url )
			->version( $version )
			->upstreamRef( $ref )
			->suspend();
		$this->jobQueueGroup->push( FetchJob::newSpec( $package->getId() ) );
	}

	/**
	 * Fetch a package immediately, returning any error status.
	 *
	 * @throws PackageBuilderError
	 */
	public function immediateFetch( string $projectName, string $url, string $version, string $ref ): FetchStatus {
		$package = $this->store->getPackageByName(
			$projectName, $version, IDBAccessObject::READ_LATEST );
		if ( $package ) {
			if ( $package->getState() !== ProduntoStore::STATE_FAILED ) {
				$status = new FetchStatus;
				$status->genericError( 'This package was already fetched' );
				return $status;
			}
			$packageBuilder = $this->store->resumePackageBuilder( $package );
		} else {
			$packageBuilder = $this->store->createPackageVersion();
			$package = $packageBuilder
				->name( $projectName )
				->fetchedUrl( $url )
				->version( $version )
				->upstreamRef( $ref )
				->accessMeta();
		}
		$status = $this->fetchPackage( $package, $packageBuilder );
		if ( $status->isOK() ) {
			$packageBuilder->commit();
		} else {
			if ( $packageBuilder->isInserted() ) {
				$packageBuilder->fail( $status );
			}
		}
		return $status;
	}

	/**
	 * Fetch a suspended package and store any failure in the DB
	 *
	 * @return bool Whether the job should be considered successful
	 */
	public function fetchSuspended( int $packageId ): bool {
		$package = $this->store->getPackageById( $packageId, IDBAccessObject::READ_LOCKING );
		if ( !$package ) {
			$this->logger->error( 'Fetcher: no such package ID {packageId}',
				[ 'packageId' => $packageId ] );
			// Can't report failure on a non-existent package
			return true;
		}
		$packageBuilder = $this->store->resumePackageBuilder( $package );
		try {
			$status = $this->fetchPackage( $package, $packageBuilder );
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
		if ( $status->isOK() ) {
			$packageBuilder->commit();
		} else {
			$packageBuilder->fail( $status );
		}
		return !$status->retry;
	}

	/**
	 * Fetch in an exception-guarded context. Do not commit or fail.
	 */
	private function fetchPackage( PackageMetaAccess $package, PackageBuilder $packageBuilder ): FetchStatus {
		$status = new FetchStatus();
		$server = $this->serverContainer->getServerForUrl( $package->getFetchedUrl() );
		if ( !$server ) {
			$this->logger->error( 'Fetcher: server not found for URL {url}',
				[ 'url' => $package->getFetchedUrl() ] );
			$status->genericError( 'Server is no longer configured' );
			return $status;
		}
		$status = $server->fetch( $package, $packageBuilder );
		if ( !$status->isOK() ) {
			$this->logger->error( 'Fetch failure: {status}',
				[ 'status' => (string)$status ] );
			if ( $status->hasMessage( 'produnto-fetch-server-error' )
				|| $status->hasMessage( 'produnto-fetch-connect-error' )
			) {
				$status->retry = true;
			}
			return $status;
		}

		$manifestStatus = $this->manifestFactory->parseManifest( $packageBuilder->suspend() );
		if ( !$manifestStatus->isOK() ) {
			$status->merge( $manifestStatus );
			return $status;
		}

		$manifestStatus->value->populateProps( $packageBuilder );

		return $status;
	}
}

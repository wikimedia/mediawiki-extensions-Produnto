<?php

namespace MediaWiki\Extension\Produnto\Fetcher;

use MediaWiki\Extension\Produnto\Server\ServerContainer;
use MediaWiki\Extension\Produnto\Store\PackageBuilderError;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\JobQueue\JobQueueGroup;
use Psr\Log\LoggerInterface;
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
	) {
	}

	/**
	 * @param string $projectName
	 * @param string $url
	 * @param string $version
	 * @throws PackageBuilderError
	 */
	public function asyncFetch( $projectName, $url, $version ) {
		$packageBuilder = $this->store->createPackageVersion();
		$package = $packageBuilder
			->name( $projectName )
			->url( $url )
			->version( $version )
			->suspend();
		$this->jobQueueGroup->push( FetchJob::newSpec( $package->getId() ) );
	}

	public function fetch( int $packageId ): bool {
		$status = new FetchStatus();
		$package = $this->store->getPackageById( $packageId, IDBAccessObject::READ_LOCKING );
		if ( !$package ) {
			$this->logger->error( 'Fetcher: no such package ID {packageId}',
				[ 'packageId' => $packageId ] );
			// Can't report failure on a non-existent package
			return true;
		}
		$packageBuilder = $this->store->resumePackageBuilder( $package );
		$server = $this->serverContainer->getServerForUrl( $package->getUrl() );
		if ( !$server ) {
			$this->logger->error( 'Fetcher: server not found for URL {url}',
				[ 'url' => $package->getUrl() ] );
			$status->genericError( 'Server is no longer configured' );
			$packageBuilder->fail( $status );
			return true;
		}
		$status = $server->fetch( $package, $packageBuilder );
		if ( $status->isOK() ) {
			$packageBuilder->commit();
			return true;
		} else {
			$this->logger->error( 'Fetch failure: {status}',
				[ 'status' => (string)$status ] );
			$packageBuilder->fail( $status );
			if ( $status->hasMessage( 'produnto-fetch-server-error' ) ) {
				// Retry
				return false;
			} else {
				return true;
			}
		}
	}
}

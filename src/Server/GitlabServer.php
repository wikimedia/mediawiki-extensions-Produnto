<?php

namespace MediaWiki\Extension\Produnto\Server;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use MediaWiki\Extension\Produnto\Fetcher\FetchStatus;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\PackageBuilder;
use MediaWiki\Http\HttpRequestFactory;
use StatusValue;
use ZipArchive;

/**
 * Client for a single GitLab server
 */
class GitlabServer extends GitServer {
	private string $url;
	private array $projectPrefixes;
	private ?string $proxy;
	private float|int $maxFileSize;

	/**
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param array $config Associative array:
	 *   - url: The base URL of the GitLab installation (external_url)
	 *   - projectPrefixes: An array of strings specifying allowable project
	 *     path prefixes, e.g. "repos/scribunto"
	 *   - proxy: The HTTP proxy to use to contact GitLab
	 *   - maxFileSize: The maximum size of each file in a package in bytes
	 *     (default 10 MiB)
	 */
	public function __construct(
		private HttpRequestFactory $httpRequestFactory,
		array $config
	) {
		$this->url = self::addTrailingSlash( $config['url'] );
		$this->projectPrefixes = $config['projectPrefixes'];
		$this->proxy = $config['proxy'] ?? null;
		$this->maxFileSize = $config['maxFileSize'] ?? 10 * 1024 * 1024;
	}

	/**
	 * Add a trailing slash if the string doesn't already have one
	 *
	 * @param string $s
	 * @return string
	 */
	private function addTrailingSlash( $s ) {
		if ( $s === '' ) {
			return '';
		} elseif ( !str_ends_with( $s, '/' ) ) {
			return $s . '/';
		} else {
			return $s;
		}
	}

	/**
	 * Extract the package name from a project URL
	 *
	 * @param string $url
	 * @return string|null
	 */
	public function urlToName( $url ): ?string {
		if ( !str_starts_with( $url, $this->url ) ) {
			return null;
		}
		$project = substr( $url, strlen( $this->url ) );
		foreach ( $this->projectPrefixes as $prefix ) {
			$prefix = self::addTrailingSlash( $prefix );
			if ( str_starts_with( $project, $prefix ) ) {
				return substr( $project, strlen( $prefix ) );
			}
		}
		return null;
	}

	public function hasUrl( string $url ): bool {
		return $this->urlToName( $url ) !== null;
	}

	/**
	 * Extract the project path from a project URL
	 *
	 * @param string $url
	 * @return string
	 */
	private function urlToProjectPath( $url ) {
		if ( !str_starts_with( $url, $this->url ) ) {
			throw new InvalidArgumentException( 'URL is not on this server' );
		}
		return substr( $url, strlen( $this->url ) );
	}

	/**
	 * Fetch a package from the server and insert the contents into the database.
	 *
	 * @param PackageAccess $package
	 * @param PackageBuilder $dest
	 * @return StatusValue
	 */
	public function fetch( PackageAccess $package, PackageBuilder $dest ): StatusValue {
		$status = new FetchStatus;
		$client = $this->createGuzzleClient();
		$file = tmpfile();
		$fileName = stream_get_meta_data( $file )['uri'];
		try {
			$response =
				$client->get(
					$this->url . 'api/v4/projects/' .
					rawurlencode( $this->urlToProjectPath( $package->getFetchedUrl() ) ) .
					'/repository/archive.zip',
					[ RequestOptions::SINK => $file ]
				);
		} catch ( GuzzleException $e ) {
			$status->guzzleError( $e );
			return $status;
		}
		if ( $response->getStatusCode() !== 200 ) {
			$status->httpError( $response );
			return $status;
		}
		$archive = new ZipArchive;
		$openStatus = $archive->open( $fileName, ZipArchive::RDONLY );
		if ( $openStatus !== true ) {
			$status->genericError( 'Unable to open zip file: ' . $archive->getStatusString() );
			return $status;
		}
		for ( $i = 0; $i < $archive->numFiles; $i++ ) {
			$stat = $archive->statIndex( $i );
			$name = $this->stripInitialPathSegment( $stat['name'] );
			if ( $name === null ) {
				// Ignore files not in a directory
				continue;
			}
			if ( $stat['size'] >= $this->maxFileSize ) {
				$status->genericError( "The file \"$name\" is too big" );
				continue;
			}
			$contents = $archive->getFromIndex( $i );
			if ( $contents === false ) {
				$status->genericError( "Error reading zip file entry \"$name\": " .
					$archive->getStatusString() );
				continue;
			}
			$dest->addFile( $name, $contents );
		}
		return $status;
	}

	/**
	 * Get a Guzzle client suitable for fetching from the Gitlab server.
	 *
	 * @return \GuzzleHttp\Client
	 */
	private function createGuzzleClient() {
		$opts = [];
		if ( $this->proxy !== null ) {
			$opts['proxy'] = $this->proxy;
		}
		return $this->httpRequestFactory->createGuzzleClient( $opts );
	}

	/**
	 * Strip the initial directory from a path
	 *
	 * @param string $name
	 * @return string|null
	 */
	private function stripInitialPathSegment( $name ) {
		$slashPos = strpos( $name, '/' );
		if ( $slashPos === false || $slashPos >= strlen( $name ) - 1 ) {
			return null;
		}
		return substr( $name, $slashPos + 1 );
	}
}

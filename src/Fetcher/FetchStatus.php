<?php

namespace MediaWiki\Extension\Produnto\Fetcher;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;
use StatusValue;
use Wikimedia\Http\HttpStatus;

/**
 * A StatusValue with convenience methods for predefined errors
 *
 * @extends StatusValue<never>
 */
class FetchStatus extends StatusValue {
	public function genericError( string $message ) {
		$this->error( 'produnto-fetch-error', $message );
	}

	public function httpError( ResponseInterface $response ) {
		$code = $response->getStatusCode();
		if ( $code >= 500 && $code < 600 ) {
			$message = 'produnto-fetch-server-error';
		} else {
			$message = 'produnto-fetch-http-error';
		}
		$reason = $response->getReasonPhrase();
		if ( $reason === '' ) {
			$reason = HttpStatus::getMessage( $code ) ?? "$code";
		}

		$this->error(
			$message,
			$reason,
			Psr7\Message::bodySummary( $response, 1000 ) ?? ''
		);
	}

	public function guzzleError( GuzzleException $exception ) {
		$class = get_class( $exception );
		$reason = match ( $class ) {
			ConnectException::class => 'connection failed',
			default => $class
		};
		$this->error( 'produnto-fetch-server-error', $reason );
	}
}

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
	public bool $retry = false;

	public function genericError( string $message ): void {
		$this->fatal( 'produnto-fetch-error', $message );
	}

	public function httpError( ResponseInterface $response ): void {
		$code = $response->getStatusCode();
		if ( $code >= 500 && $code < 600 ) {
			$message = 'produnto-fetch-server-error';
			$this->retry = true;
		} else {
			$message = 'produnto-fetch-http-error';
		}
		$reason = $response->getReasonPhrase();
		if ( $reason === '' ) {
			$reason = HttpStatus::getMessage( $code ) ?? "$code";
		}

		$this->fatal(
			$message,
			$code,
			$reason,
			Psr7\Message::bodySummary( $response, 1000 ) ?? ''
		);
	}

	public function guzzleError( GuzzleException $exception ): void {
		$class = get_class( $exception );
		$reason = match ( $class ) {
			ConnectException::class => 'connection failed',
			default => $class
		};
		$this->fatal( 'produnto-fetch-connect-error', $reason );
		$this->retry = true;
	}

	public function toJsonArray(): array {
		$data = parent::toJsonArray();
		if ( $this->retry ) {
			$data['retry'] = $this->retry;
		}
		return $data;
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ) {
		 $status = parent::newFromJsonArray( $json );
		 $status->retry = $json['retry'] ?? false;
		 return $status;
	}
}

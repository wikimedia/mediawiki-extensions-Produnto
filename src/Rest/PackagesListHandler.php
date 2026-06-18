<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Language\Language;
use MediaWiki\Message\MessageFormatterFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Handler;
use Wikimedia\ParamValidator\ParamValidator;

class PackagesListHandler extends Handler {
	use Handler\Helper\RestStatusTrait;

	private int $partitionSize;

	/** @var array<int,int>|null */
	private ?array $packageStates = null;

	public function __construct(
		Config $config,
		private ProduntoStore $store,
		private PermissionManager $permissionManager,
		private Language $contLang,
		private MessageFormatterFactory $formatterFactory,
	) {
		$this->partitionSize = $config->get( 'ProduntoApiBatchSizes' )['packages'];
	}

	/** @inheritDoc */
	public function getETag() {
		// A package can be modified after the creation of its row, but that
		// modification is always accompanied by a change in state, so an ID
		// to state map fully specifies the state of the range.
		return '"' . hash( 'sha256', json_encode( $this->getPackageStates() ) ) . '"';
	}

	/** @inheritDoc */
	public function execute() {
		[ $startId, $endId ] = $this->getPartition();
		$items = [];
		$this->packageStates = [];
		$messageTranslator = new MessageTranslator(
			$this->contLang,
			$this->formatterFactory,
			useUserLang: false,
			// Codes are for client-side comparison with MW language codes
			useBcp47: false,
		);
		foreach ( $this->store->getPackagesFromIdRange( $startId, $endId ) as $package ) {
			$this->packageStates[$package->getId()] = $package->getState();
			$props = [
				'name' => $package->getName(),
				'version' => $package->getVersion(),
				'id' => $package->getId(),
				'fetchedUrl' => $package->getFetchedUrl(),
				'upstreamRef' => $package->getUpstreamRef(),
			];
			$optionalProps = [
				'localName' => $package->getLocalNames(),
				'description' => $package->getDescriptions(),
				'type' => $package->getType(),
				'homepageUrl' => $package->getHomepageUrl(),
				'collabUrl' => $package->getCollabUrl(),
				'docUrl' => $package->getDocUrl(),
				'issueUrl' => $package->getIssueUrl(),
				'authors' => $package->getAuthors(),
				'license' => $package->getLicense(),
				'requires' => $package->getRequires(),
				'modules' => $package->getModules(),
			];
			foreach ( $optionalProps as $name => $value ) {
				if ( $value !== null && $value !== [] ) {
					$props[$name] = $value;
				}
			}

			$state = $package->getState();
			if ( $state !== ProduntoStore::STATE_READY ) {
				$props['state'] = match ( $package->getState() ) {
					ProduntoStore::STATE_FETCHING => 'fetching',
					ProduntoStore::STATE_FAILED => 'failed'
				};
			}

			$status = $package->getStatus();
			if ( !$status->isGood() ) {
				$props['errors'] = $messageTranslator->formatMessages( $status->getMessages() );
			}

			$items[] = $props;
		}

		// Sort by package name, so that deflate provides better compression
		usort( $items, static fn ( $a, $b ) => $a['name'] <=> $b['name'] );

		$response = $this->getResponseFactory()->createJson(
			[ 'packages' => $items ] );
		// Permit public caching with revalidation.
		// We use {credentials:omit} on the client side to work around T264631
		if ( $this->permissionManager->isEveryoneAllowed( 'read' ) ) {
			$response->setHeader( 'Cache-Control', 'public' );
		}
		return $response;
	}

	/**
	 * Get the start and end ID of the partition
	 *
	 * @return array{int,int}
	 */
	private function getPartition() {
		$params = $this->getValidatedParams();
		$partition = $params['partition'];
		$startId = $partition * $this->partitionSize;
		$endId = $startId + $this->partitionSize - 1;
		return [ $startId, $endId ];
	}

	/**
	 * Get the map of package IDs to states.
	 *
	 * @return int[]
	 */
	private function getPackageStates(): array {
		if ( $this->packageStates === null ) {
			[ $startId, $endId ] = $this->getPartition();
			$this->packageStates = $this->store->getPackageStatesFromIdRange( $startId, $endId );
		}
		return $this->packageStates;
	}

	/** @inheritDoc */
	public function getParamSettings() {
		return [
			'partition' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}

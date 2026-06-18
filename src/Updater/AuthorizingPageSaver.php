<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Content\JsonContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageStore;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageUpdater;
use MediaWiki\Storage\PageUpdaterFactory;
use StatusValue;
use stdClass;

/**
 * Authorize and execute an update to MediaWiki:Packages.json,
 * and deploy the resulting packages.
 */
class AuthorizingPageSaver {
	private ?string $jsonPatch = null;
	private ?string $summary = null;
	private ?PageUpdater $pageUpdater = null;
	private bool $ignoreWarnings = false;

	public function __construct(
		private PageStore $pageStore,
		private PageUpdaterFactory $pageUpdaterFactory,
		private Updater $updater,
		private Authority $authority,
	) {
	}

	/**
	 * Set JSON with a map of package name to version string, or null to
	 * undeploy the package. These versions will be merged with the active
	 * deployment.
	 */
	public function patchJson( string $json ): self {
		$this->jsonPatch = $json;
		return $this;
	}

	/**
	 * Set the edit summary.
	 */
	public function summary( string $summary ): self {
		$this->summary = $summary;
		return $this;
	}

	/**
	 * Set a flag indicating whether warnings should be ignored.
	 */
	public function ignoreWarnings( bool $ignore = true ): self {
		$this->ignoreWarnings = $ignore;
		return $this;
	}

	/**
	 * Attempt to execute the change.
	 *
	 * @return StatusValue<never>|UpdateStatus
	 */
	public function save(): StatusValue {
		$status = $this->authorize();
		if ( !$status->isGood() ) {
			return $status;
		}
		$status = $this->prepareContent();
		if ( !$status->isOK() ) {
			return $status;
		} elseif ( !$this->ignoreWarnings && !$status->isGood() ) {
			$status->setOK( false );
			return $status;
		}
		if ( !( $status instanceof UpdateStatus ) ) {
			throw new \RuntimeException( 'unexpected class for good status' );
		}

		$json = FormatJson::encode( $status->getData(), "\t", FormatJson::UTF8_OK );
		$content = new JsonContent( $json );
		$pageUpdater = $this->getPageUpdater();
		$pageUpdater->setContent( SlotRecord::MAIN, $content );

		// Don't create a deployment if there was no change
		$pageUpdater->prepareUpdate();
		if ( !$pageUpdater->isChange() ) {
			$status->fatal( 'produnto-update-null-edit' );
			return $status;
		}

		$revision = $pageUpdater->saveRevision( $this->summary ?? '' );
		$status->merge( $pageUpdater->getStatus() );
		if ( !$status->isOK() ) {
			return $status;
		}

		if ( !$revision ) {
			// PageUpdater shouldn't be successful with no revision unless there was a null edit,
			// but we checked for that
			throw new \RuntimeException( 'No revision was created' );
		}

		$status->setRevision( $revision );

		// We have a PageSaveComplete hook, but that runs too late for the REST
		// API, so explicitly run a deployment now. The hook is disarmed by
		// having $status->isDeployed() return true.

		// However, during tests, deployment was already done as an opportunistic update
		if ( !$status->isDeployed() ) {
			$this->updater->deploy( $status, $revision->getId(), $this->authority->getUser() );
		}

		return $status;
	}

	/**
	 * Check whether the edit is allowed, and increment rate limits
	 */
	private function authorize(): StatusValue {
		$status = PermissionStatus::newEmpty();
		$this->authority->authorizeAction( 'produnto-update', $status );
		return $status;
	}

	/**
	 * Get the [[MediaWiki:Packages.json]] page
	 */
	private function getPage(): PageIdentity {
		return $this->pageStore->getPageForLink( $this->updater->getPackagesTitleValue() );
	}

	private function getPageUpdater(): PageUpdater {
		if ( !$this->pageUpdater ) {
			$this->pageUpdater = $this->pageUpdaterFactory->newPageUpdater(
				$this->getPage(), $this->authority->getUser() );
		}
		return $this->pageUpdater;
	}

	/**
	 * Get the content to be saved, wrapped in a status
	 *
	 * @return StatusValue<void>|UpdateStatus
	 */
	private function prepareContent() {
		$prevRev = $this->getPageUpdater()->grabParentRevision();
		$prevContent = $prevRev?->getContent( SlotRecord::MAIN );
		if ( $prevContent instanceof JsonContent ) {
			$prevDataStatus = $prevContent->getData();
			if ( !$prevDataStatus->isOK() ) {
				return $prevDataStatus;
			}
			$data = $prevDataStatus->value;
		} else {
			$data = new \stdClass();
		}

		$patchDataStatus = FormatJson::parse( $this->jsonPatch );
		if ( !$patchDataStatus->isOK() ) {
			return $patchDataStatus;
		}
		$patchData = $patchDataStatus->value;
		if ( !is_object( $patchData ) ) {
			return StatusValue::newFatal( 'produnto-update-invalid' );
		}
		foreach ( $patchData as $packageName => $version ) {
			if ( $version === null || $version === '' ) {
				unset( $data->$packageName );
			} elseif ( is_string( $version ) ) {
				$data->$packageName = $version;
			} else {
				return StatusValue::newFatal( 'produnto-update-invalid' );
			}
		}
		$data = $this->sortObjectByKey( $data );

		$validateStatus = $this->updater->validateDeployment( $data );
		$this->updater->saveValidationResult( $data, $validateStatus );
		if ( $validateStatus->isOK() ) {
			$newJson = FormatJson::encode( $data, "\t", FormatJson::UTF8_OK );
			if ( $newJson === false ) {
				throw new \RuntimeException( 'JSON encoding unexpectedly failed' );
			}
		}
		return $validateStatus;
	}

	/**
	 * Clone a stdClass and sort its properties by name
	 *
	 * @param stdClass $obj
	 * @return stdClass
	 */
	private function sortObjectByKey( stdClass $obj ): stdClass {
		$sortedObj = new stdClass;
		$sortedKeys = array_keys( (array)$obj );
		sort( $sortedKeys );
		foreach ( $sortedKeys as $key ) {
			$sortedObj->$key = $obj->$key;
		}
		return $sortedObj;
	}
}

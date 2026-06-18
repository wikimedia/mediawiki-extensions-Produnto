<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Content\Hook\JsonValidateSaveHook;
use MediaWiki\Content\JsonContent;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use Psr\Log\LoggerInterface;
use StatusValue;

class UpdaterHooks implements
	ContentHandlerDefaultModelForHook,
	JsonValidateSaveHook,
	PageSaveCompleteHook
{
	private LoggerInterface $logger;

	public function __construct(
		private readonly Updater $updater,
	) {
		$this->logger = LoggerFactory::getInstance( 'Produnto' );
	}

	/** @inheritDoc */
	public function onContentHandlerDefaultModelFor( $title, &$model ) {
		if ( $this->isPackagesTitle( $title ) ) {
			$model = CONTENT_MODEL_JSON;
		}
	}

	/** @inheritDoc */
	public function onJsonValidateSave( JsonContent $content, PageIdentity $pageIdentity,
		StatusValue $status
	) {
		if ( !$this->isPackagesTitle( $pageIdentity ) ) {
			return true;
		}
		$jsonStatus = $content->getData();
		if ( !$jsonStatus->isOK() ) {
			$status->merge( $jsonStatus );
			return false;
		}
		$data = $jsonStatus->getValue();

		// Use the validation status created by AuthorizingPageSaver if any
		$validateStatus = $this->updater->getValidationResult( $data )
			?? $this->updater->validateDeployment( $data );

		if ( $validateStatus->isOK() ) {
			$this->updater->saveValidationResult( $data, $validateStatus );
			return true;
		} else {
			$status->merge( $validateStatus );
			return false;
		}
	}

	/** @inheritDoc */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
	) {
		if ( !$this->isPackagesTitle( $wikiPage->getTitle() ) ) {
			return;
		}
		$content = $revisionRecord->getContent( SlotRecord::MAIN );
		if ( $content instanceof JsonContent ) {
			$data = $content->getData()->getValue();
		} else {
			$this->logger->error( 'MediaWiki:Packages.json saved but is not JsonContent' );
			return;
		}
		$updateStatus = $this->updater->getValidationResult( $data );
		if ( !$updateStatus ) {
			$this->logger->error( 'MediaWiki.Packages.json saved but we have no validation result' );
			return;
		}
		if ( $updateStatus->isDeployed() ) {
			// Already done by AuthorizingPageSaver
			return;
		}
		$revId = $revisionRecord->getId();
		if ( !$revId ) {
			throw new \RuntimeException( 'Completed revision has no ID' );
		}
		$this->updater->deploy( $updateStatus, $revId, $user );
	}

	/**
	 * Check if a title is MediaWiki:Packages.json or its configured replacement
	 */
	private function isPackagesTitle( PageIdentity $title ): bool {
		$packagesTitle = $this->updater->getPackagesTitleValue();
		return $packagesTitle->getNamespace() === $title->getNamespace()
			&& $packagesTitle->getDBkey() === $title->getDBkey();
	}
}

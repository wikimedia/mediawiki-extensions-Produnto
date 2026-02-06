<?php

namespace MediaWiki\Extension\Produnto\Updater;

use MediaWiki\Config\Config;
use MediaWiki\Content\Hook\JsonValidateSaveHook;
use MediaWiki\Content\JsonContent;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\Hook\ContentHandlerDefaultModelForHook;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\TitleParser;
use MediaWiki\Title\TitleValue;
use Psr\Log\LoggerInterface;
use StatusValue;

class UpdaterHooks implements
	ContentHandlerDefaultModelForHook,
	JsonValidateSaveHook,
	PageSaveCompleteHook
{
	private LoggerInterface $logger;
	private ?TitleValue $packagesTitle = null;

	/** @var array<string,ValidationStatus> */
	private $validationResults = [];

	public function __construct(
		private Config $config,
		private TitleParser $titleParser,
		private Updater $updater
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
		$validateStatus = $this->updater->validateDeployment( $data );
		if ( $validateStatus->isOK() ) {
			$hash = json_encode( $data );
			$this->saveValidationResult( $hash, $validateStatus );
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
			$hash = json_encode( $content->getData()->getValue() );
		} else {
			$this->logger->error( 'MediaWiki:Packages.json saved but is not JsonContent' );
			return;
		}
		$validateStatus = $this->getValidationResult( $hash );
		if ( !$validateStatus ) {
			$this->logger->error( 'MediaWiki.Packages.json saved but we have no validation result' );
			return;
		}
		$revId = $revisionRecord->getId();
		if ( !$revId ) {
			throw new \RuntimeException( 'Completed revision has no ID' );
		}
		$this->updater->deploy( $validateStatus, $revId );
	}

	/**
	 * Check if a title is MediaWiki:Packages.json or its configured replacement
	 *
	 * @param PageIdentity $title
	 * @return bool
	 */
	private function isPackagesTitle( PageIdentity $title ) {
		if ( !$this->packagesTitle ) {
			$titleText = $this->config->get( 'ProduntoPackagesTitle' );
			if ( $titleText === null ) {
				$this->packagesTitle = new TitleValue( NS_MEDIAWIKI, 'Packages.json' );
			} else {
				$this->packagesTitle = $this->titleParser->parseTitle( $titleText );
			}
		}
		return $this->packagesTitle->getNamespace() === $title->getNamespace()
			&& $this->packagesTitle->getDBkey() === $title->getDBkey();
	}

	/**
	 * Save the validation result pre-save
	 *
	 * @param string $hash
	 * @param ValidationStatus $status
	 */
	private function saveValidationResult( string $hash, ValidationStatus $status ) {
		$this->validationResults[$hash] = $status;
	}

	/**
	 * Retrieve a validation result post-save
	 * @param string $hash
	 * @return ?ValidationStatus
	 */
	private function getValidationResult( string $hash ): ?ValidationStatus {
		return $this->validationResults[$hash] ?? null;
	}
}

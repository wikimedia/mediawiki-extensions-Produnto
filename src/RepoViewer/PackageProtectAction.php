<?php

namespace MediaWiki\Extension\Produnto\RepoViewer;

use MediaWiki\Actions\FormAction;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\Produnto\Store\PackageProtectionStore;
use MediaWiki\Html\Html;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Page\Article;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Xml\XmlSelect;

/**
 * Implements protect action for a package.
 */
class PackageProtectAction extends FormAction {
	public function __construct(
		Article $article,
		private readonly Title $packageTitle,
		private readonly PackageProtectionStore $protection,
		private readonly PermissionManager $permissionManager,
	) {
		parent::__construct( $article, $article->getContext() );
	}

	/** @inheritDoc */
	public function getName() {
		return 'protect';
	}

	/** @inheritDoc */
	protected function checkCanExecute( User $user ) {
		// Since package shadow pages cannot be edited, we can't use getRestriction() because
		// that would also check editing permissions.
		$status = PermissionStatus::newEmpty();
		if ( !$this->getAuthority()->isDefinitelyAllowed( 'protect', $status ) ) {
			throw new PermissionsError( 'protect', $status );
		}
		parent::checkCanExecute( $user );
	}

	/** @inheritDoc */
	protected function getPageTitle() {
		return $this->msg( 'protect-title', $this->packageTitle->getPrefixedText() );
	}

	/** @inheritDoc */
	protected function usesOOUI() {
		return true;
	}

	/** @inheritDoc */
	protected function getFormFields() {
		$options = [];
		foreach ( $this->getLevels() as $level ) {
			$key = $level === '' ? 'protect-default' : "protect-level-$level";
			$message = $this->msg( $key );
			$options[$message->exists() ? $message->text() : $level] = $level;
		}
		$current = $this->protection->getProtection( $this->packageTitle->getDBkey() );
		$expiryOptions = XmlSelect::parseOptionsMessage(
			$this->msg( 'protect-expiry-options' )->inContentLanguage()->text()
		);
		foreach ( $expiryOptions as &$expiry ) {
			if ( in_array( $expiry, [ 'infinite', 'indefinite' ], true ) ) {
				$expiry = 'infinity';
			}
		}
		unset( $expiry );
		if ( $current ) {
			$message = $current['expiry'] === 'infinity'
				? $this->msg( 'protect-existing-expiry-infinity' )
				: $this->msg( 'protect-existing-expiry' )
					->dateTimeParams( $current['expiry'] )
					->dateParams( $current['expiry'] )
					->timeParams( $current['expiry'] );
			$expiryOptions = [ $message->text() => $current['expiry'] ] + array_filter(
				$expiryOptions, static fn ( $expiry ) => $expiry !== $current['expiry']
			);
		}
		return [
			'level' => [
				'type' => 'select',
				'label-message' => 'produnto-protect-level',
				'options' => $options,
				'default' => $current['level'] ?? '',
			],
			'expiry' => [
				'type' => 'selectorother',
				'options' => $expiryOptions,
				'other-message' => 'protect-othertime-op',
				'label-message' => 'protectexpiry',
				'default' => $current['expiry'] ?? 'infinity',
			],
			'reason' => [
				'type' => 'text',
				'label-message' => 'protectcomment',
			],
		];
	}

	/** @inheritDoc */
	protected function postText() {
		$log = '';
		LogEventsList::showLogExtract( $log, 'protect', $this->packageTitle, '', [], $this->getContext() );
		return Html::element( 'h2', [], $this->msg( 'protectlogpage' )->text() ) . $log;
	}

	/** @inheritDoc */
	public function onSubmit( $data ) {
		$authority = $this->getAuthority();
		$status = PermissionStatus::newEmpty();
		if ( !$authority->authorizeAction( 'protect', $status ) ) {
			return Status::wrap( $status );
		}
		$level = $data['level'];
		if ( !in_array( $level, $this->getLevels(), true ) ) {
			return Status::newFatal( 'badaccess-group0' );
		}
		$expiry = $data['expiry'];
		if ( in_array( $expiry, [ 'infinite', 'indefinite' ], true ) ) {
			$expiry = 'infinity';
		}
		if ( $level !== '' && $expiry !== 'infinity' ) {
			$time = strtotime( $expiry );
			if ( $time === false || $time <= time() ) {
				return Status::newFatal( 'protect_expiry_invalid' );
			}
			$expiry = wfTimestamp( TS_MW, $time );
		}
		$this->protection->setProtection(
			$authority->getUser(), $this->packageTitle->getDBkey(), $level, $expiry, $data['reason']
		);
		return Status::newGood();
	}

	/** @return string[] */
	private function getLevels(): array {
		return $this->permissionManager->getNamespaceRestrictionLevels( NS_PACKAGE, $this->getUser() );
	}

	/** @inheritDoc */
	public function onSuccess() {
		$this->getOutput()->redirect( $this->getTitle()->getFullURL() );
	}
}

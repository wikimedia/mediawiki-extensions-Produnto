<?php

namespace MediaWiki\Extension\Produnto\Store;

use InvalidArgumentException;
use MediaWiki\Extension\Produnto\RepoViewer\RepoLinker;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Title-keyed storage for package protections, which have no page IDs.
 * Authorization and input validation belong to callers of this store.
 */
class PackageProtectionStore {
	public function __construct(
		private readonly RepoLinker $repoLinker,
		private readonly IConnectionProvider $dbProvider,
		private readonly RestrictionStore $restrictionStore,
	) {
	}

	/**
	 * Read the active protection for a package from a replica.
	 *
	 * @return array{level:string,expiry:string}|null
	 */
	public function getProtection( string $package ): ?array {
		return $this->getProtections( [ $package ] )[$package] ?? null;
	}

	/**
	 * Batch-load active protections, keyed by the supplied package names.
	 * Unprotected packages and invalid titles are omitted.
	 *
	 * This query stays in Produnto to load an entire deployment in one query;
	 * core's getCreateProtection() loads one title at a time.
	 *
	 * @param string[] $packages
	 * @param int $recency Use READ_LATEST only when authorizing or performing a write.
	 * @return array<string,array{level:string,expiry:string}>
	 */
	public function getProtections( array $packages, int $recency = IDBAccessObject::READ_NORMAL ): array {
		$namesByTitle = [];
		foreach ( $packages as $package ) {
			$link = $this->repoLinker->getPackageLinkTarget( $package );
			if ( $link ) {
				$namesByTitle[$link->getDBkey()][] = $package;
			}
		}
		if ( !$namesByTitle ) {
			return [];
		}
		$db = DBAccessObjectUtils::getDBFromRecency( $this->dbProvider, $recency );
		$rows = $db->newSelectQueryBuilder()
			->select( [ 'pt_title', 'pt_create_perm', 'pt_expiry' ] )
			->from( 'protected_titles' )
			->where( [ 'pt_namespace' => NS_PACKAGE, 'pt_title' => array_keys( $namesByTitle ) ] )
			->recency( $recency )
			->caller( __METHOD__ )
			->fetchResultSet();

		$protections = [];
		$now = wfTimestampNow();
		foreach ( $rows as $row ) {
			$expiry = $db->decodeExpiry( $row->pt_expiry );
			if ( $expiry !== 'infinity' && $expiry <= $now ) {
				continue;
			}
			foreach ( $namesByTitle[$row->pt_title] as $package ) {
				$protections[$package] = [ 'level' => $row->pt_create_perm, 'expiry' => $expiry ];
			}
		}
		return $protections;
	}

	/**
	 * Store and log protection without checking permissions.
	 *
	 * @param UserIdentity $user User applying the protection
	 * @param string $package Package name, not a file subpage
	 * @param string $level Validated protection level, or empty to remove protection
	 * @param string $expiry Validated 14-digit timestamp or 'infinity'
	 * @param string $reason Reason for the change
	 */
	public function setProtection(
		UserIdentity $user, string $package, string $level, string $expiry, string $reason
	): void {
		$link = $this->repoLinker->getPackageLinkTarget( $package );
		if ( !$link || str_contains( $package, '/' ) || $package === '' ) {
			throw new InvalidArgumentException( 'Invalid package title' );
		}
		$title = Title::newFromLinkTarget( $link );
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );
		$previous = $this->getProtections( [ $package ], IDBAccessObject::READ_LATEST )[$package] ?? null;
		$this->restrictionStore->setCreateProtection( $title, $level, $expiry, $reason, $user );

		$log = new ManualLogEntry( 'protect', $level === '' ? 'unprotect' : ( $previous ? 'modify' : 'protect' ) );
		$log->setTarget( $title );
		$log->setPerformer( $user );
		$log->setComment( $reason );
		if ( $level !== '' ) {
			$log->setParameters( [
				'4::description' => "[produnto-update=$level] ($expiry)",
				'5:bool:cascade' => false,
				'details' => [ [ 'type' => 'produnto-update', 'level' => $level, 'expiry' => $expiry ] ],
			] );
		}
		$logId = $log->insert( $dbw );
		$dbw->endAtomic( __METHOD__ );
		$log->publish( $logId );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Rest;

use MediaWiki\Extension\Produnto\Store\ProduntoStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\Handler;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\TimestampFormat;

class RecentDeploymentsHandler extends Handler {
	private int|null|false $activeId = false;

	public function __construct(
		private ProduntoStore $store,
		private PermissionManager $permissionManager,
		private IConnectionProvider $dbProvider
	) {
	}

	/** @inheritDoc */
	protected function getETag() {
		return '"' . ( $this->getActiveId() ?? 'null' ) . '"';
	}

	/** @inheritDoc */
	public function execute() {
		$deployments = $this->store->getRecentDeployments();

		$revIdsByWiki = [];
		$infosByWiki = [];
		foreach ( $deployments as $deployment ) {
			$id = $deployment->getId();
			$wiki = $deployment->getControlWikiId();
			$rev = $deployment->getControlRevisionId();

			$packageVersions = [];
			$info = [ 'id' => $id, 'controlWiki' => $wiki, 'revision' => $rev ];
			if ( $deployment->getId() === $this->getActiveId() ) {
				$info['active'] = true;
			}
			foreach ( $deployment->getPackages() as $package ) {
				$name = $package->getName();
				$version = $package->getVersion();
				$packageVersions[$name] = $version;
			}
			$info['packages'] = $packageVersions;
			$revIdsByWiki[$wiki][] = $rev;
			$infosByWiki[$wiki][$rev] = $info;
		}

		foreach ( $revIdsByWiki as $wiki => $revIds ) {
			$db = $this->dbProvider->getReplicaDatabase( $wiki );
			$res = $db->newSelectQueryBuilder()
				->select( [ 'rev_id', 'actor_user', 'actor_name', 'rev_timestamp', 'comment_text' ] )
				->from( 'revision' )
				->join( 'actor', null, 'rev_actor=actor_id' )
				->join( 'comment', null, 'comment_id=rev_comment_id' )
				->where( [ 'rev_id' => $revIds ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				$infosByWiki[$wiki][$row->rev_id] += [
					'userText' => $row->actor_name,
					'timestamp' => wfTimestamp( TimestampFormat::ISO_8601, $row->rev_timestamp ),
					'summary' => $row->comment_text
				];
			}
		}

		$response = $this->getResponseFactory()->createJson( [
			'deployments' => array_merge( ...array_values( $infosByWiki ) )
		] );
		if ( $this->permissionManager->isEveryoneAllowed( 'read' ) ) {
			$response->setHeader( 'Cache-Control', 'public' );
		}
		return $response;
	}

	private function getActiveId(): ?int {
		if ( $this->activeId === false ) {
			$this->activeId = $this->store->getActiveDeployment()?->getId();
		}
		return $this->activeId;
	}
}

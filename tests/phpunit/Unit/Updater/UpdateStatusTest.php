<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Updater;

use MediaWiki\Extension\Produnto\Store\DeploymentAccess;
use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Extension\Produnto\Updater\UpdateStatus;
use MediaWiki\Revision\RevisionRecord;
use stdClass;

/**
 * @covers \MediaWiki\Extension\Produnto\Updater\UpdateStatus
 */
class UpdateStatusTest extends \MediaWikiUnitTestCase {
	public function testGetPackages() {
		$status = new UpdateStatus;
		$package = new PackageAccess(
			new SimpleFileAccess(),
			1, '', '', '', '', [], 0, null
		);
		$status->addPackage( $package );
		$this->assertSame( [ $package ], $status->getPackages() );
	}

	public function testGetModules() {
		$status = new UpdateStatus;
		$this->assertNull( $status->getModules() );
		$status->setModules( [ 'foo' => [ 1, 'foo.lua' ] ] );
		$this->assertSame( [ 'foo' => [ 1, 'foo.lua' ] ], $status->getModules() );
	}

	public function testGetDeployment() {
		$status = new UpdateStatus;
		$this->assertFalse( $status->isDeployed() );

		$deployment = $this->createNoOpMock( DeploymentAccess::class );
		$status->setDeployment( $deployment );

		$this->assertSame( $deployment, $status->getDeployment() );
		$this->assertTrue( $status->isDeployed() );
	}

	public function testGetData() {
		$status = new UpdateStatus;
		$data = new stdClass;
		$status->setData( $data );
		$this->assertSame( $data, $status->getData() );
	}

	public function testGetRevision() {
		$status = new UpdateStatus;
		$revision = $this->createNoOpMock( RevisionRecord::class );
		$status->setRevision( $revision );
		$this->assertSame( $revision, $status->getRevision() );
	}
}

<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Updater;

use MediaWiki\Extension\Produnto\Store\PackageAccess;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use MediaWiki\Extension\Produnto\Updater\ValidationStatus;

/**
 * @covers \MediaWiki\Extension\Produnto\Updater\ValidationStatus
 */
class ValidationStatusTest extends \MediaWikiUnitTestCase {
	public function testGetPackages() {
		$status = new ValidationStatus;
		$package = new PackageAccess(
			new SimpleFileAccess(),
			1, '', '', '', '', [], 0, null
		);
		$status->addPackage( $package );
		$this->assertSame( [ $package ], $status->getPackages() );
	}

	public function testGetModules() {
		$status = new ValidationStatus;
		$this->assertNull( $status->getModules() );
		$status->setModules( [ 'foo' => [ 1, 'foo.lua' ] ] );
		$this->assertSame( [ 'foo' => [ 1, 'foo.lua' ] ], $status->getModules() );
	}
}

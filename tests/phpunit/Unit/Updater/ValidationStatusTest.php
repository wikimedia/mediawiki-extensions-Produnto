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
			1, '', '', '', [], 0, null
		);
		$status->addPackage( $package );
		$this->assertSame( [ $package ], $status->getPackages() );
	}

	public function testGetExtensionData() {
		$status = new ValidationStatus;
		$status->setExtensionData( 'test', 'foo' );
		$this->assertSame( 'foo', $status->getExtensionData( 'test' ) );
		$this->assertNull( $status->getExtensionData( 'nonexistent' ) );
	}
}

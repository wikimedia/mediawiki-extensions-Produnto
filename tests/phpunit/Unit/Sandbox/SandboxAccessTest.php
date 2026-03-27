<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Sandbox;

use MediaWiki\Extension\Produnto\Sandbox\SandboxAccess;
use MediaWiki\Extension\Produnto\Sandbox\SandboxFileCollection;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;

/**
 * @covers \MediaWiki\Extension\Produnto\Sandbox\SandboxAccess
 */
class SandboxAccessTest extends \MediaWikiUnitTestCase {

	public function testHasPackageExists() {
		$this->assertTrue( $this->getSandboxAccess()->hasPackage( 'package1' ) );
	}

	public function testHasPackageMissing() {
		$this->assertFalse( $this->getSandboxAccess()->hasPackage( 'nonexistent' ) );
	}

	public function testGetPackageExists() {
		$result = $this->getSandboxAccess()->getPackage( 'package1' );
		$this->assertInstanceOf( SandboxFileCollection::class, $result );
		$this->assertSame( 'text1', $result->getFileContents( 'file1' ) );
		$this->assertSame( 'text2', $result->getFileContents( 'file2' ) );
	}

	public function testGetPackageMissing() {
		$result = $this->getSandboxAccess()->getPackage( 'nonexistent' );
		$this->assertNull( $result );
	}

	public function testGetPackageNames() {
		$this->assertSame(
			[ 'package1' ],
			$this->getSandboxAccess()->getPackageNames()
		);
	}

	public function testGetModuleInfoExists() {
		$info = $this->getSandboxAccess()->getModuleInfo( 'module1' );
		$this->assertSame( 'package1', $info->packageName );
		$this->assertSame( 'file1', $info->path );
		$this->assertSame( 'text1', $info->contents );
	}

	private function getSandboxAccess(): SandboxAccess {
		$text1 = 'text1';
		$text2 = 'text2';
		$hash1 = hash( 'sha256', $text1 );
		$hash2 = hash( 'sha256', $text2 );

		$fileAccess = new SimpleFileAccess( [], [ $hash1 => $text1 ] );

		return new SandboxAccess(
			$fileAccess,
			[
				SandboxStore::HASHES_BY_PACKAGE_PATH => [
					'package1' => [
						'file1' => $hash1,
						'file2' => $hash2
					]
				],
				SandboxStore::TEXTS => [
					$hash2 => $text2
				],
				SandboxStore::MODULES => [
					'module1' => [ 'package1', 'file1' ]
				],
			]
		);
	}
}

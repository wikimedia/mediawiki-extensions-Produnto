<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Sandbox;

use MediaWiki\Extension\Produnto\Sandbox\SandboxBuilder;
use MediaWiki\Extension\Produnto\Sandbox\SandboxStore;
use MediaWiki\Extension\Produnto\Store\SimpleFileAccess;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @covers \MediaWiki\Extension\Produnto\Sandbox\SandboxBuilder
 */
class SandboxBuilderTest extends \MediaWikiIntegrationTestCase {
	public function testBasicCommit() {
		$text1 = 'text1';
		$text2 = 'text2';
		$hash1 = hash( 'sha256', $text1 );
		$hash2 = hash( 'sha256', $text2 );

		$fileAccess = new SimpleFileAccess( [], [ $hash1 => $text1 ] );

		$stash = new HashBagOStuff();
		$builder = new SandboxBuilder( $fileAccess, $stash, 1, 'sandbox1', [] );

		$res = $builder
			->addFileReference( 'package1', 'file1', $hash1 )
			->addFile( 'package1', 'file2', $hash2, $text2 )
			->modules( [
				'module1' => [ 'package1', 'file1' ]
			] )
			->commit();

		$this->assertTrue( $res );

		$this->assertFalse( $builder->hasHash( $hash1 ) );
		$this->assertTrue( $builder->hasHash( $hash2 ) );
		$this->assertSame( $text1,
			$builder->access()->getPackage( 'package1' )->getFileContents( 'file1' ) );
	}

	public function testOversize() {
		$text = str_repeat( 'x', SandboxStore::MAX_SANDBOX_SIZE * 2 );
		$hash = hash( 'sha256', $text );
		$fileAccess = new SimpleFileAccess();
		$builder = new SandboxBuilder( $fileAccess, new HashBagOStuff(), 1, 'sandbox1', [] );
		$builder->addFile( 'package1', 'file1', $hash, $text );
		$this->assertFalse( $builder->commit() );
	}
}

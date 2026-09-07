<?php

namespace MediaWiki\Extension\Produnto\Tests\Unit\Version;

use MediaWiki\Extension\Produnto\Version\ConstraintParser;
use MediaWiki\Extension\Produnto\Version\ConstraintParserError;
use MediaWiki\Extension\Produnto\Version\VersionParser;

/**
 * @covers \MediaWiki\Extension\Produnto\Version\ConstraintParser
 * @covers \MediaWiki\Extension\Produnto\Version\AndConstraint
 * @covers \MediaWiki\Extension\Produnto\Version\AnyConstraint
 * @covers \MediaWiki\Extension\Produnto\Version\CompareConstraint
 */
class ConstraintParserTest extends \MediaWikiUnitTestCase {
	public static function provideIsSatisfiedBy() {
		return [
			'true ==' => [ '1.0.0', '==1.0.0', true ],
			'false ==' => [ '1.0.0', '==1.0.1', false ],
			'true ~=' => [ '1.0.0', '~=1.0.1', true ],
			'false ~=' => [ '1.0.0', '~=1.0.0', false ],
			'true >' => [ '1.0.1', '>1.0.0', true ],
			'false >' => [ '1.0.1', '>1.0.1', false ],
			'true <' => [ '1.0.1', '<1.0.2', true ],
			'false <' => [ '1.0.1', '<1.0.0', false ],
			'true >=' => [ '1.0.1', '>=1.0.0', true ],
			'true >= 2' => [ '1.0.1', '>=1.0.1', true ],
			'false >=' => [ '1.0.1', '>=2.0.0', false ],
			'true <=' => [ '1.0.0', '<=1.0.1', true ],
			'false <=' => [ '2.0.0', '<=1.0.1', false ],
			'true ~>' => [ '1.0.0', '~>1.0', true ],
			'false ~>' => [ '1.0.0', '~>1.1', false ],
			'true empty operator' => [ '1.0.0', '1.0.0', true ],
			'false empty operator' => [ '1.0.0', '1.1.0', false ],
			'true =' => [ '1.0.0', '=1.0.0', true ],
			'false =' => [ '1.0.0', '=1.1.0', false ],
			'true !=' => [ '1.0.0', '!=1.0.1', true ],
			'false !=' => [ '1.0.0', '!=1.0.0', false ],
			'true *' => [ '1.0.0', '*', true ],
			'true and' => [ '1.1.0', '>=1.0.0, <= 1.2.0', true ],
			'false and' => [ '1.1.0', '>=1.0.0, < 1.1.0', false ],
			'no-upgrade' => [ '1.0.0', '@1.0.0', true ],
		];
	}

	/** @dataProvider provideIsSatisfiedBy */
	public function testIsSatisfiedBy( string $version, string $constraint, bool $expected ) {
		$vp = new VersionParser();
		$cp = new ConstraintParser( $vp );
		$constraint = $cp->parse( $constraint );
		$result = $constraint->isSatisfiedBy( $vp->parse( $version ) );
		$this->assertSame( $expected, $result );
	}

	public static function provideConstraintParserError() {
		return [
			'empty string' => [ '' ],
			'regex mismatch' => [ '$' ],
			'invalid operator' => [ '<>1.0.0' ],
		];
	}

	/** @dataProvider provideConstraintParserError */
	public function testConstraintParserError( string $constraint ) {
		$vp = new VersionParser();
		$cp = new ConstraintParser( $vp );
		$this->expectException( ConstraintParserError::class );
		$cp->parse( $constraint );
	}
}

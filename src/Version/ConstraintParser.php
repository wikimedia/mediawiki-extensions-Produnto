<?php

namespace MediaWiki\Extension\Produnto\Version;

/**
 * Parse constraint strings, following LuaRocks conventions, except that we also
 * accept a star "*" to mean any version.
 */
class ConstraintParser {
	/** @var Constraint[] */
	private array $cache = [];

	public function __construct(
		private VersionParser $versionParser
	) {
	}

	private const OPERATORS = [
		// Canonical operators
		'==' => '==',
		'~=' => '~=',
		'>' => '>',
		'<' => '<',
		'>=' => '>=',
		'<=' => '<=',
		'~>' => '~>',
		// Aliases
		'' => '==',
		'=' => '==',
		'!=' => '~='
	];

	/**
	 * Parse a constraint, with caching
	 *
	 * @throws ConstraintParserError
	 */
	public function parse( string $input ): Constraint {
		// phpcs:ignore MediaWiki.Usage.AssignmentInReturn.AssignmentInReturn
		return $this->cache[$input] ??= $this->parseConstraints( $input );
	}

	/**
	 * Similar to parse_constraints() in LuaRocks queries.tl
	 *
	 * @throws ConstraintParserError
	 */
	private function parseConstraints( string $input ): Constraint {
		$constraints = [];
		foreach ( explode( ',', $input ) as $item ) {
			$constraints[] = $this->parseConstraint( trim( $item ) );
		}
		if ( count( $constraints ) === 1 ) {
			return $constraints[0];
		} else {
			return new AndConstraint( $constraints );
		}
	}

	private function parseConstraint( string $input ): Constraint {
		// Accept composer-like asterisk
		if ( $input === '*' ) {
			return new AnyConstraint();
		}

		if ( !preg_match( '/^(@?)([<>=~!]*)\s*([a-zA-Z0-9._-]+)$/', $input, $m ) ) {
			throw new ConstraintParserError( 'invalid constraint' );
		}
		[ , $noUpgrade, $op, $versionStr ] = $m;
		if ( !isset( self::OPERATORS[$op] ) ) {
			throw new ConstraintParserError( 'invalid operator' );
		}
		return new CompareConstraint(
			self::OPERATORS[$op],
			$this->versionParser->parse( $versionStr ),
			$noUpgrade !== ''
		);
	}
}

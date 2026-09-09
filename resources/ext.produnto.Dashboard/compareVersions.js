const deltas = require( './compareVersions.config.json' ).deltas;

const versionCache = {};

/**
 * Compare version strings and return -1 if v1<v2, 1 if v1>v2, or 0 if v1=v2
 *
 * @param {string} v1
 * @param {string} v2
 * @return {number}
 */
function compareVersions( v1, v2 ) {
	const parts1 = parseVersion( v1 );
	const parts2 = parseVersion( v2 );
	const n = Math.max( parts1.length, parts2.length );
	for ( let i = 0; i < n; i++ ) {
		const part1 = parts1[i] || 0;
		const part2 = parts2[i] || 0;
		if ( part1 < part2 ) {
			return -1;
		} else if ( part1 > part2 ) {
			return 1;
		}
	}
	return 0;
}

/**
 * Parse a version string like how VersionParser.php and LuaRocks do it.
 *
 * @param {string} input
 * @return {number[]}
 */
function parseVersion( input ) {
	input = String( input );
	if ( input in versionCache ) {
		return versionCache[ input ];
	}

	const re = /(?:([0-9]+)|([a-zA-Z]+))[._-]*/;
	const parts = [];
	let delta = 0;
	let str = input;
	while ( str !== '' ) {
		const m = re.exec( str );
		if ( m === null ) {
			// VersionParser would throw an exception here, but we're only
			// trying to sort lists, not validate a deployment, so we'll just
			// treat it like a letter and terminate the loop.
			parts.push( str.charCodeAt( 0 ) );
			break;
		}
		if ( m[ 1 ] !== undefined ) {
			parts.push( parseInt( m[ 1 ] ) + delta );
			delta = 0;
		} else if ( deltas[ m[ 2 ] ] ) {
			delta = deltas[ m[ 2 ] ];
		} else {
			parts.push( m[ 2 ].charCodeAt( 0 ) );
			delta = 0;
		}
		str = str.slice( m[ 0 ].length );
	}
	if ( delta ) {
		parts.push( delta );
	}

	versionCache[ input ] = parts;
	return parts;
}

function compareVersionsBackwards( v1, v2 ) {
	return -compareVersions( v1, v2 );
}

module.exports = { compareVersions, compareVersionsBackwards };

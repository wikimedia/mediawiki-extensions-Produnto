/**
 * Compare version strings and return -1 if v1<v2, 1 if v1>v2, or 0 if v1=v2
 *
 * @param {string} v1
 * @param {string} v2
 * @return {number}
 */
function compareVersions( v1, v2 ) {
	const parts1 = v1.split( '.' );
	const parts2 = v2.split( '.' );
	for ( let i = 0; i < parts1.length; i++ ) {
		const part1 = parts1[i] || '0';
		const part2 = parts2[i] || '0';
		if ( parseInt( part1 ) < parseInt( part2 ) ) {
			return -1;
		} else if ( parseInt( part1 ) > parseInt( part2 ) ) {
			return 1;
		} else if ( part1 < part2 ) {
			return -1;
		} else if ( part1 > part2 ) {
			return 1;
		}
	}
	return 0;
}

function compareVersionsBackwards( v1, v2 ) {
	return -compareVersions( v1, v2 );
}

module.exports = { compareVersions, compareVersionsBackwards };

QUnit.module( 'ext.produnto.Dashboard/compareVersions.js' );

const { compareVersions } = require( '../../../resources/ext.produnto.Dashboard/compareVersions.js' );

const cases = {
	'single less': [ '1', '2', -1 ],
	'single equal': [ '1', '1', 0 ],
	'single greater': [ '2', '1', 1 ],
	'single with revision less': [ '1-1', '1-2', -1 ],
	'single with revision greater': [ '1-2', '1-1', 1 ],
	'single with revision irrelevant': [ '2-1', '1-2', 1 ],
	'dev-scm': [ '1.0.0-dev1', '1.0.0-scm1', 1 ],
	'scm-cvs': [ '1.0.0-scm1', '1.0.0-cvs1', 1 ],
	'cvs-rc': [ '1.0.0-cvs1', '1.0.0-rc1', 1 ],
	'rc-pre': [ '1.0.0-rc1', '1.0.0-pre1', 1 ],
	'pre-beta': [ '1.0.0-pre1', '1.0.0-beta1', 1 ],
	'beta-alpha': [ '1.0.0-beta1', '1.0.0-alpha1', 1 ],
	'foo-bar': [ '1.0.foo', '1.0.bar', 1 ],
	'double 0-1': [ '1.0', '1.1', -1 ],
	'double major precedence': [ '2.0', '1.1', 1 ],
	'triple 0-1': [ '1.0.0', '1.0.1', -1 ],
	'triple minor precedence': [ '1.1.0', '1.0.1', 1 ],
	'triple major precedence': [ '2.0.0', '1.0.1', 1 ],
	'two hyphens': [ '1.0.0-alpha1-1', '1.0.0-alpha2-1', -1 ],
	'WMF release train': [ '1.47.0-wmf.17', '1.47.0-wmf.18', -1 ],
	'WMF release major': [ '1.47.0-wmf.17', '1.48.0-wmf.1', -1 ],
};

QUnit.test.each( 'compareVersions', cases, ( assert, [ v1, v2, expected ] ) => {
	assert.strictEqual( compareVersions( v1, v2 ), expected );
} );

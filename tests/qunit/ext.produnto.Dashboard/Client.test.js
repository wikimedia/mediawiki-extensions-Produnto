const Client = require( '../../../resources/ext.produnto.Dashboard/Client.js' );

QUnit.module( 'ext.produnto.Dashboard/Client.js', {
	beforeEach() {
		this.client = new Client();
		this.client.scriptPath = 'https://example.test';
		this.client.langCodes = [ 'fr', 'en' ];
		this.client.actionApi = { getToken: async () => 'test-token' };
	}
} );

QUnit.test.each( 'REST error messages', {
	localized: [
		{ messageTranslations: { en: 'Permission denied', fr: 'Permission refusée' } },
		'Permission refusée'
	],
	'localized preferred over plain': [
		{ messageTranslations: { fr: 'Permission refusée' }, message: 'Permission denied' },
		'Permission refusée'
	],
	plain: [ { message: 'Permission denied' }, 'Permission denied' ],
	'empty translations': [ { messageTranslations: {}, message: 'Permission denied' }, 'Permission denied' ],
	'no message': [ {}, '403 Forbidden' ],
	'non-JSON response': [ null, '403 Forbidden' ],
}, async function ( assert, [ body, expected ] ) {
	this.sandbox.stub( window, 'fetch' ).callsFake( async () => ( {
		ok: false,
		status: 403,
		statusText: 'Forbidden',
		json: async () => {
			if ( body === null ) {
				throw new SyntaxError( 'Invalid JSON' );
			}
			return body;
		}
	} ) );
	await assert.rejects( this.client.fetchJsonRest( 'deployment/patch' ), new Error( expected ) );
} );

QUnit.test( 'REST translations use BCP 47 tags', async function ( assert ) {
	this.client.langCodes = [ 'zh-hans', 'en' ];
	this.sandbox.stub( window, 'fetch' ).callsFake( async () => ( {
		ok: false,
		json: async () => ( { messageTranslations: { en: 'Permission denied', 'zh-Hans': '权限不足' } } )
	} ) );
	await assert.rejects( this.client.fetchJsonRest( 'deployment/patch' ), new Error( '权限不足' ) );
} );

QUnit.test( 'every deployment retry reports its permission failure', async function ( assert ) {
	let requests = 0;
	this.sandbox.stub( window, 'fetch' ).callsFake( async () => {
		requests++;
		return {
			ok: false,
			status: 403,
			statusText: 'Forbidden',
			json: async () => ( {
				messageTranslations: { en: 'You do not have permission to deploy packages.' }
			} )
		};
	} );
	for ( let attempt = 0; attempt < 2; attempt++ ) {
		await assert.rejects(
			this.client.patch( {}, { example: '1' }, '', false ),
			new Error( 'You do not have permission to deploy packages.' )
		);
	}
	assert.strictEqual( requests, 2, 'Both clicks send a request' );
} );

QUnit.test.each( 'retry after failure', [ 'token', 'network', 'JSON' ], async function ( assert, failure ) {
	let fail = true;
	this.client.actionApi.getToken = async () => {
		if ( fail && failure === 'token' ) {
			throw new Error( 'token failure' );
		}
		return 'test-token';
	};
	this.sandbox.stub( window, 'fetch' ).callsFake( async () => {
		if ( fail && failure === 'network' ) {
			throw new Error( 'network failure' );
		}
		return {
			ok: true,
			json: async () => {
				if ( fail && failure === 'JSON' ) {
					throw new Error( 'JSON failure' );
				}
				return { ok: true, warnings: [], errors: [], deployment: { id: 1 } };
			}
		};
	} );
	await assert.rejects( this.client.patch( {}, { example: '1' }, '', false ), new Error( `${ failure } failure` ) );
	fail = false;
	const result = await this.client.patch( {}, { example: '1' }, '', false );
	assert.true( result.ok, 'A subsequent deployment succeeds' );
} );

QUnit.test( 'duplicate deployment is ignored while fetching the token', async function ( assert ) {
	let rejectToken;
	this.client.actionApi.getToken = () => new Promise( ( resolve, reject ) => {
		rejectToken = reject;
	} );
	const first = this.client.patch( {}, { example: '1' }, '', false );
	const second = await this.client.patch( {}, { example: '1' }, '', false );
	assert.strictEqual( second, null );
	rejectToken( new Error( 'token failure' ) );
	await assert.rejects( first, new Error( 'token failure' ) );
	assert.false( this.client.deployPending, 'Failure releases the pending deployment' );
} );

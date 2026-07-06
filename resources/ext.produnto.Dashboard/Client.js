const { compareVersions } = require( './compareVersions.js' );

const META_PROPS = [
	'homepageUrl',
	'collabUrl',
	'docUrl',
	'issueUrl',
	'authors',
	'license',
];
const L10N_PROPS = [
	'localName',
	'description',
];

/**
 * @module Client
 */
/**
 * Class to perform dashboard-related API requests and to process the responses
 */
class Client {
	constructor() {
		this.scriptPath = mw.config.get( 'wgScriptPath' );
		this.langCodes = mw.language.getFallbackLanguageChain();
		this.packages = new Map();
		this.deployments = [];
		this.actionApi = new mw.Api();
	}

	/**
	 * @typedef {Object} Package
	 * @property {string} name
	 * @property {string} version
	 * @property {number} id
	 * @property {Object.<string,string>} [localName]
	 * @property {Object.<string,string>} [description]
	 * @property {string} [collabUrl]
	 * @property {string} [docUrl]
	 * @property {string} [issueUrl]
	 * @property {string[]} [authors]
	 * @property {string} [license]
	 * @property {Object.<string,string>} [requires]
	 * @property {string} [state]
	 * @property {string[]} [errors]
	 */
	/**
	 * @typedef {Object} Deployment
	 * @property {number} id
	 * @property {string} wiki
	 * @property {number} revision
	 * @property {boolean} [active]
	 * @property {Object.<string,string>[]} packages
	 */

	/**
	 * Perform initial requests in parallel
	 *
	 * @return {Promise<{packages:Package[], deployments:Deployment[]}>}
	 */
	async start() {
		await Promise.all( [
			this.fetchPackages(),
			this.fetchDeployments()
		] );
		return {
			packages: this.getPackages(),
			deployments: this.getDeployments()
		};
	}

	/**
	 * Get metadata of all fetched packages
	 *
	 * @return {Package[]}
	 */
	getPackages() {
		const packages = Array.from( this.packages.values() );
		packages.sort( ( a, b ) => {
			if ( a.name < b.name ) {
				return -1;
			} else if ( a.name > b.name ) {
				return 1;
			} else {
				return 0;
			}
		} );
		return packages;
	}

	/**
	 * Get the metadata of recent deployments
	 *
	 * @return {Deployment[]}
	 */
	getDeployments() {
		return this.deployments;
	}

	/**
	 * Validate a set of packages for deployment
	 *
	 * @param {Object.<string,string>} packages Package versions by name, absent for undeployed
	 * @return {Promise<Object.<string,string[]>>|null}>} Errors by package name,
	 *   or null if the request was aborted.
	 */
	async validate( packages ) {
		if ( this.abortValidate ) {
			this.abortValidate.abort();
		}
		const opts = {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json'
			}
		};
		if ( window.AbortController ) {
			this.abortValidate = new AbortController();
			opts.signal = this.abortValidate.signal;
		}
		opts.body = JSON.stringify( packages );
		try {
			const data = await this.fetchJsonRest(
				'deployment/validate',
				opts
			);
			this.abortValidate = null;
			const packageErrors = {};
			for ( const error of data.warnings.concat( data.errors ) ) {
				if ( error.package ) {
					if ( !packageErrors[error.package] ) {
						packageErrors[error.package] = [];
					}
					packageErrors[error.package].push( this.localise( error.translations ) );
				}
			}
			return packageErrors;
		} catch ( e ) {
			if ( !window.AbortError || !( e instanceof window.AbortError ) ) {
				throw e;
			}
			return null;
		}
	}

	/**
	 * @typedef PatchResult
	 * @property {boolean} ok
	 * @property {string[]} warnings
	 * @property {string[]} errors
	 * @property {Deployment} deployment
	 */
	/**
	 * Modify package versions
	 *
	 * @param {Object.<string,string>} oldPackages Old package versions by name
	 * @param {Object.<string,string>} newPackages New package versions by name
	 * @param {string} summary
	 * @param {boolean} ignoreWarnings
	 * @return {PatchResult|null}
	 */
	async patch( oldPackages, newPackages, summary, ignoreWarnings ) {
		if ( this.deployPending ) {
			return null;
		}
		const names = Object.keys( Object.assign( {}, oldPackages, newPackages ) );
		const patchVersions = {};
		for ( const name of names ) {
			const version = newPackages[name];
			if ( version === undefined || version === '' ) {
				patchVersions[name] = null;
			} else if ( version !== oldPackages[name] ) {
				patchVersions[name] = version;
			}
		}

		const formData = new FormData();
		formData.set( 'packages', JSON.stringify( patchVersions ) );
		formData.set( 'summary', summary );
		formData.set( 'ignoreWarnings', ignoreWarnings ? '1' : '' );
		formData.set( 'token', await this.actionApi.getToken( 'csrf' ) );
		const opts = {
			method: 'POST',
			body: formData
		};

		this.deployPending = true;
		const result = await this.fetchJsonRest(
			'deployment/patch',
			opts
		);
		this.deployPending = false;
		return {
			ok: result.ok,
			warnings: this.localiseStatusArray( result.warnings ),
			errors: this.localiseStatusArray( result.errors ),
			deployment: result.deployment,
		};
	}

	/**
	 * @private
	 * @return {Promise<void>}
	 */
	async fetchPackages() {
		const index = await this.fetchJsonRest(
			'packages/all/',
			// Omit credentials so that the cache can be shared -- workaround for T264631
			{ credentials: 'omit' }
		);
		const partitionPromises = [];
		for ( const partition of index.partitions ) {
			partitionPromises.push( this.fetchPartition( partition.href ) );
		}
		return Promise.all( partitionPromises );
	}

	/**
	 * @private
	 * @param {string} href
	 * @return {Promise<void>}
	 */
	async fetchPartition( href ) {
		const response = await this.fetchJsonRest(
			`packages/all/${ href }`, { credentials: 'omit' } );
		for ( const inputPackage of response.packages ) {
			const name = inputPackage.name;
			const version = inputPackage.version;
			let outputPackage;
			if ( !this.packages.has( name ) ) {
				outputPackage = {
					name: name,
					versions: [],
					requirements: {},
					errors: {}
				};
				this.packages.set( name, outputPackage );
			} else {
				outputPackage = this.packages.get( name );
			}

			// Add per-version properties
			outputPackage.versions.push( version );
			if ( inputPackage.requirements !== undefined ) {
				outputPackage.requirements[version] = inputPackage.requirements;
			}
			if ( inputPackage.errors ) {
				const translations = [];
				for ( const error of inputPackage.errors ) {
					translations.push( this.localise( error.translations ) );
				}
				outputPackage.errors[version] = translations;
			}

			// Use metadata from the new version if it is newer
			if ( this.hasMetadata( inputPackage ) &&
				( outputPackage.metaVersion === undefined ||
					compareVersions( inputPackage.version, outputPackage.metaVersion ) > 0
				)
			) {
				outputPackage.metaVersion = inputPackage.version;
				for ( const propName of META_PROPS ) {
					if ( propName in inputPackage ) {
						outputPackage[propName] = inputPackage[propName];
					} else {
						delete outputPackage[propName];
					}
				}
				for ( const propName of L10N_PROPS ) {
					if ( propName in inputPackage ) {
						outputPackage[propName] = this.localise( inputPackage[propName] );
					} else {
						delete outputPackage[propName];
					}
				}
			}
		}
	}

	/**
	 * @private
	 * @return {Promise<void>}
	 */
	async fetchDeployments() {
		const response = await this.fetchJsonRest( 'deployments/recent', { credentials: 'omit' } );
		if ( response.deployments ) {
			this.deployments = response.deployments;
		} else {
			throw new Error( 'Error fetching deployments' );
		}
	}

	/**
	 * @external RequestInit
	 */

	/**
	 * @private
	 * @param {string} path
	 * @param {RequestInit} [options]
	 * @return {Promise<any>}
	 */
	async fetchJsonRest( path, options ) {
		options = options || {};
		const url = this.scriptPath + '/rest.php/produnto/v1/' + path;
		const req = new Request( url, options );
		const response = await fetch( req );
		if ( !response.ok ) {
			let data;
			try {
				data = await response.json();
			} catch ( e ) {
			}
			// Use the message from the JSON response if MediaWiki provided it
			if ( data && data.message ) {
				throw new Error( data.message );
			}
			// Otherwise just show generic status text
			throw new Error( `${ response.status } ${ response.statusText }` );
		}
		return await response.json();
	}

	/**
	 * Check if the package has any package metadata. The metadata shown in the
	 * dashboard is from the fetched version with the highest version number that
	 * has metadata.
	 *
	 * @param {Package} pkg
	 * @return {boolean}
	 */
	hasMetadata( pkg ) {
		for ( const prop of META_PROPS ) {
			if ( prop in pkg ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Given a map of language codes to message texts, return the one that is
	 * best for the current user.
	 *
	 * @private
	 * @param {Object.<string,string>} messages
	 * @return {string|undefined}
	 */
	localise( messages ) {
		for ( const langCode of this.langCodes ) {
			if ( langCode in messages ) {
				return messages[langCode];
			}
		}
		// As a final fallback, use the first defined language
		// eslint-disable-next-line no-unreachable-loop
		for ( const langCode in messages ) {
			return messages[langCode];
		}
		return undefined;
	}

	localiseStatusArray( messages ) {
		return Array.from( messages )
			.map( ( m ) => this.localise( m.translations ) );
	}
}

module.exports = Client;

<template>
	<div class="ext-produnto-dashboard">
		<deployment-box
			v-model:summary="summary"
			:deployments="deployments"
			:selected="selectedDeploymentId"
			:is-changed="isChanged"
			@update:selected="updateDeploymentId"
			@revert="revert"
			@deploy="deploy"
		></deployment-box>
		<message-list
			:messages="deploymentErrors"
			type="error"
		></message-list>
		<message-list
			v-model:ignore="ignoreWarnings"
			:messages="deploymentWarnings"
			:show-ignore="true"
			type="warning"
		></message-list>
		<cdx-progress-bar
			v-if="showDeployProgress"
			:aria-label="msg( 'produnto-dashboard-progress' )"
		></cdx-progress-bar>
		<cdx-message
			v-if="showSuccess"
			type="success"
			:allow-user-dismiss="true"
		>{{ msg( 'produnto-dashboard-deploy-success' ) }}</cdx-message>

		<cdx-label
			:input-id="packagesId"
		>{{ msg( 'produnto-dashboard-packages' ) }}</cdx-label>
		<div :id="packagesId" class="ext-produnto-dashboard--packages">
			<div class="ext-produnto-dashboard--search">
				<cdx-search-input
					v-model="searchTerm"
				></cdx-search-input>
			</div>
			<package-list
				:search="searchTerm"
				:packages="packages"
				:active-versions="activeVersions"
				:selected-versions="selectedVersions"
				:validation-errors="validationErrors"
				@update-selected="updateVersion"
			></package-list>
		</div>
	</div>
</template>

<script>

const { computed, defineComponent, reactive, ref, useId } = require( 'vue' );
const PackageList = require( './PackageList.vue' );
const DeploymentBox = require( './DeploymentBox.vue' );
const MessageList = require( './MessageList.vue' );
const { CdxLabel, CdxMessage, CdxProgressBar, CdxSearchInput } = require( './codex.js' );
const { formatTimeAndDate } = require( 'mediawiki.DateFormatter' );
const { Client } = require( './Client.js' );

/**
 * The root component for the dashboard special page
 */
module.exports = defineComponent( {
	name: 'ProduntoDashboardApp',
	components: {
		CdxLabel,
		CdxMessage,
		CdxProgressBar,
		CdxSearchInput,
		DeploymentBox,
		MessageList,
		PackageList,
	},
	props: {
		initialDeployments: { type: Array, required: true },
		initialPackages: { type: Array, required: true },
		client: { type: Client, required: true },
	},

	/**
	 * @typedef {module:Client.Package} Package
	 */
	/**
	 * @typedef {module:Client.Deployment} Deployment
	 */
	/**
	 * @param {Object} props
	 * @param {Package[]} props.initialPackages
	 * @param {Deployment[]} props.initialDeployments
	 * @return {Object}
	 */
	setup( props ) {
		// DATA MODEL
		// The package list shows a view of the package versions from a previous
		// deployment. If the user changes a package version, we clone the set
		// of deployed packages into a new editable deployment and switch to
		// that deployment.
		// We use update events instead of v-model to implement these complex
		// semantics, avoiding warring watchers.

		const activeDeploymentId = ref( 0 );
		const packages = ref( props.initialPackages );
		const deployments = ref( props.initialDeployments );
		const client = props.client;

		const showSuccess = ref( false );
		const deploymentErrors = ref( [] );
		const deploymentWarnings = ref( [] );
		const showDeployProgress = ref( false );
		const ignoreWarnings = ref( false );

		const activeDeployment = computed( () => (
			deployments.value.find( ( elt ) => elt.id === activeDeploymentId.value )
		) );
		const activeVersions = computed( () => (
			activeDeployment.value ? activeDeployment.value.packages : {}
		) );

		for ( const dpt of deployments.value ) {
			if ( dpt.active ) {
				activeDeploymentId.value = dpt.id;
				break;
			}
		}

		// Undeployed version map, used to fill in missing versions, avoiding type errors
		const emptyVersions = {};
		for ( const pkg of packages.value ) {
			emptyVersions[pkg.name] = '';
		}

		// The currently selected deployment ID, or zero for no deployment, or
		// 'new' for the editable new deployment.
		const selectedDeploymentId = ref( 0 );
		const selectedDeployment = computed( () => {
			const id = selectedDeploymentId.value;
			if ( id !== 'new' ) {
				for ( const dpt of deployments.value ) {
					if ( dpt.id === id ) {
						return dpt;
					}
				}
			}
			return null;
		} );

		// Storage for the new deployment
		const newVersions = reactive( {} );

		const isChanged = computed( () => {
			if ( !newVersions.value ) {
				return false;
			}
			for ( const { name } of packages.value ) {
				if ( newVersions.value[name] !== ( activeVersions.value[name] || '' ) ) {
					return true;
				}
			}
			return false;
		} );

		/**
		 * Update event handler for the deployment select
		 *
		 * @param {number|string} newId
		 */
		function updateDeploymentId( newId ) {
			if ( newId === 'new' ) {
				newVersions.value = Object.assign( {}, emptyVersions,
					selectedDeployment.value.packages );
			}
			selectedDeploymentId.value = newId;
			clearMessages();
		}

		// Getter for the selected package versions
		const selectedVersions = computed( () => {
			if ( selectedDeploymentId.value === 'new' ) {
				return newVersions.value;
			} else if ( selectedDeployment.value ) {
				return Object.assign( {}, emptyVersions,
					selectedDeployment.value.packages );
			} else {
				return {};
			}
		} );

		const validationErrors = ref( {} );

		/**
		 * Event handler for changes to the package version select inputs
		 *
		 * @param {string} pkgName
		 * @param {string} version
		 */
		function updateVersion( pkgName, version ) {
			clearMessages();
			if ( selectedDeploymentId.value !== 'new' ) {
				if ( selectedDeployment.value ) {
					newVersions.value = Object.assign( {}, emptyVersions,
						selectedDeployment.value.packages );
				} else {
					newVersions.value = Object.assign( {}, emptyVersions );
				}
				selectedDeploymentId.value = 'new';
			}
			newVersions.value[pkgName] = version;
			client.validate( makeDeployment( newVersions.value ) )
				.then( ( errors ) => {
					if ( errors !== null ) {
						validationErrors.value = errors;
					}
				} )
				.catch( ( error ) => {
					validationErrors.value[pkgName] = [ error.toString() ];
					// eslint-disable-next-line no-console
					console.log( error );
				} );
		}

		function makeDeployment( packageVersions ) {
			const filteredPackages = {};
			for ( const [ name, version ] of Object.entries( packageVersions ) ) {
				if ( version !== '' ) {
					filteredPackages[name] = String( version );
				}
			}
			return filteredPackages;
		}

		// Populate selectedVersions and the deployment refs now that the
		// dependencies have been registered
		updateDeploymentId( activeDeploymentId.value );

		// The package search query
		const searchTerm = ref( '' );

		// The edit summary for a new deployment
		const summary = ref( '' );

		/**
		 * The "revert to this" button clones the current deployment and switches
		 * to the "new" deployment, like changing a package version.
		 */
		function revert() {
			if ( !selectedDeployment.value ) {
				return;
			}
			newVersions.value = Object.assign( {}, emptyVersions,
				selectedDeployment.value.packages );
			summary.value = mw.msg( 'produnto-dashboard-revert-summary',
				formatTimeAndDate( new Date( selectedDeployment.value.timestamp ) ) );
			selectedDeploymentId.value = 'new';
		}

		async function deploy() {
			clearMessages();
			showDeployProgress.value = true;

			try {
				const result = await client.patch(
					makeDeployment( activeVersions.value ),
					makeDeployment( newVersions.value ),
					summary.value,
					ignoreWarnings.value
				);
				showDeployProgress.value = false;
				if ( !result ) {
					// Aborted?
				} else if ( result.ok ) {
					activeDeployment.value.active = false;
					deployments.value.unshift( result.deployment );
					activeDeploymentId.value = result.deployment.id;
					selectedDeploymentId.value = result.deployment.id;
					showSuccess.value = true;
					summary.value = '';
					ignoreWarnings.value = false;
				} else {
					deploymentErrors.value = result.errors;
					deploymentWarnings.value = result.warnings;
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.log( error );
				showDeployProgress.value = false;
				deploymentErrors.value = [ error.toString() ];
				deploymentWarnings.value = [];
			}
		}

		function clearMessages() {
			showSuccess.value = false;
			deploymentWarnings.value = [];
			deploymentErrors.value = [];
		}

		return {
			msg: mw.msg,
			activeVersions,
			selectedVersions,
			isChanged,
			updateVersion,
			selectedDeploymentId,
			updateDeploymentId,
			revert,
			summary,
			deployments,
			packagesId: useId(),
			packages,
			searchTerm,
			validationErrors,
			deploy,
			showSuccess,
			showDeployProgress,
			deploymentErrors,
			deploymentWarnings,
			ignoreWarnings,
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-dashboard {
	&--search {
		margin: @spacing-50 0;
		max-width: @size-1200;
	}
	&--packages {
		border: @border-subtle;
		border-radius: @border-radius-base;
		padding: @spacing-75;
		box-shadow: @box-shadow-medium;
		background: @background-color-interactive-subtle;
	}
}
</style>

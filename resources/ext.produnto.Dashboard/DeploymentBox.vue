<template>
	<div class="ext-produnto-deployment-box">
		<cdx-label
			:input-id="selectId"
		>{{ msg( 'produnto-dashboard-deployment' ) }}</cdx-label>
		<div class="ext-produnto-deployment-box--selector">
			<cdx-select
				:id="selectId"
				v-model:selected="selectedDeploymentId"
				:menu-items="deploymentItems"
			>
			</cdx-select>
			<div v-if="isNewSelected" class="ext-produnto-deployment-box--selector--new">
				<cdx-field>
					<template #label>{{ msg( 'produnto-dashboard-summary' ) }}</template>
					<cdx-text-input v-model="summaryWrapper"></cdx-text-input>
				</cdx-field>
				<div class="ext-produnto-deployment-box--selector--new--submit">
					<cdx-button
						action="progressive"
						weight="primary"
						:disabled="!canSave"
						@click="$emit( 'deploy' )"
					>{{ msg( 'produnto-dashboard-deploy' ) }}</cdx-button>
				</div>
			</div>
			<div v-else class="ext-produnto-deployment-box--selector--existing">
				<div class="ext-produnto-deployment-box--selector--existing--info">
					<span class="ext-produnto-deployment-box--selector--existing--links"
					><span><a :href="selectedDiffUrl">{{ msg( 'diff' ) }}</a></span
					><span><a :href="historyUrl">{{ msg( 'hist' ) }}</a></span
					></span
					><span class="ext-produnto-deployment-box--selector--existing--summary"
					>{{ selectedSummary }}</span>
				</div>
				<cdx-button
					v-if="!isActiveSelected"
					action="destructive"
					@click="$emit( 'revert' )"
				>{{ msg( 'produnto-dashboard-revert-to-this' ) }}</cdx-button>
			</div>
		</div>
	</div>
</template>

<script>
const { computed, defineComponent, toRef, useId } = require( 'vue' );
const { CdxButton, CdxField, CdxLabel, CdxSelect, CdxTextInput, useModelWrapper } =
	require( './codex.js' );
const { formatTimeAndDate } = require( 'mediawiki.DateFormatter' );

/**
 * A box with a deployment selector and information about the selected
 * deployment, and deployment action buttons like actually deploying a
 * deployment.
 */
module.exports = defineComponent( {
	name: 'DeploymentBox',

	components: {
		CdxButton,
		CdxField,
		CdxLabel,
		CdxSelect,
		CdxTextInput,
	},

	props: {
		// The deployment objects from the client
		deployments: { type: Array, required: true },
		// The selected deployment ID
		// eslint-disable-next-line vue/no-unused-properties
		selected: { type: [ Number, String ], required: true },
		// eslint-disable-next-line vue/no-unused-properties
		summary: { type: String, required: true },
		// Whether any selected package version differs from the active package set
		isChanged: Boolean,
	},

	emits: [
		'deploy',
		'revert',
		'update:selected',
		'update:summary',
	],

	setup( props, { emit } ) {
		function getDeploymentById( id ) {
			id = +id;
			for ( const dpy of props.deployments ) {
				if ( dpy.id === id ) {
					return dpy;
				}
			}
			return null;
		}

		const activeDeploymentId = computed( () => {
			for ( const dpy of props.deployments ) {
				if ( dpy.active ) {
					return dpy.id;
				}
			}
			return 0;
		} );

		const selectedDeploymentId = useModelWrapper(
			toRef( props, 'selected' ), emit, 'update:selected' );
		const selectedDeployment = computed(
			() => getDeploymentById( selectedDeploymentId.value ) );
		const selectedSummary = computed(
			() => selectedDeployment.value ? selectedDeployment.value.summary : '' );

		const NS_MEDIAWIKI = 8;
		const packagesJsonTitleConfig = mw.config.get( 'wgProduntoPackagesTitle' );
		const packagesJsonTitle = packagesJsonTitleConfig ?
			new mw.Title( packagesJsonTitleConfig ) :
			mw.Title.makeTitle( NS_MEDIAWIKI, 'Packages.json' );

		const selectedDiffUrl = computed( () => {
			const dpt = selectedDeployment.value;
			if ( !dpt ) {
				return '';
			}
			return packagesJsonTitle.getUrl( {
				oldid: dpt.revision,
				diff: 'prev'
			} );
		} );
		const historyUrl = packagesJsonTitle.getUrl( { action: 'history' } );

		const summaryWrapper = useModelWrapper( toRef( props, 'summary' ), emit, 'update:summary' );

		const isNewSelected = computed( () => selectedDeploymentId.value === 'new' );
		const isActiveSelected = computed(
			() => selectedDeploymentId.value === activeDeploymentId.value );
		const canSave = computed( () => isNewSelected.value && props.isChanged );

		// It would be nice if we could apply custom HTML formatting to menu
		// items, but the example for that in the CdxMenu documentation, using
		// useFloatingMenu(), is too complex to be maintainable.
		const deploymentItems = computed( () => {
			const items = [ { label: mw.msg( 'produnto-dashboard-new-deployment' ), value: 'new' } ];
			for ( const dp of props.deployments ) {
				const dateTime = formatTimeAndDate( new Date( dp.timestamp ) );
				const item = {
					label: `${ dateTime } — ${ dp.userText }`,
					description: dp.summary,
					value: dp.id,
				};
				if ( dp.active ) {
					item.label = mw.msg( 'produnto-dashboard-active', item.label );
				}
				items.push( item );
			}
			return items;
		} );

		return {
			msg: mw.msg,
			canSave,
			historyUrl,
			isActiveSelected,
			isNewSelected,
			summaryWrapper,
			selectedDeploymentId,
			selectedSummary,
			selectedDiffUrl,
			deploymentItems,
			selectId: useId(),
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-deployment-box {
	// Leave enough space so that it usually doesn't vertically expand when it
	// switches to edit mode
	min-height: 150px;

	&--selector {
		flex: auto;
		display: flex;
		flex-direction: column;
		row-gap: @spacing-75;

		&--new {
			display: flex;
			align-items: end;
			column-gap: @spacing-50;
			.cdx-field {
				flex: auto;
			}
			&--submit {
				flex: none;
				// Match cdx-text-input padding
				padding-bottom: 4px;
			}
		}

		&--existing {
			display: flex;
			column-gap: @spacing-50;

			&--info {
				flex: auto;
			}
			button {
				flex: none;
			}
			&--links {
				&::before {
					content: '@{msg-parentheses-start}';
				}
				&::after {
					content: '@{msg-parentheses-end} . . ';
				}
				> span:not( :first-child )::before {
					content: '@{msg-pipe-separator}';
				}
			}
			&--summary {
				/* Like core comment class */
				font-style: italic;
				unicode-bidi: isolate;
				overflow-wrap: break-word;
				word-break: break-word;
			}
		}
	}
}
</style>

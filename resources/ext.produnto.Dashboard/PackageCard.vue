<template>
	<div
		class="ext-produnto-package"
		:class="show ? [] : [ 'ext-produnto-package__hidden' ]"
	>
		<span class="ext-produnto-package__info">
			<span class="ext-produnto-package__info__title">
				<span v-if="localName && localName !== name">
					<span class="ext-produnto-package__info__title__local">{{ localName }}</span>
					<span class="ext-produnto-package__info__title__internal">{{ name }}</span>
				</span>
				<span v-else>{{ name }}</span>
			</span>

			<span v-if="authorList" class="ext-produnto-package__info__authors">
				<template v-if="license">{{
					msg( 'produnto-dashboard-byline-license', authorList, authors.length, license )
				}}</template>
				<template v-else>{{
					msg( 'produnto-dashboard-byline', authorList, authors.length )
				}}</template>
			</span>

			<span class="ext-produnto-package__info__description">
				{{ description }}
			</span>

			<span
				v-if="homepageUrl || collabUrl || docUrl || issueUrl || repoViewerUrl"
				class="ext-produnto-package__info__links"
			>
				<span v-if="homepageUrl">
					<a :href="homepageUrl">{{ msg( 'produnto-dashboard-homepage' ) }}</a>
				</span>
				<span v-if="collabUrl">
					<a :href="collabUrl">{{ msg( 'produnto-dashboard-collab' ) }}</a>
				</span>
				<span v-if="docUrl">
					<a :href="docUrl">{{ msg( 'produnto-dashboard-docs' ) }}</a>
				</span>
				<span v-if="issueUrl">
					<a :href="issueUrl">{{ msg( 'produnto-dashboard-issues' ) }}</a>
				</span>
				<span v-if="repoViewerUrl">
					<a :href="repoViewerUrl">{{ msg( 'produnto-dashboard-browse' ) }}</a>
				</span>
			</span>
		</span>
		<span class="ext-produnto-package__version">
			<cdx-select
				v-model:selected="wrappedSelected"
				:menu-items="versionItems"
			></cdx-select>
			<span class="ext-produnto-package__version__error">
				<message-list
					type="warning"
					:messages="errors.concat( validationErrors )"
				></message-list>
			</span>
		</span>
	</div>
</template>

<script>
const { computed, defineComponent, toRef } = require( 'vue' );
const { CdxSelect, useModelWrapper } = require( './codex.js' );
const { compareVersionsBackwards } = require( './compareVersions.js' );
const MessageList = require( './MessageList.vue' );

/**
 * Box showing one package in a package list
 */
module.exports = defineComponent( {
	name: 'PackageCard',

	components: {
		CdxSelect,
		MessageList
	},

	props: {
		// eslint-disable-next-line vue/no-unused-properties
		selected: { type: [ String, null ], default: null },
		deployed: { type: String, default: null },
		name: { type: [ String, null ], default: null },
		localName: { type: [ String, null ], default: null },
		description: { type: [ String, null ], default: null },
		authors: { type: Array, default: () => [] },
		license: { type: [ String, null ], default: null },
		homepageUrl: { type: [ String, null ], default: null },
		collabUrl: { type: [ String, null ], default: null },
		docUrl: { type: [ String, null ], default: null },
		issueUrl: { type: [ String, null ], default: null },
		versions: { type: Array, default: () => [] },
		show: { type: Boolean, required: true },
		errors: { type: Array, required: true },
		validationErrors: { type: Array, required: true },
	},

	emits: [
		'update:selected',
	],

	setup( props, { emit } ) {
		const wrappedSelected = useModelWrapper( toRef( props, 'selected' ), emit, 'update:selected' );
		const repoViewerUrl = computed( () => {
			if ( props.deployed === '' ) {
				return null;
			}
			const title = mw.Title.makeTitle(
				mw.config.get( 'wgNamespaceIds' ).package,
				props.name
			);
			return title ? title.getUrl() : null;
		} );

		return {
			msg: mw.msg,
			authorList: computed( () => mw.language.listToText( props.authors || [] ) ),
			versionItems: computed( () => {
				const versions = props.versions;
				versions.sort( compareVersionsBackwards );
				const versionItems = [ { label: 'Not deployed', value: '' } ];
				for ( const v of props.versions ) {
					const item = { label: v, value: v };
					if ( v === props.deployed ) {
						item.label = mw.msg( 'produnto-dashboard-active', item.label );
						item.boldLabel = true;
					}
					versionItems.push( item );
				}
				return versionItems;
			} ),
			wrappedSelected,
			repoViewerUrl,
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-package {
	background-color: @background-color-base;
	display: flex;
	align-items: flex-start;
	position: relative;
	border: @border-base;
	border-radius: @border-radius-base;
	padding: @spacing-75;
	column-gap: @spacing-50;

	&__hidden {
		display: none;
	}

	&__info {
		flex: 1 1 auto;
		display: flex;
		flex-direction: column;
		font-size: @font-size-medium;
		line-height: @line-height-small;
		word-break: normal;
		overflow-wrap: anywhere;

		&__title {
			color: @color-base;
			font-weight: @font-weight-bold;
			line-height: @line-height-small;

			&__internal {
				margin-inline-start: 1em;
				&::before {
					content: '@{msg-brackets-start}';
				}
				&::after {
					content: '@{msg-brackets-end}';
				}
			}
		}

		&__authors {
			font-size: @font-size-x-small;
			line-height: @line-height-x-small;
			margin-left: @spacing-50;
		}

		&__description {
			color: @color-subtle;
			margin-top: @spacing-25;
		}

		&__links {
			margin-top: @spacing-50;

			&>span {
				margin-left: @spacing-50;
				display: inline list-item;
			}
		}
	}

	&__version {
		flex: 0 0 auto;
		display: flex;
		flex-direction: column;
		row-gap: @spacing-50;

		&__error {
			/* Match the width of the cdx-select */
			max-width: @min-width-medium;
		}
	}
}
</style>

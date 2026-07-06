<template>
	<div class="ext-produnto-package-list">
		<package-card
			v-for="p in packages"
			:key="p.id"
			:name="p.name"
			:local-name="p.localName"
			:description="p.description"
			:homepage-url="p.homepageUrl"
			:collab-url="p.collabUrl"
			:doc-url="p.docUrl"
			:issue-url="p.issueUrl"
			:authors="p.authors"
			:license="p.license"
			:versions="p.versions"
			:errors="p.errors[selectedVersions[p.name]] || []"
			:validation-errors="validationErrors[p.name] || []"
			:selected="selectedVersions[p.name]"
			:deployed="activeVersions[p.name] || ''"
			:show="doesPackageMatch( p )"
			@update:selected="( val ) => $emit( 'updateSelected', p.name, val )"
		></package-card>
	</div>
</template>

<script>

const { defineComponent } = require( 'vue' );
const PackageCard = require( './PackageCard.vue' );

const searchFields = [ 'name', 'localName', 'description' ];

/**
 * A list of packages with their versions and metadata
 */
module.exports = defineComponent( {
	name: 'PackageList',
	components: {
		PackageCard
	},
	props: {
		packages: { type: Array, required: true },
		activeVersions: { type: Object, required: true },
		selectedVersions: { type: Object, required: true },
		validationErrors: { type: Object, required: true },
		search: { type: String, required: true },
	},
	emits: [
		'updateSelected'
	],
	setup( props ) {
		function doesPackageMatch( pkg ) {
			const search = props.search.toLowerCase();
			if ( !search.length ) {
				// No search term -- show all packages
				return true;
			}
			for ( const field of searchFields ) {
				const val = pkg[field];
				if ( val !== undefined && val.toLowerCase().includes( search ) ) {
					return true;
				}
			}
			return false;
		}

		return {
			doesPackageMatch,
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-package-list {
	display: flex;
	flex-direction: column;
	row-gap: @spacing-50;
}
</style>

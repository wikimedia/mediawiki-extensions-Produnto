<template>
	<cdx-message type="error">
		<p>{{ msg( 'produnto-dashboard-init-error', error ) }}</p>
		<p><cdx-button
			action="progressive"
			weight="primary"
			@click="reload"
		>{{ msg( 'produnto-dashboard-reload' ) }}</cdx-button></p>
	</cdx-message>
</template>

<script>

const { defineComponent } = require( 'vue' );
const { CdxButton, CdxMessage } = require( './codex.js' );

module.exports = defineComponent( {
	name: 'ErrorPage',
	components: {
		CdxButton,
		CdxMessage,
	},
	props: {
		error: { type: String, required: true },
	},
	setup() {
		return {
			reload: () => window.location.reload(),
			msg: mw.msg
		};
	}
} );

</script>

<template>
	<cdx-message v-if="messages.length" :type="type">
		<ul v-if="messages.length > 1">
			<li v-for="message in messages" :key="message">
				{{ message }}
			</li>
		</ul>
		<div v-else>
			{{ messages[0] }}
		</div>
		<cdx-checkbox
			v-if="showIgnore"
			v-model="ignoreWrapper"
			class="ext-produnto-messagelist--ignore"
		>{{ msg( 'produnto-dashboard-ignore-warnings' ) }}</cdx-checkbox>
	</cdx-message>
</template>

<script>

const { defineComponent, toRef } = require( 'vue' );
const { CdxCheckbox, CdxMessage, useModelWrapper } = require( './codex.js' );

module.exports = defineComponent( {
	name: 'MessageList',

	components: {
		CdxCheckbox,
		CdxMessage
	},

	props: {
		showIgnore: { type: Boolean, default: false },
		// eslint-disable-next-line vue/no-unused-properties
		ignore: Boolean,
		messages: { type: Array, required: true },
		type: { type: String, required: true },
	},

	emits: [
		'update:ignore'
	],

	setup( props, { emit } ) {
		return {
			ignoreWrapper: useModelWrapper( toRef( props, 'ignore' ), emit, 'update:ignore' ),
			msg: mw.msg
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-messagelist--ignore {
	margin-top: @spacing-50;
}
</style>

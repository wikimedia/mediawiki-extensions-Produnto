<template>
	<cdx-message v-if="fetchError" type="error">
		{{ fetchError }}
	</cdx-message>
	<cdx-progress-indicator v-else-if="initializing">
		{{ msg( 'produnto-sandbox-loading' ) }}
	</cdx-progress-indicator>
	<cdx-message v-else-if="!sandboxes.length">
		{{ msg( 'produnto-sandbox-empty' ) }}
	</cdx-message>
	<div
		v-for="( sandbox, index ) in sandboxes"
		:key="sandbox.mtime"
		class="produnto-sandbox-card"
	>
		<div class="produnto-sandbox-id">
			{{ sandbox.id }}
		</div>
		<div class="produnto-sandbox-flex">
			<ul class="produnto-sandbox-grow">
				<li v-if="sandbox.active">{{ msg( 'produnto-sandbox-active' ) }}</li>
				<li v-else>{{ msg( 'produnto-sandbox-inactive' ) }}</li>
				<li>{{ msg( 'produnto-sandbox-size-kb', kb( sandbox.size ) ) }}</li>
				<li>{{ msg( 'produnto-sandbox-mtime', datetime( sandbox.mtime ) ) }}</li>
			</ul>
			<div class="produnto-sandbox-buttons">
				<cdx-button
					v-if="sandbox.active"
					@click="onActivateToggle( index, sandbox.id, true )"
				>{{ msg( 'produnto-sandbox-deactivate' ) }}</cdx-button>
				<template v-else>
					<cdx-button
						action="destructive"
						@click="onDelete( index, sandbox.id, false )"
					>{{ msg( 'produnto-sandbox-delete' ) }}</cdx-button>
					<cdx-button
						action="progressive"
						@click="onActivateToggle( index, sandbox.id, false )"
					>{{ msg( 'produnto-sandbox-activate' ) }}</cdx-button>
				</template>
			</div>
		</div>
	</div>
	<cdx-toast-container></cdx-toast-container>
</template>

<script>
const { defineComponent, ref, onMounted } = require( 'vue' );
const {
	CdxButton,
	CdxMessage,
	CdxProgressIndicator,
	CdxToastContainer,
	useToast,
} = require( './codex.js' );
const { formatTimeAndDate } = require( 'mediawiki.DateFormatter' );

const POLL_INTERVAL = 3000;

module.exports = defineComponent( {
	name: 'ProduntoSandboxApp',
	components: {
		CdxButton,
		CdxMessage,
		CdxProgressIndicator,
		CdxToastContainer,
	},
	setup() {
		const initializing = ref( true );
		const sandboxes = ref( [] );
		const fetchError = ref( null );
		const toast = useToast();

		const api = new mw.Api();
		let pollTimer = null;
		let fetchPending = false;

		function onPollTimeout() {
			pollTimer = null;
			fetchPending = true;
			function onFinally() {
				fetchPending = false;
				if ( !pollTimer && !document.hidden ) {
					pollTimer = setTimeout( onPollTimeout, POLL_INTERVAL );
				}
			}
			doFetch().then( onFinally ).catch( onFinally );
		}

		async function doFetch() {
			try {
				const response = await fetchRest( 'sandbox' );
				fetchError.value = null;
				sandboxes.value = await response.json();
			} catch ( e ) {
				fetchError.value = String( e );
			}
		}

		async function fetchRest( path, options ) {
			const response = await fetch(
				mw.config.get( 'wgScriptPath' ) + '/rest.php/produnto/v1/' + path,
				options !== undefined ? options : {}
			);
			initializing.value = false;
			if ( !response.ok ) {
				throw new Error( `${ response.status } ${ response.statusText }` );
			}
			return response;
		}

		async function onActivateToggle( index, id, isActive ) {
			const form = new FormData();
			form.set( 'token', await api.getToken( 'csrf' ) );
			try {
				await fetchRest(
					isActive ? `sandbox/${ id }/deactivate` : `sandbox/${ id }/activate`,
					{
						method: 'POST',
						body: form,
					}
				);
			} catch ( e ) {
				showToastError( e );
				return;
			}
			for ( const sandbox of sandboxes.value ) {
				if ( sandbox.id === id ) {
					sandbox.active = !isActive;
				} else if ( !isActive ) {
					sandbox.active = false;
				}
			}
			if ( sandboxes.value[index].id === id ) {
				sandboxes.value[index].active = !isActive;
			}
		}

		async function onDelete( index, id ) {
			try {
				await fetchRest(
					`sandbox/${ id }`,
					{ method: 'DELETE' }
				);
			} catch ( e ) {
				showToastError( e );
				return;
			}
			if ( sandboxes.value[index].id === id ) {
				sandboxes.value.splice( index, 1 );
			}
		}

		function onShow() {
			if ( !pollTimer && !fetchPending ) {
				onPollTimeout();
			}
		}

		function onHide() {
			if ( pollTimer ) {
				clearTimeout( pollTimer );
				pollTimer = null;
			}
		}

		function showToastError( msg ) {
			toast.show( { message: String( msg ), type: 'error', autoDismiss: true } );
		}

		function datetime( dt ) {
			return formatTimeAndDate( new Date( dt ) );
		}

		function kb( n ) {
			return mw.language.convertNumber( Math.round( n / 1024 ) );
		}

		onMounted( onPollTimeout );

		document.addEventListener( 'visibilitychange', () => {
			if ( document.hidden ) {
				onHide();
			} else {
				onShow();
			}
		} );

		return {
			datetime,
			fetchError,
			initializing,
			kb,
			msg: mw.msg,
			onActivateToggle,
			onDelete,
			sandboxes,
		};
	}
} );
</script>


<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-produnto-sandbox-vue-app {
	.produnto-sandbox-card {
		background-color: @background-color-base;
		border: @border-base;
		border-radius: @border-radius-base;
		padding: @spacing-75;
	}

	.produnto-sandbox-id {
		font-size: 120%;
		font-weight: bold;
	}

	.produnto-sandbox-flex {
		display: flex;
	}

	.produnto-sandbox-grow {
		flex: 1 0 auto;
	}
}
</style>

( function () {
	const App = require( './App.vue' );
	const ErrorPage = require( './ErrorPage.vue' );
	const Vue = require( 'vue' );
	const Client = require( './Client.js' );

	// Defer mounting until we have the API data, so that the user only sees one
	// kind of spinner.
	const client = new Client();
	client.start().then( ( { deployments, packages } ) => {
		Vue.createMwApp(
			App,
			{
				initialDeployments: deployments,
				initialPackages: packages,
				client,
			}
		).mount( '#ext-produnto-dashboard-vue-root' );
	} ).catch( ( e ) => {
		Vue.createMwApp(
			ErrorPage,
			{
				error: e instanceof Error ? e.message : e.toString()
			}
		).mount( '#ext-produnto-dashboard-vue-root' );
		throw e;
	} );
}() );

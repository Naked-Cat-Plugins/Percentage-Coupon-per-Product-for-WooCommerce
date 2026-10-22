const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: '.',
	testMatch: '*.spec.js',
	globalSetup: require.resolve( './global-setup.js' ),
	globalTeardown: require.resolve( './global-teardown.js' ),
	// The tests share one customer and its cart.
	workers: 1,
	fullyParallel: false,
	timeout: 90000,
	expect: { timeout: 15000 },
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.BASE_URL || 'https://wordpress.local',
		ignoreHTTPSErrors: true,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
} );

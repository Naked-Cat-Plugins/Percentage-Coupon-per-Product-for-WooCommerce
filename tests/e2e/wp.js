// Runs the fixtures script through WP-CLI against the WordPress install this plugin lives in.
const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const WP_PATH = process.env.WP_PATH || path.resolve( __dirname, '../../../../..' );
const FIXTURES = path.join( __dirname, 'fixtures.php' );

function wp( ...args ) {
	const output = execFileSync( 'wp', [ `--path=${ WP_PATH }`, '--skip-themes', 'eval-file', FIXTURES, ...args.map( String ) ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'ignore' ],
	} );
	// Other plugins on a dev install may print notices before the JSON.
	const json = output.split( '\n' ).reverse().find( ( line ) => line.startsWith( '{' ) );
	if ( ! json ) {
		throw new Error( `No JSON from fixtures.php ${ args.join( ' ' ) }:\n${ output }` );
	}
	return JSON.parse( json );
}

module.exports = { wp };

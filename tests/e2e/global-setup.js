const fs = require( 'fs' );
const path = require( 'path' );
const { wp, MU_PLUGIN } = require( './wp' );

module.exports = async () => {
	fs.mkdirSync( path.dirname( MU_PLUGIN ), { recursive: true } );
	fs.copyFileSync( path.join( __dirname, 'mu-plugin.php' ), MU_PLUGIN );
	const fixtures = wp( 'create' );
	fs.writeFileSync( path.join( __dirname, '.fixtures.json' ), JSON.stringify( fixtures, null, '\t' ) );
	console.log( `Fixtures created, HPOS ${ fixtures.hpos ? 'on' : 'off' }` );
};

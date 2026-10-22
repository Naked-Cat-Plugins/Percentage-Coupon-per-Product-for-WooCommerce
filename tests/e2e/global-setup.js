const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );

module.exports = async () => {
	const fixtures = wp( 'create' );
	fs.writeFileSync( path.join( __dirname, '.fixtures.json' ), JSON.stringify( fixtures, null, '\t' ) );
	console.log( `Fixtures created, HPOS ${ fixtures.hpos ? 'on' : 'off' }` );
};

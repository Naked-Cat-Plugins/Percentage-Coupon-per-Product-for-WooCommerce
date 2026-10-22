const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );

module.exports = async () => {
	if ( process.env.PPCW_KEEP_FIXTURES ) {
		return;
	}
	wp( 'delete' );
	fs.rmSync( path.join( __dirname, '.fixtures.json' ), { force: true } );
};

const fs = require( 'fs' );
const path = require( 'path' );
const { wp, MU_PLUGIN } = require( './wp' );

module.exports = async () => {
	fs.rmSync( MU_PLUGIN, { force: true } );
	if ( process.env.PPCW_KEEP_FIXTURES ) {
		return;
	}
	wp( 'delete' );
	fs.rmSync( path.join( __dirname, '.fixtures.json' ), { force: true } );
};

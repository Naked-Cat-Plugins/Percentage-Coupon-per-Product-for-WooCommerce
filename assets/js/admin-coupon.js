/* Percentage Coupon per Product for WooCommerce: show the plugin's coupon setting only for its coupon type */
jQuery( function( $ ) {
	$( 'select#discount_type' ).on( 'change', function() {
		$( '.product_percentage_coupon_woo_coupon_field' ).toggle( 'percent_per_product' === $( this ).val() );
	} ).trigger( 'change' );
} );

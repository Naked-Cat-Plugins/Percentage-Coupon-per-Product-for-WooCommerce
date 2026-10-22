/* Percentage Coupon per Product for WooCommerce: show each percentage field only while its coupon is active */
jQuery( function( $ ) {
	function product_percentage_coupon_hide_fields() {
		$( '.product_percentage_coupon_woo_active' ).each( function() {
			var id = $( this ).attr( 'id' );
			var perc_id = id.replace( '_active_', '_amount_' );
			if ( $( this ).val() == 'yes' ) {
				$( '.'+perc_id+'_field' ).show();
			} else {
				$( '.'+perc_id+'_field' ).hide();
			}
		} );
	}
	product_percentage_coupon_hide_fields();
	$( document ).on( 'change', '.product_percentage_coupon_woo_active', function() {
		product_percentage_coupon_hide_fields();
	} );
	$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function() {
		product_percentage_coupon_hide_fields();
	} );
} );

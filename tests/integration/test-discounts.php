<?php
/**
 * Integration tests for Percentage Coupon per Product for WooCommerce.
 *
 * Runs inside a real WordPress + WooCommerce install, through WP-CLI:
 *
 *     wp eval-file tests/integration/test-discounts.php
 *
 * Creates its own products, coupons and orders (all prefixed "PPCW Test"), runs every scenario
 * through the real cart and order discount code, and deletes everything it created at the end.
 * Exits non-zero if any assertion fails.
 *
 * Discounts are compared including tax, so the expected values hold whether the store enters
 * prices with or without tax.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

if ( ! class_exists( 'Woo_Product_Percentage_Coupon' ) ) {
	WP_CLI::error( 'Percentage Coupon per Product for WooCommerce is not active.' );
}

// wp eval-file runs this file inside a function, so the shared state must be declared global.
global $ppcw_created, $ppcw_results;

$ppcw_created = array(
	'products' => array(),
	'coupons'  => array(),
	'orders'   => array(),
);
$ppcw_results = array(
	'pass' => 0,
	'fail' => 0,
);

/**
 * Record one assertion.
 *
 * @param string $label    What is being checked.
 * @param float  $expected Expected value.
 * @param float  $actual   Actual value.
 */
function ppcw_assert_amount( $label, $expected, $actual ) {
	global $ppcw_results;
	if ( abs( (float) $expected - (float) $actual ) < 0.011 ) {
		++$ppcw_results['pass'];
		WP_CLI::log( WP_CLI::colorize( "%G  PASS%n {$label} (" . wc_format_decimal( $actual, 2 ) . ')' ) );
	} else {
		++$ppcw_results['fail'];
		WP_CLI::log( WP_CLI::colorize( "%R  FAIL%n {$label}: expected " . wc_format_decimal( $expected, 2 ) . ', got ' . wc_format_decimal( $actual, 2 ) ) );
	}
}

/**
 * Record a boolean assertion.
 *
 * @param string $label  What is being checked.
 * @param bool   $result Whether it held.
 * @param string $detail Extra information shown on failure.
 */
function ppcw_assert_true( $label, $result, $detail = '' ) {
	global $ppcw_results;
	if ( $result ) {
		++$ppcw_results['pass'];
		WP_CLI::log( WP_CLI::colorize( "%G  PASS%n {$label}" ) );
	} else {
		++$ppcw_results['fail'];
		WP_CLI::log( WP_CLI::colorize( "%R  FAIL%n {$label}" . ( $detail ? ": {$detail}" : '' ) ) );
	}
}

/**
 * Create a simple product.
 *
 * @param string $name    Product name suffix.
 * @param string $regular Regular price.
 * @param string $sale    Sale price, empty for none.
 * @param array  $meta    Meta to set.
 * @return WC_Product_Simple
 */
function ppcw_simple_product( $name, $regular, $sale = '', $meta = array() ) {
	global $ppcw_created;
	$product = new WC_Product_Simple();
	$product->set_name( "PPCW Test {$name}" );
	$product->set_status( 'publish' );
	$product->set_regular_price( $regular );
	$product->set_sale_price( $sale );
	foreach ( $meta as $key => $value ) {
		$product->update_meta_data( $key, $value );
	}
	$product->save();
	$ppcw_created['products'][] = $product->get_id();
	return $product;
}

/**
 * Create a coupon of the plugin's type.
 *
 * @param string $code               Coupon code.
 * @param string $amount             Default percentage.
 * @param bool   $exclude_sale_items Whether sale items are excluded.
 * @return WC_Coupon
 */
function ppcw_coupon( $code, $amount, $exclude_sale_items = false ) {
	global $ppcw_created;
	$existing = wc_get_coupon_id_by_code( $code );
	if ( $existing ) {
		wp_delete_post( $existing, true );
		wp_cache_delete( WC_Cache_Helper::get_cache_prefix( 'coupons' ) . 'coupon_id_from_code_' . md5( wc_strtolower( $code ) ), 'coupons' );
	}
	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'percent_per_product' );
	$coupon->set_amount( $amount );
	$coupon->set_exclude_sale_items( $exclude_sale_items );
	$coupon->save();
	$ppcw_created['coupons'][] = $coupon->get_id();
	return $coupon;
}

/**
 * Empty the cart, add items, apply a coupon and return the coupon's discount including tax.
 *
 * @param array  $items Array of array( product_id, quantity, variation_id ).
 * @param string $code  Coupon code.
 * @return array( float discount, bool applied, string[] notices )
 */
function ppcw_cart_discount( $items, $code ) {
	wc_clear_notices();
	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
	foreach ( $items as $item ) {
		$product = wc_get_product( $item[2] ?? $item[0] );
		WC()->cart->add_to_cart( $item[0], $item[1], $item[2] ?? 0, $product->is_type( 'variation' ) ? $product->get_variation_attributes() : array() );
	}
	$applied = WC()->cart->apply_coupon( $code );
	WC()->cart->calculate_totals();
	$notices = wp_list_pluck( wc_get_notices( 'error' ), 'notice' );
	wc_clear_notices();
	$discount = WC()->cart->get_coupon_discount_amount( $code, false );
	return array( $discount, $applied, $notices );
}

/**
 * Create an order with items, apply the coupon the way the admin "Apply coupon" button does,
 * and return the coupon's discount including tax.
 *
 * @param array  $items Array of array( product_id, quantity, variation_id ).
 * @param string $code  Coupon code.
 * @return float
 */
function ppcw_order_discount( $items, $code ) {
	global $ppcw_created;
	$order = wc_create_order();
	foreach ( $items as $item ) {
		$order->add_product( wc_get_product( $item[2] ?? $item[0] ), $item[1] );
	}
	$order->set_billing_country( WC()->countries->get_base_country() );
	$order->calculate_totals();
	$order->apply_coupon( $code );
	$order->save();
	$ppcw_created['orders'][] = $order->get_id();
	$order = wc_get_order( $order->get_id() );
	return (float) $order->get_discount_total() + (float) $order->get_discount_tax();
}

/**
 * Forget the coupon list the plugin caches for the request, after creating coupons.
 */
function ppcw_reset_coupon_cache() {
	$property = new ReflectionProperty( 'Woo_Product_Percentage_Coupon', 'coupons' );
	$property->setAccessible( true );
	$property->setValue( Woo_Product_Percentage_Coupon(), null );
}

/**
 * Save a product through the plugin's handler, as the product edit screen does.
 *
 * @param int   $product_id Product ID.
 * @param array $posted     Posted fields.
 */
function ppcw_save_product( $product_id, $posted ) {
	$_POST = $posted;
	Woo_Product_Percentage_Coupon()->woocommerce_process_product_meta( $product_id );
	$_POST = array();
}

/**
 * Save a variation through the plugin's handler, as the variations panel does.
 *
 * @param int   $variation_id Variation ID.
 * @param int   $index        Loop index in the form.
 * @param array $posted       Posted fields.
 */
function ppcw_save_variation( $variation_id, $index, $posted ) {
	$_POST = $posted;
	Woo_Product_Percentage_Coupon()->woocommerce_save_product_variation( $variation_id, $index );
	$_POST = array();
}

/**
 * Count the plugin's meta rows on a product or variation.
 *
 * @param int $product_id Product or variation ID.
 * @return int
 */
function ppcw_meta_count( $product_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $product_id, '_product_percentage_coupon_woo_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Save a coupon's plugin settings through the plugin's handler, as the coupon edit screen does.
 *
 * @param int   $coupon_id Coupon ID.
 * @param array $posted    Posted fields.
 */
function ppcw_save_coupon( $coupon_id, $posted ) {
	$_POST = $posted;
	Woo_Product_Percentage_Coupon()->woocommerce_coupon_options_save( $coupon_id, new WC_Coupon( $coupon_id ) );
	$_POST = array();
}

/**
 * Whether a coupon in the cart gives free shipping, the check WooCommerce's free shipping method makes.
 *
 * @return bool
 */
function ppcw_cart_has_free_shipping_coupon() {
	foreach ( WC()->cart->get_coupons() as $cart_coupon ) {
		if ( $cart_coupon->is_valid() && $cart_coupon->get_free_shipping() ) {
			return true;
		}
	}
	return false;
}

/**
 * Create an order with items and apply a coupon from the admin, keeping the result.
 *
 * @param array  $items Array of array( product_id, quantity, variation_id ).
 * @param string $code  Coupon code.
 * @return array( result, codes, discount )
 */
function ppcw_order_apply( $items, $code ) {
	global $ppcw_created;
	$order = wc_create_order();
	foreach ( $items as $item ) {
		$order->add_product( wc_get_product( $item[2] ?? $item[0] ), $item[1] );
	}
	$order->set_billing_country( WC()->countries->get_base_country() );
	$order->calculate_totals();
	$result = $order->apply_coupon( $code );
	$order->save();
	$ppcw_created['orders'][] = $order->get_id();
	$order                    = wc_get_order( $order->get_id() );
	return array(
		'result'   => $result,
		'codes'    => $order->get_coupon_codes(),
		'discount' => (float) $order->get_discount_total() + (float) $order->get_discount_tax(),
	);
}

WP_CLI::log( 'WordPress ' . get_bloginfo( 'version' ) . ', WooCommerce ' . WC_VERSION . ', PHP ' . PHP_VERSION . ', HPOS ' . ( wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ? 'on' : 'off' ) );

try {
	wc_load_cart();
	// A WP-CLI customer has no address, so WooCommerce would strip the base tax from tax inclusive prices and add none back.
	WC()->customer->set_billing_location( WC()->countries->get_base_country(), WC()->countries->get_base_state(), WC()->countries->get_base_postcode(), WC()->countries->get_base_city() );
	WC()->customer->set_shipping_location( WC()->countries->get_base_country(), WC()->countries->get_base_state(), WC()->countries->get_base_postcode(), WC()->countries->get_base_city() );

	// Coupons: one that allows sale items, one that excludes them.
	$coupon      = ppcw_coupon( 'ppcw-test-a', '10' );
	$coupon_sale = ppcw_coupon( 'ppcw-test-nosale', '10', true );
	$coupon_core = ppcw_coupon( 'ppcw-test-core', '10' );
	$coupon_core->set_discount_type( 'percent' );
	$coupon_core->save();
	$a           = $coupon->get_id();
	$b           = $coupon_sale->get_id();

	// Simple products.
	$p_default  = ppcw_simple_product( 'default', '100' );
	$p_override = ppcw_simple_product( 'override 25', '100', '', array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '25' ) );
	$p_sale     = ppcw_simple_product( 'sale 80 override 50', '100', '80', array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '50', "_product_percentage_coupon_woo_active_{$b}" => 'yes', "_product_percentage_coupon_woo_amount_{$b}" => '50' ) );
	$p_disabled = ppcw_simple_product( 'disabled', '100', '', array( "_product_percentage_coupon_woo_active_{$a}" => 'no' ) );
	$p_odd      = ppcw_simple_product( 'odd 9.99 override 33.33', '9.99', '', array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '33.33' ) );
	// Sale scheduled in the future: not on sale yet, so not excluded by "Exclude sale items".
	$p_future = ppcw_simple_product( 'future sale', '100', '70', array( "_product_percentage_coupon_woo_active_{$b}" => 'yes', "_product_percentage_coupon_woo_amount_{$b}" => '20' ) );
	$p_future->set_date_on_sale_from( gmdate( 'Y-m-d', strtotime( '+10 days' ) ) );
	$p_future->save();

	// Variable product: product level override 20, one variation overriding to 30, one variation on sale.
	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Size' );
	$attribute->set_options( array( 'S', 'M', 'L' ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$variable = new WC_Product_Variable();
	$variable->set_name( 'PPCW Test variable override 20' );
	$variable->set_status( 'publish' );
	$variable->set_attributes( array( $attribute ) );
	$variable->update_meta_data( "_product_percentage_coupon_woo_active_{$a}", 'yes' );
	$variable->update_meta_data( "_product_percentage_coupon_woo_amount_{$a}", '20' );
	$variable->save();
	$ppcw_created['products'][] = $variable->get_id();
	$variations = array();
	foreach ( array(
		'S' => array( '100', '', array() ),
		'M' => array( '100', '', array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '30' ) ),
		'L' => array( '100', '50', array( "_product_percentage_coupon_woo_active_{$a}" => 'no' ) ),
	) as $size => $data ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $variable->get_id() );
		$variation->set_attributes( array( 'size' => $size ) );
		$variation->set_regular_price( $data[0] );
		$variation->set_sale_price( $data[1] );
		foreach ( $data[2] as $key => $value ) {
			$variation->update_meta_data( $key, $value );
		}
		$variation->save();
		$ppcw_created['products'][] = $variation->get_id();
		$variations[ $size ]        = $variation->get_id();
	}
	WC_Product_Variable::sync( $variable->get_id() );
	$v = $variable->get_id();

	WP_CLI::log( '' );
	WP_CLI::log( 'Cart, coupon allowing sale items:' );
	ppcw_assert_amount( 'Baseline: WooCommerce\'s own 10% coupon on a 100 product', 10, ppcw_cart_discount( array( array( $p_default->get_id(), 1 ) ), 'ppcw-test-core' )[0] );
	ppcw_assert_amount( 'No product setting uses the coupon default (10% of 100)', 10, ppcw_cart_discount( array( array( $p_default->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Product override 25% of 100', 25, ppcw_cart_discount( array( array( $p_override->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Product override 25% of 100, quantity 3', 75, ppcw_cart_discount( array( array( $p_override->get_id(), 3 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Product on sale (80) with override 50% applies to the sale price', 40, ppcw_cart_discount( array( array( $p_sale->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Product set to "No" next to a default product: only the default one is discounted', 10, ppcw_cart_discount( array( array( $p_disabled->get_id(), 1 ), array( $p_default->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Odd price 9.99 at 33.33%', 3.33, ppcw_cart_discount( array( array( $p_odd->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Variation inheriting the product override (20% of 100)', 20, ppcw_cart_discount( array( array( $v, 1, $variations['S'] ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Variation with its own override (30% of 100)', 30, ppcw_cart_discount( array( array( $v, 1, $variations['M'] ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount( 'Variation on sale (50) set to "No (inherit)" uses the product override (20% of 50)', 10, ppcw_cart_discount( array( array( $v, 1, $variations['L'] ) ), 'ppcw-test-a' )[0] );
	ppcw_assert_amount(
		'One coupon, mixed cart: 10 + 25 + 40 + 0 + 20 + 30 = 125',
		125,
		ppcw_cart_discount(
			array(
				array( $p_default->get_id(), 1 ),
				array( $p_override->get_id(), 1 ),
				array( $p_sale->get_id(), 1 ),
				array( $p_disabled->get_id(), 1 ),
				array( $v, 1, $variations['S'] ),
				array( $v, 1, $variations['M'] ),
			),
			'ppcw-test-a'
		)[0]
	);

	WP_CLI::log( '' );
	WP_CLI::log( 'Cart, coupon with "Exclude sale items" ticked:' );
	list( $discount, $applied, $notices ) = ppcw_cart_discount( array( array( $p_sale->get_id(), 1 ) ), 'ppcw-test-nosale' );
	ppcw_assert_true( 'Rejected when the cart only holds sale items', ! $applied && 0.0 === (float) $discount, 'applied=' . var_export( $applied, true ) . ' discount=' . $discount );
	ppcw_assert_true( 'Shows WooCommerce\'s own sale items notice', (bool) preg_grep( '/sale/i', $notices ), implode( ' | ', $notices ) );
	ppcw_assert_amount( 'Mixed cart: sale item skipped, regular item gets the coupon default (10)', 10, ppcw_cart_discount( array( array( $p_sale->get_id(), 1 ), array( $p_default->get_id(), 1 ) ), 'ppcw-test-nosale' )[0] );
	ppcw_assert_amount( 'Sale scheduled for the future is not a sale yet, override 20% of 100', 20, ppcw_cart_discount( array( array( $p_future->get_id(), 1 ) ), 'ppcw-test-nosale' )[0] );

	WP_CLI::log( '' );
	WP_CLI::log( 'Magic URL Coupon for WooCommerce integration:' );
	$coupon_zero = ppcw_coupon( 'ppcw-test-zero', '0' );
	$z           = $coupon_zero->get_id();
	$p_zero_own  = ppcw_simple_product( 'zero coupon override 15', '100', '', array( "_product_percentage_coupon_woo_active_{$z}" => 'yes', "_product_percentage_coupon_woo_amount_{$z}" => '15' ) );
	ppcw_cart_discount( array( array( $p_default->get_id(), 1 ), array( $p_disabled->get_id(), 1 ) ), 'ppcw-test-a' );
	ppcw_assert_true( 'Product with a discount stays valid', true === apply_filters( 'magic_coupon_coupon_is_valid_for_product', true, $coupon, $p_default->get_id() ) );
	ppcw_assert_true( 'Product set to "No" is not valid', false === apply_filters( 'magic_coupon_coupon_is_valid_for_product', true, $coupon, $p_disabled->get_id() ) );
	ppcw_cart_discount( array( array( $p_default->get_id(), 1 ), array( $p_zero_own->get_id(), 1 ) ), 'ppcw-test-zero' );
	ppcw_assert_true( 'Coupon at 0%: product without its own percentage is not valid', false === apply_filters( 'magic_coupon_coupon_is_valid_for_product', true, $coupon_zero, $p_default->get_id() ) );
	ppcw_assert_true( 'Coupon at 0%: product with its own 15% stays valid', true === apply_filters( 'magic_coupon_coupon_is_valid_for_product', true, $coupon_zero, $p_zero_own->get_id() ) );

	WP_CLI::log( '' );
	WP_CLI::log( 'Products set to "No" do not count towards the coupon:' );
	$coupon_limit = ppcw_coupon( 'ppcw-test-limit', '10' );
	$coupon_limit->set_limit_usage_to_x_items( 1 );
	$coupon_limit->save();
	$l           = $coupon_limit->get_id();
	$p_limit_no  = ppcw_simple_product( 'limit no 200', '200', '', array( "_product_percentage_coupon_woo_active_{$l}" => 'no' ) );
	$p_limit_yes = ppcw_simple_product( 'limit yes 100', '100' );
	ppcw_assert_amount( '"Limit usage to 1 item": a dearer "No" product does not use up the limit', 10, ppcw_cart_discount( array( array( $p_limit_no->get_id(), 1 ), array( $p_limit_yes->get_id(), 1 ) ), 'ppcw-test-limit' )[0] );

	WP_CLI::log( '' );
	WP_CLI::log( 'Cart with nothing discounted, default (as in 1.3): the coupon is accepted with no discount:' );
	list( $discount, $applied, $notices ) = ppcw_cart_discount( array( array( $p_disabled->get_id(), 1 ) ), 'ppcw-test-a' );
	ppcw_assert_true( 'Cart with only a "No" product accepts the coupon, 0 off', $applied && 0.0 === (float) $discount && in_array( 'ppcw-test-a', WC()->cart->get_applied_coupons(), true ), 'applied=' . var_export( $applied, true ) . ' discount=' . $discount . ' ' . implode( ' | ', $notices ) );
	$coupon_fs = ppcw_coupon( 'ppcw-test-freeship', '10' );
	$coupon_fs->set_free_shipping( true );
	$coupon_fs->save();
	$p_fs_no = ppcw_simple_product( 'free shipping no', '100', '', array( '_product_percentage_coupon_woo_active_' . $coupon_fs->get_id() => 'no' ) );
	list( $discount, $applied ) = ppcw_cart_discount( array( array( $p_fs_no->get_id(), 1 ) ), 'ppcw-test-freeship' );
	ppcw_assert_true( 'A coupon that gives free shipping still gives it on that cart', $applied && ppcw_cart_has_free_shipping_coupon(), 'applied=' . var_export( $applied, true ) );
	$order_result = ppcw_order_apply( array( array( $p_disabled->get_id(), 1 ) ), 'ppcw-test-a' );
	ppcw_assert_true( 'The order records the coupon with 0 off, for tracking', true === $order_result['result'] && in_array( 'ppcw-test-a', $order_result['codes'], true ) && 0.0 === $order_result['discount'], wp_json_encode( $order_result ) );

	WP_CLI::log( '' );
	WP_CLI::log( 'Coupon set to "Refuse without discount":' );
	$coupon_refuse = ppcw_coupon( 'ppcw-test-refuse', '10' );
	$coupon_refuse->set_free_shipping( true );
	$coupon_refuse->save();
	ppcw_save_coupon( $coupon_refuse->get_id(), array( 'product_percentage_coupon_woo_refuse_without_discount' => 'yes' ) );
	ppcw_assert_true( 'Ticking the setting on the coupon screen stores it', 'yes' === ( new WC_Coupon( $coupon_refuse->get_id() ) )->get_meta( '_product_percentage_coupon_woo_refuse_without_discount' ) );
	$r       = $coupon_refuse->get_id();
	$p_ref_n = ppcw_simple_product( 'refuse no', '100', '', array( "_product_percentage_coupon_woo_active_{$r}" => 'no' ) );
	list( $discount, $applied, $notices ) = ppcw_cart_discount( array( array( $p_ref_n->get_id(), 1 ) ), 'ppcw-test-refuse' );
	ppcw_assert_true( 'Cart with only a "No" product refuses the coupon', ! $applied && 0.0 === (float) $discount, 'applied=' . var_export( $applied, true ) . ' discount=' . $discount );
	ppcw_assert_true( 'Shows WooCommerce\'s own "not applicable" notice', (bool) preg_grep( '/not applicable/i', $notices ), implode( ' | ', $notices ) );
	ppcw_assert_true( 'and gives no free shipping', ! ppcw_cart_has_free_shipping_coupon() );
	ppcw_assert_amount( 'A mixed cart still gets the discount on the other product (10)', 10, ppcw_cart_discount( array( array( $p_ref_n->get_id(), 1 ), array( $p_default->get_id(), 1 ) ), 'ppcw-test-refuse' )[0] );
	$order_result = ppcw_order_apply( array( array( $p_ref_n->get_id(), 1 ) ), 'ppcw-test-refuse' );
	ppcw_assert_true( 'Applying it to such an order from the admin is refused', is_wp_error( $order_result['result'] ), wp_json_encode( $order_result ) );
	ppcw_save_coupon( $coupon_refuse->get_id(), array() );
	ppcw_assert_true( 'Unticking the setting removes it', '' === ( new WC_Coupon( $coupon_refuse->get_id() ) )->get_meta( '_product_percentage_coupon_woo_refuse_without_discount' ) );
	ppcw_save_coupon( $coupon->get_id(), array( 'product_percentage_coupon_woo_refuse_without_discount' => 'yes' ) );
	ppcw_save_coupon( $coupon->get_id(), array() );

	WP_CLI::log( '' );
	WP_CLI::log( 'Percentages with decimals, on a store using a comma as decimal separator:' );
	$ppcw_comma = function () {
		return ',';
	};
	add_filter( 'wc_get_price_decimal_separator', $ppcw_comma );
	ppcw_reset_coupon_cache();
	$p_comma_legacy = ppcw_simple_product( 'comma legacy 12,5', '100', '', array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '12,5' ) );
	ppcw_assert_amount( 'Value saved by an older version as "12,5" is applied as 12.5%', 12.5, ppcw_cart_discount( array( array( $p_comma_legacy->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	$p_comma_saved = ppcw_simple_product( 'comma saved', '100' );
	ppcw_save_product( $p_comma_saved->get_id(), array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '12,5' ) );
	ppcw_assert_true( '"12,5" typed on the product is stored as "12.5"', '12.5' === wc_get_product( $p_comma_saved->get_id() )->get_meta( "_product_percentage_coupon_woo_amount_{$a}" ) );
	ppcw_assert_amount( '"12,5" typed on the product is applied as 12.5%', 12.5, ppcw_cart_discount( array( array( $p_comma_saved->get_id(), 1 ) ), 'ppcw-test-a' )[0] );
	remove_filter( 'wc_get_price_decimal_separator', $ppcw_comma );

	WP_CLI::log( '' );
	WP_CLI::log( 'Saving product and variation settings:' );
	ppcw_reset_coupon_cache();
	$p_save = ppcw_simple_product( 'save', '100' );
	ppcw_save_product( $p_save->get_id(), array() );
	ppcw_assert_true( 'Saving without the plugin\'s fields writes nothing', 0 === ppcw_meta_count( $p_save->get_id() ), ppcw_meta_count( $p_save->get_id() ) . ' rows' );
	ppcw_save_product( $p_save->get_id(), array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '' ) );
	ppcw_assert_true( 'Saving the default values ("Yes", empty) writes nothing', 0 === ppcw_meta_count( $p_save->get_id() ), ppcw_meta_count( $p_save->get_id() ) . ' rows' );
	ppcw_save_product( $p_save->get_id(), array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '15', "_product_percentage_coupon_woo_active_{$b}" => 'no' ) );
	ppcw_assert_true( 'Saving a percentage and a "No" writes just those two', 2 === ppcw_meta_count( $p_save->get_id() ), ppcw_meta_count( $p_save->get_id() ) . ' rows' );
	ppcw_save_product( $p_save->get_id(), array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '15' ) );
	ppcw_assert_true( 'A coupon missing from the form keeps its setting', 'no' === wc_get_product( $p_save->get_id() )->get_meta( "_product_percentage_coupon_woo_active_{$b}" ) );
	ppcw_save_product( $p_save->get_id(), array( "_product_percentage_coupon_woo_active_{$a}" => 'yes', "_product_percentage_coupon_woo_amount_{$a}" => '', "_product_percentage_coupon_woo_active_{$b}" => 'yes' ) );
	ppcw_assert_true( 'Going back to the defaults removes the settings', 0 === ppcw_meta_count( $p_save->get_id() ), ppcw_meta_count( $p_save->get_id() ) . ' rows' );
	ppcw_save_variation( $variations['S'], 0, array( "_product_percentage_coupon_woo_active_{$a}_variable" => array( 'no' ), "_product_percentage_coupon_woo_amount_{$a}_variable" => array( '' ) ) );
	ppcw_assert_true( 'Variation saved as "No (inherit)" with no percentage writes nothing', 0 === ppcw_meta_count( $variations['S'] ), ppcw_meta_count( $variations['S'] ) . ' rows' );
	ppcw_assert_amount( 'and still inherits the product override (20% of 100)', 20, ppcw_cart_discount( array( array( $v, 1, $variations['S'] ) ), 'ppcw-test-a' )[0] );
	ppcw_save_variation( $variations['S'], 0, array( "_product_percentage_coupon_woo_active_{$a}_variable" => array( 'yes' ), "_product_percentage_coupon_woo_amount_{$a}_variable" => array( '35' ) ) );
	ppcw_assert_amount( 'Variation saved with its own 35%', 35, ppcw_cart_discount( array( array( $v, 1, $variations['S'] ) ), 'ppcw-test-a' )[0] );
	ppcw_save_variation( $variations['S'], 0, array( "_product_percentage_coupon_woo_active_{$a}_variable" => array( 'no' ), "_product_percentage_coupon_woo_amount_{$a}_variable" => array( '' ) ) );

	WP_CLI::log( '' );
	WP_CLI::log( 'Coupon list and robustness:' );
	$coupon_dup_1 = ppcw_coupon( 'ppcw-test-dup', '10' );
	$coupon_dup_2 = new WC_Coupon();
	$coupon_dup_2->set_code( 'ppcw-test-dup' );
	$coupon_dup_2->set_discount_type( 'percent_per_product' );
	$coupon_dup_2->set_amount( '20' );
	$coupon_dup_2->save();
	$ppcw_created['coupons'][] = $coupon_dup_2->get_id();
	ppcw_reset_coupon_cache();
	$listed = array_map(
		function ( $c ) {
			return $c->get_id();
		},
		Woo_Product_Percentage_Coupon()->get_all_coupons()
	);
	ppcw_assert_true( 'Two coupons sharing a code are both listed on the product screen', in_array( $coupon_dup_1->get_id(), $listed, true ) && in_array( $coupon_dup_2->get_id(), $listed, true ) );
	$ppcw_warnings = array();
	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		function ( $errno, $errstr ) use ( &$ppcw_warnings ) {
			$ppcw_warnings[] = $errstr;
			return true;
		}
	);
	$filtered = apply_filters( 'woocommerce_coupon_get_discount_amount', 5.0, 10.0, null, false, $coupon );
	restore_error_handler();
	ppcw_assert_true( 'Discount filter called without a cart item leaves the amount alone, with no PHP warning', 5.0 === (float) $filtered && empty( $ppcw_warnings ), implode( ' | ', $ppcw_warnings ) );

	WP_CLI::log( '' );
	WP_CLI::log( 'Orders, coupon applied from the admin (Apply coupon / Recalculate):' );
	ppcw_assert_amount( 'Product override 25% of 100, quantity 2', 50, ppcw_order_discount( array( array( $p_override->get_id(), 2 ) ), 'ppcw-test-a' ) );
	ppcw_assert_amount( 'Product on sale (80) with override 50%', 40, ppcw_order_discount( array( array( $p_sale->get_id(), 1 ) ), 'ppcw-test-a' ) );
	ppcw_assert_amount( 'Variation with its own override (30% of 100)', 30, ppcw_order_discount( array( array( $v, 1, $variations['M'] ) ), 'ppcw-test-a' ) );
	ppcw_assert_amount( 'Variation inheriting the product override (20% of 100)', 20, ppcw_order_discount( array( array( $v, 1, $variations['S'] ) ), 'ppcw-test-a' ) );
} catch ( Throwable $e ) {
	++$ppcw_results['fail'];
	WP_CLI::log( WP_CLI::colorize( '%R  ERROR%n ' . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() ) );
	WP_CLI::log( $e->getTraceAsString() );
} finally {
	if ( WC()->cart ) {
		WC()->cart->empty_cart();
	}
	foreach ( $ppcw_created['orders'] as $id ) {
		$order = wc_get_order( $id );
		if ( $order ) {
			$order->delete( true );
		}
	}
	foreach ( array_reverse( $ppcw_created['products'] ) as $id ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			$product->delete( true );
		}
	}
	foreach ( $ppcw_created['coupons'] as $id ) {
		wp_delete_post( $id, true );
	}
}

WP_CLI::log( '' );
if ( $ppcw_results['fail'] ) {
	WP_CLI::error( "{$ppcw_results['fail']} failed, {$ppcw_results['pass']} passed." );
}
WP_CLI::success( "All {$ppcw_results['pass']} passed." );

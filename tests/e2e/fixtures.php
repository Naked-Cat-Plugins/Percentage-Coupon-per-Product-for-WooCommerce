<?php
/**
 * Fixtures for the end-to-end tests.
 *
 *     wp eval-file tests/e2e/fixtures.php create   Prints the fixtures as JSON
 *     wp eval-file tests/e2e/fixtures.php order <order_id>   Prints an order's discounts as JSON
 *     wp eval-file tests/e2e/fixtures.php product-meta <product_id> <coupon_id>   Prints the product's meta for that coupon as JSON
 *     wp eval-file tests/e2e/fixtures.php reset-cart   Empties the test customer's saved cart
 *     wp eval-file tests/e2e/fixtures.php delete   Deletes everything "create" made, plus the test users' orders
 *
 * Everything created is prefixed "PPCW E2E" / "ppcw-e2e" so it can be found and deleted again.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

// wp eval-file runs this file inside a function, so the helpers below need these as globals.
global $ppcw_e2e_args;
$ppcw_e2e_args = $args;

/**
 * Create a simple product.
 *
 * @param string $name    Product name suffix.
 * @param string $regular Regular price.
 * @param string $sale    Sale price, empty for none.
 * @param array  $meta    Meta to set.
 * @return int
 */
function ppcw_e2e_product( $name, $regular, $sale = '', $meta = array() ) {
	$product = new WC_Product_Simple();
	$product->set_name( "PPCW E2E {$name}" );
	$product->set_status( 'publish' );
	$product->set_regular_price( $regular );
	$product->set_sale_price( $sale );
	foreach ( $meta as $key => $value ) {
		$product->update_meta_data( $key, $value );
	}
	return $product->save();
}

/**
 * Create a coupon of the plugin's type.
 *
 * @param string $code               Coupon code.
 * @param string $amount             Default percentage.
 * @param bool   $exclude_sale_items Whether sale items are excluded.
 * @return int
 */
function ppcw_e2e_coupon( $code, $amount, $exclude_sale_items = false ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_discount_type( 'percent_per_product' );
	$coupon->set_amount( $amount );
	$coupon->set_exclude_sale_items( $exclude_sale_items );
	return $coupon->save();
}

/**
 * Create a user with a random password.
 *
 * @param string $login User login.
 * @param string $role  Role.
 * @return array( id, login, password )
 */
function ppcw_e2e_user( $login, $role ) {
	$password = wp_generate_password( 24, false );
	$user_id  = wp_insert_user(
		array(
			'user_login' => $login,
			'user_email' => "{$login}@example.com",
			'user_pass'  => $password,
			'role'       => $role,
			'locale'     => 'en_US',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		WP_CLI::error( $user_id->get_error_message() );
	}
	return array(
		'id'       => $user_id,
		'login'    => $login,
		'password' => $password,
	);
}

/**
 * Delete everything the fixtures created.
 */
function ppcw_e2e_delete() {
	global $wpdb;
	foreach ( array( 'ppcw-e2e-customer', 'ppcw-e2e-admin' ) as $login ) {
		$user = get_user_by( 'login', $login );
		if ( $user ) {
			foreach ( wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => -1 ) ) as $order ) {
				$order->delete( true );
			}
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user->ID );
		}
	}
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'product', 'product_variation' ) AND post_title LIKE 'PPCW E2E %'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			$product->delete( true );
		}
	}
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_title LIKE 'ppcw-e2e%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	wp_cache_flush();
}

switch ( $ppcw_e2e_args[0] ?? '' ) {

	case 'create':
		ppcw_e2e_delete();
		$coupon        = ppcw_e2e_coupon( 'ppcw-e2e', '10' );
		$coupon_nosale = ppcw_e2e_coupon( 'ppcw-e2e-nosale', '10', true );

		$products = array(
			// 10% coupon default of 100.
			'default'  => ppcw_e2e_product( 'default', '100' ),
			// 25% of 100.
			'override' => ppcw_e2e_product( 'override 25', '100', '', array( "_product_percentage_coupon_woo_active_{$coupon}" => 'yes', "_product_percentage_coupon_woo_amount_{$coupon}" => '25' ) ),
			// 50% of the 80 sale price.
			'sale'     => ppcw_e2e_product( 'sale 80 override 50', '100', '80', array( "_product_percentage_coupon_woo_active_{$coupon}" => 'yes', "_product_percentage_coupon_woo_amount_{$coupon}" => '50', "_product_percentage_coupon_woo_active_{$coupon_nosale}" => 'yes', "_product_percentage_coupon_woo_amount_{$coupon_nosale}" => '50' ) ),
			// Not discounted.
			'disabled' => ppcw_e2e_product( 'disabled', '100', '', array( "_product_percentage_coupon_woo_active_{$coupon}" => 'no' ) ),
			// Edited through the admin UI by the tests.
			'edit'     => ppcw_e2e_product( 'edit in admin', '100' ),
		);

		// Variable product, 20% at product level, variation M overrides to 30%.
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'S', 'M' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$variable = new WC_Product_Variable();
		$variable->set_name( 'PPCW E2E variable override 20' );
		$variable->set_status( 'publish' );
		$variable->set_attributes( array( $attribute ) );
		$variable->update_meta_data( "_product_percentage_coupon_woo_active_{$coupon}", 'yes' );
		$variable->update_meta_data( "_product_percentage_coupon_woo_amount_{$coupon}", '20' );
		$variable->save();
		foreach ( array(
			'S' => array(),
			'M' => array( "_product_percentage_coupon_woo_active_{$coupon}" => 'yes', "_product_percentage_coupon_woo_amount_{$coupon}" => '30' ),
		) as $size => $meta ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $variable->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( '100' );
			foreach ( $meta as $key => $value ) {
				$variation->update_meta_data( $key, $value );
			}
			$products[ "variation_{$size}" ] = $variation->save();
		}
		WC_Product_Variable::sync( $variable->get_id() );
		$products['variable'] = $variable->get_id();

		$customer = ppcw_e2e_user( 'ppcw-e2e-customer', 'customer' );
		$wc_customer = new WC_Customer( $customer['id'] );
		$wc_customer->set_billing_first_name( 'PPCW' );
		$wc_customer->set_billing_last_name( 'E2E' );
		$wc_customer->set_billing_address_1( 'Rua de Teste 1' );
		$wc_customer->set_billing_city( 'Seixal' );
		$wc_customer->set_billing_postcode( WC()->countries->get_base_postcode() );
		$wc_customer->set_billing_country( WC()->countries->get_base_country() );
		$wc_customer->set_billing_state( WC()->countries->get_base_state() );
		$wc_customer->set_billing_phone( '912345678' );
		$wc_customer->set_billing_email( $customer['login'] . '@example.com' );
		foreach ( array( 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country', 'state' ) as $field ) {
			$wc_customer->{"set_shipping_{$field}"}( $wc_customer->{"get_billing_{$field}"}() );
		}
		$wc_customer->save();

		echo wp_json_encode(
			array(
				'coupons'  => array(
					'main'   => array( 'id' => $coupon, 'code' => 'ppcw-e2e' ),
					'nosale' => array( 'id' => $coupon_nosale, 'code' => 'ppcw-e2e-nosale' ),
				),
				'products' => $products,
				'customer' => $customer,
				'admin'    => ppcw_e2e_user( 'ppcw-e2e-admin', 'administrator' ),
				'decimal'  => wc_get_price_decimal_separator(),
				'hpos'     => wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled(),
			)
		);
		break;

	case 'order':
		$order = wc_get_order( absint( $ppcw_e2e_args[1] ?? 0 ) );
		if ( ! $order ) {
			WP_CLI::error( 'Order not found.' );
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				// Line discount including tax.
				'discount' => round( ( (float) $item->get_subtotal() + (float) $item->get_subtotal_tax() ) - ( (float) $item->get_total() + (float) $item->get_total_tax() ), 2 ),
			);
		}
		echo wp_json_encode(
			array(
				'id'             => $order->get_id(),
				'coupons'        => $order->get_coupon_codes(),
				'discount'       => round( (float) $order->get_discount_total() + (float) $order->get_discount_tax(), 2 ),
				'items'          => $items,
				// Where WooCommerce actually stored it.
				'data_store'     => WC_Data_Store::load( 'order' )->get_current_class_name(),
				'payment_method' => $order->get_payment_method(),
			)
		);
		break;

	case 'product-meta':
		$product   = wc_get_product( absint( $ppcw_e2e_args[1] ?? 0 ) );
		$coupon_id = absint( $ppcw_e2e_args[2] ?? 0 );
		echo wp_json_encode(
			array(
				'active' => $product->get_meta( "_product_percentage_coupon_woo_active_{$coupon_id}" ),
				'amount' => $product->get_meta( "_product_percentage_coupon_woo_amount_{$coupon_id}" ),
			)
		);
		break;

	case 'reset-cart':
		// A logged-in customer's cart survives between browser sessions.
		$user = get_user_by( 'login', 'ppcw-e2e-customer' );
		if ( $user ) {
			delete_user_meta( $user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id() );
			( new WC_Session_Handler() )->delete_session( (string) $user->ID );
		}
		echo wp_json_encode( array( 'reset' => true ) );
		break;

	case 'delete':
		ppcw_e2e_delete();
		echo wp_json_encode( array( 'deleted' => true ) );
		break;

	default:
		WP_CLI::error( 'Usage: wp eval-file tests/e2e/fixtures.php create|order <id>|product-meta <product_id> <coupon_id>|delete' );
}

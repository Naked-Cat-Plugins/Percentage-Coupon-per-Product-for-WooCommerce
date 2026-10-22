<?php
/**
 * Plugin Name:          Percentage Coupon per Product for WooCommerce
 * Plugin URI:
 * Description:          Create WooCommerce coupons with variable discount percentage per product
 * Version:              1.3
 * Author:               Naked Cat Plugins (by Webdados)
 * Author URI:           https://nakedcatplugins.com
 * Text Domain:          product-percentage-coupon-woo
 * Requires at least:    5.8
 * Tested up to:         6.9
 * Requires PHP:         7.2
 * WC requires at least: 7.1
 * WC tested up to:      9.9
 * Requires Plugins:     woocommerce
 * License:              GPLv3
 **/

/**
 * WooCommerce CRUD ready (except get_all_coupons, because there's no CRUD methods)
 * WooCommerce HPOS ready - https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Keep in sync with the "Version" and "WC requires at least" headers above
define( 'PCPW_VERSION', '1.3' );
define( 'PCPW_WC_REQ_VERSION', '7.1' );

/**
 * Add plugin initialization hook.
 *
 * @return void
 */
function woo_product_percentage_coupon_init() {
	load_plugin_textdomain( 'product-percentage-coupon-woo' );
	if ( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, PCPW_WC_REQ_VERSION, '>=' ) ) {
		require_once __DIR__ . '/includes/class-woo-product-percentage-coupon.php';
		$GLOBALS['Woo_Product_Percentage_Coupon'] = Woo_Product_Percentage_Coupon();
	} else {
		add_action( 'admin_notices', 'admin_notices_woo_product_percentage_coupon_woocommerce_not_active' );
	}
}
add_action( 'init', 'woo_product_percentage_coupon_init', 1 );

/**
 * Main class
 *
 * @return Woo_Product_Percentage_Coupon
 */
function Woo_Product_Percentage_Coupon() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Woo_Product_Percentage_Coupon::instance();
}

/**
 * Admin notice for when WooCommerce is not active or the version is lower than required.
 *
 * @return void
 */
function admin_notices_woo_product_percentage_coupon_woocommerce_not_active() {
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s is the required WooCommerce version */
					__( '<strong>Percentage Coupon per Product for WooCommerce</strong> is installed and active but <strong>WooCommerce (%s or above)</strong> is not.', 'product-percentage-coupon-woo' ),
					PCPW_WC_REQ_VERSION
				)
			);
			?>
		</p>
	</div>
	<?php
}

/* HPOS Compatible */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);


/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */

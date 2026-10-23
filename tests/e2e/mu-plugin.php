<?php
/**
 * Installed in wp-content/mu-plugins by the e2e global setup, removed by the teardown.
 *
 * Action Scheduler's async queue runner is dispatched with the cookies of the request that
 * triggers it, so it loads that shopper's cart session and saves it back when its batch ends.
 * If the shopper changed the cart meanwhile, the runner overwrites it with the older copy
 * (a WooCommerce core race, not this plugin's). The tests add several products within seconds,
 * right after creating fixtures that queue background jobs, so they hit it. This stops the
 * runner from being dispatched from the test users' own requests; WP-Cron still runs the queue.
 *
 * @package product-percentage-coupon-woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'action_scheduler_allow_async_request_runner',
	function ( $allow ) {
		$user = wp_get_current_user();
		if ( $user && in_array( $user->user_login, array( 'ppcw-e2e-customer', 'ppcw-e2e-admin' ), true ) ) {
			return false;
		}
		return $allow;
	}
);

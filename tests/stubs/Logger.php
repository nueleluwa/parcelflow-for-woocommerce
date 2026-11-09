<?php
/**
 * Test double: records log calls instead of writing to $wpdb.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Support;

// phpcs:disable
class Logger {
	public static function log( $args ) {
		$GLOBALS['pf_logs'][] = $args;
	}
}

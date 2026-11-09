<?php
/**
 * Declares WooCommerce feature compatibility.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class Compatibility {

	/**
	 * Declare compatibility with WooCommerce features.
	 *
	 * HPOS: all order access in this plugin uses WC CRUD (wc_get_order, WC_Order meta
	 * methods, wc_get_orders) and never touches wp_posts/wp_postmeta for orders. However
	 * the declaration is an explicit claim of having been tested, so it is OFF by default
	 * and must be enabled (filter below) once the HPOS test scenarios in docs/TESTING.md
	 * have been run on a real store.
	 *
	 * Cart/Checkout Blocks: the plugin ships no block of its own, so no declaration is made.
	 */
	public static function declare_features() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}
		/**
		 * Filter: set to true only after HPOS has been verified on a real installation.
		 *
		 * @param bool $declare Whether to declare custom_order_tables compatibility.
		 */
		if ( apply_filters( 'parcelflow_wc_declare_hpos_compatible', false ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PARCELFLOW_WC_FILE, true );
		}
	}
}

<?php
/**
 * ParcelFlow → Help / API Status.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class HelpPage {

	public function render() {
		global $wp_version;
		$base = (string) Settings::get( 'base_url' );
		View::render(
			'help',
			array(
				'connection'   => get_transient( 'parcelflow_wc_connection' ),
				'base_url'     => $base,
				'env'          => array(
					'plugin'      => PARCELFLOW_WC_VERSION,
					'wp'          => $wp_version,
					'wc'          => defined( 'WC_VERSION' ) ? WC_VERSION : '—',
					'php'         => PHP_VERSION,
					'hpos'        => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
					'blocks'      => function_exists( 'has_block' ) && wc_get_page_id( 'checkout' ) > 0 && has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) ),
					'as'          => function_exists( 'as_schedule_single_action' ),
					'token'       => Settings::has_token(),
					'token_const' => Settings::token_from_constant(),
				),
				'tracking_url' => \ParcelFlow\WooCommerce\Tracking\TrackingPage::url_for(),
			)
		);
	}
}

<?php
/**
 * Activation / deactivation.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce;

use ParcelFlow\WooCommerce\Data\Schema;
use ParcelFlow\WooCommerce\Tracking\Scheduler;
use ParcelFlow\WooCommerce\Tracking\TrackingPage;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate() {
		Schema::install();
		add_option( \ParcelFlow\WooCommerce\Settings\Settings::OPTION, \ParcelFlow\WooCommerce\Settings\Settings::defaults(), '', false );
		TrackingPage::ensure_page();
		update_option( 'parcelflow_wc_flush_rewrite', 1, false );
	}

	public static function deactivate() {
		Scheduler::unschedule_all();
		flush_rewrite_rules( false );
	}
}

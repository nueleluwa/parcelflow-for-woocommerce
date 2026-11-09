<?php
/**
 * Action Scheduler jobs: tracking polling, stale-booking sweep, log purge.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Tracking;

use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	const TRACK_HOOK = 'parcelflow_wc_refresh_tracking';
	const PURGE_HOOK = 'parcelflow_wc_purge_logs';
	const GROUP      = 'parcelflow';

	public function register() {
		add_action( self::TRACK_HOOK, array( TrackingService::class, 'refresh_open_shipments' ) );
		add_action( self::PURGE_HOOK, array( Logger::class, 'purge_old' ) );
		add_action( 'action_scheduler_init', array( $this, 'schedule' ) );
	}

	/**
	 * Ensure recurring jobs exist. Runs on action_scheduler_init (Action Scheduler ships with
	 * WooCommerce).
	 */
	public function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		// Poll every 30 minutes; the per-shipment refresh interval is enforced by the query.
		if ( ! as_has_scheduled_action( self::TRACK_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + 300, 30 * MINUTE_IN_SECONDS, self::TRACK_HOOK, array(), self::GROUP );
		}
		if ( ! as_has_scheduled_action( self::PURGE_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::PURGE_HOOK, array(), self::GROUP );
		}
	}

	public static function unschedule_all() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::TRACK_HOOK, array(), self::GROUP );
			as_unschedule_all_actions( self::PURGE_HOOK, array(), self::GROUP );
			as_unschedule_all_actions( \ParcelFlow\WooCommerce\Orders\OrderIntegration::BOOK_HOOK, null, self::GROUP );
		}
	}
}

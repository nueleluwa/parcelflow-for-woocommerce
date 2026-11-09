<?php
/**
 * Database schema (shipment index + API log tables).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Data;

defined( 'ABSPATH' ) || exit;

final class Schema {

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'parcelflow_wc_db_version';

	public static function shipments_table() {
		global $wpdb;
		return $wpdb->prefix . 'parcelflow_shipments';
	}

	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'parcelflow_logs';
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$ship    = self::shipments_table();
		$logs    = self::logs_table();

		// One shipment row per WooCommerce order. The UNIQUE(order_id) key is the
		// atomic duplicate-booking guard: only one row can ever exist per order, and the
		// state transition awaiting/failed -> in_progress is a single conditional UPDATE.
		dbDelta(
			"CREATE TABLE {$ship} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			order_number varchar(64) NOT NULL DEFAULT '',
			state varchar(20) NOT NULL DEFAULT 'awaiting',
			status_group varchar(20) NOT NULL DEFAULT 'pending',
			pf_shipment_id varchar(64) NOT NULL DEFAULT '',
			shipment_number varchar(128) NOT NULL DEFAULT '',
			tracking_number varchar(128) NOT NULL DEFAULT '',
			label_url text NULL,
			status varchar(191) NOT NULL DEFAULT '',
			carrier_id varchar(64) NOT NULL DEFAULT '',
			carrier_name varchar(191) NOT NULL DEFAULT '',
			rate_id varchar(191) NOT NULL DEFAULT '',
			shipping_fee decimal(18,4) NULL,
			total_fee decimal(18,4) NULL,
			currency varchar(10) NOT NULL DEFAULT '',
			error_message text NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			request_json longtext NULL,
			events_json longtext NULL,
			booked_at datetime NULL,
			last_attempt_at datetime NULL,
			last_tracked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_id (order_id),
			KEY shipment_number (shipment_number(100)),
			KEY tracking_number (tracking_number(100)),
			KEY status_group (status_group),
			KEY created_at (created_at)
		) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			event_type varchar(40) NOT NULL DEFAULT '',
			success tinyint(1) NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NULL,
			shipment_number varchar(128) NOT NULL DEFAULT '',
			endpoint varchar(191) NOT NULL DEFAULT '',
			http_code smallint(6) NULL,
			message text NULL,
			context longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY event_type (event_type),
			KEY order_id (order_id)
		) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function drop() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names only.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i, %i', self::shipments_table(), self::logs_table() ) );
		delete_option( self::DB_VERSION_OPTION );
	}
}

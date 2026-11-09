<?php
/**
 * Uninstall handler. Data is only removed when "Delete all data on uninstall" is enabled.
 * Order metadata is never deleted.
 *
 * @package ParcelFlow\WooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$parcelflow_settings = get_option( 'parcelflow_wc_settings', array() );

if ( is_array( $parcelflow_settings ) && isset( $parcelflow_settings['delete_data_on_uninstall'] ) && 'yes' === $parcelflow_settings['delete_data_on_uninstall'] ) {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}parcelflow_shipments, {$wpdb->prefix}parcelflow_logs" );
	// phpcs:enable
	foreach ( array( 'parcelflow_wc_settings', 'parcelflow_wc_access_token', 'parcelflow_wc_seen_carriers', 'parcelflow_wc_db_version', 'parcelflow_wc_tracking_page_id', 'parcelflow_wc_flush_rewrite' ) as $parcelflow_option ) {
		delete_option( $parcelflow_option );
	}
}

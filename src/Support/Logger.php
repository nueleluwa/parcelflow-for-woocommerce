<?php
/**
 * Audit / diagnostic logger backed by a dedicated table (plus the WC logger in debug mode).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Support;

use ParcelFlow\WooCommerce\Data\Schema;
use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Logger {

	/** Event types shown in the Logs filter. */
	public static function event_types() {
		return array(
			'rate_request'    => __( 'Rate calculation', 'parcelflow-for-woocommerce' ),
			'shipment_create' => __( 'Shipment booking', 'parcelflow-for-woocommerce' ),
			'tracking'        => __( 'Tracking', 'parcelflow-for-woocommerce' ),
			'history'         => __( 'Shipment history', 'parcelflow-for-woocommerce' ),
			'estimate'        => __( 'Shipment estimate', 'parcelflow-for-woocommerce' ),
			'connection_test' => __( 'Connection test', 'parcelflow-for-woocommerce' ),
			'booking'         => __( 'Booking workflow', 'parcelflow-for-woocommerce' ),
			'system'          => __( 'System', 'parcelflow-for-woocommerce' ),
		);
	}

	/**
	 * Write a log row. $context is redacted before storage.
	 *
	 * @param array $args event_type, success, order_id, shipment_number, endpoint, http_code, message, context.
	 */
	public static function log( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'event_type'      => 'system',
				'success'         => true,
				'order_id'        => null,
				'shipment_number' => '',
				'endpoint'        => '',
				'http_code'       => null,
				'message'         => '',
				'context'         => array(),
			)
		);

		$context = '';
		if ( ! empty( $args['context'] ) ) {
			$context = wp_json_encode( Redactor::redact( $args['context'] ) );
			if ( strlen( (string) $context ) > 20000 ) {
				$context = substr( $context, 0, 20000 ) . '…[truncated]';
			}
		}

		$message = Redactor::scrub_string( (string) $args['message'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			Schema::logs_table(),
			array(
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
				'event_type'      => sanitize_key( $args['event_type'] ),
				'success'         => $args['success'] ? 1 : 0,
				'order_id'        => $args['order_id'] ? absint( $args['order_id'] ) : null,
				'shipment_number' => substr( sanitize_text_field( (string) $args['shipment_number'] ), 0, 128 ),
				'endpoint'        => substr( sanitize_text_field( (string) $args['endpoint'] ), 0, 191 ),
				'http_code'       => null === $args['http_code'] ? null : (int) $args['http_code'],
				'message'         => $message,
				'context'         => $context,
			)
		);

		if ( ! $args['success'] && Settings::is( 'debug_logging' ) && function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				sprintf( '[%s] %s (HTTP %s)', $args['event_type'], $message, (string) $args['http_code'] ),
				array( 'source' => 'parcelflow' )
			);
		}
	}

	public static function purge_old() {
		global $wpdb;
		$days = max( 1, (int) Settings::get( 'log_retention_days', 30 ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s',
				Schema::logs_table(),
				gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )
			)
		);
	}
}

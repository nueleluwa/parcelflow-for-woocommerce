<?php
/**
 * Read access to the API log table.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Data;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom plugin table, accessed directly by design.
final class LogRepository {

	public static function find( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::logs_table(), $id ), ARRAY_A );
	}

	/**
	 * @param array $args event_type, status (success|failure|''), order (order id or number text), date_from, date_to, limit, offset.
	 * @return array{rows:array,total:int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'event_type' => '',
				'status'     => '',
				'order'      => '',
				'date_from'  => '',
				'date_to'    => '',
				'limit'      => 25,
				'offset'     => 0,
			)
		);
		// Static SQL with optional filters ("(<value> = '' OR <condition>)"); see ShipmentRepository.
		$type    = (string) $args['event_type'];
		$success = 'success' === $args['status'] ? '1' : ( 'failure' === $args['status'] ? '0' : '' );
		$order   = '' !== $args['order'] ? (string) (int) ltrim( $args['order'], '#' ) : '';
		$from    = '' !== $args['date_from'] ? get_gmt_from_date( $args['date_from'] . ' 00:00:00' ) : '';
		$to      = '' !== $args['date_to'] ? get_gmt_from_date( $args['date_to'] . ' 23:59:59' ) : '';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE (%s = '' OR event_type = %s) AND (%s = '' OR success = %d) AND (%s = '' OR order_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s)",
				Schema::logs_table(),
				$type,
				$type,
				$success,
				(int) $success,
				$order,
				(int) $order,
				$from,
				$from,
				$to,
				$to
			)
		);
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE (%s = '' OR event_type = %s) AND (%s = '' OR success = %d) AND (%s = '' OR order_id = %d) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) ORDER BY id DESC LIMIT %d OFFSET %d",
				Schema::logs_table(),
				$type,
				$type,
				$success,
				(int) $success,
				$order,
				(int) $order,
				$from,
				$from,
				$to,
				$to,
				(int) $args['limit'],
				(int) $args['offset']
			),
			ARRAY_A
		);

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	public static function clear() {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', Schema::logs_table() ) );
	}
}

<?php
/**
 * Shipment index table access. One row per WooCommerce order.
 *
 * Reference data (shipment number, tracking number, label, status, fee) is additionally
 * mirrored to order meta by the booking service, so it travels with the order.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Data;

use ParcelFlow\WooCommerce\Support\Status;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- custom plugin tables are accessed directly by design; table names come from Schema (prefix + constant), never from user input; all values use placeholders.
final class ShipmentRepository {

	private static function table() {
		return Schema::shipments_table();
	}

	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function find( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );
	}

	public static function find_by_order( $order_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', self::table(), $order_id ), ARRAY_A );
	}

	/**
	 * Find by ParcelFlow shipment number or tracking number (exact match).
	 */
	public static function find_by_reference( $ref ) {
		global $wpdb;
		$ref = (string) $ref;
		if ( '' === $ref ) {
			return null;
		}
		// phpcs:ignore WordPress.DB
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE shipment_number = %s OR tracking_number = %s ORDER BY id DESC LIMIT 1',
				self::table(),
				$ref,
				$ref
			),
			ARRAY_A
		);
	}

	/**
	 * Create the awaiting row for a paid order. Returns false if one already exists
	 * (UNIQUE(order_id)), which is the desired idempotent behaviour.
	 */
	public static function create_awaiting( $order_id, $order_number, array $extra = array() ) {
		global $wpdb;
		$now  = self::now();
		$row  = array_merge(
			array(
				'order_id'     => (int) $order_id,
				'order_number' => (string) $order_number,
				'state'        => Status::STATE_AWAITING,
				'status_group' => 'pending',
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			$extra
		);
		$prev = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert( self::table(), $row );
		$wpdb->suppress_errors( $prev );
		return false !== $ok;
	}

	/**
	 * Atomically claim an order for booking. Exactly one caller can win: the UPDATE only
	 * matches rows in a retry-safe state, and affected-rows is checked.
	 */
	public static function claim_for_booking( $order_id ) {
		global $wpdb;
		$now = self::now();
		// phpcs:ignore WordPress.DB
		$n = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET state = %s, status_group = %s, attempts = attempts + 1, last_attempt_at = %s, updated_at = %s, error_message = '' WHERE order_id = %d AND state IN (%s, %s)",
				self::table(),
				Status::STATE_IN_PROGRESS,
				'processing',
				$now,
				$now,
				$order_id,
				Status::STATE_AWAITING,
				Status::STATE_FAILED
			)
		);
		return 1 === (int) $n;
	}

	/**
	 * Update fields on an order's row. status_group is recomputed when state/status change.
	 */
	public static function update( $order_id, array $fields ) {
		global $wpdb;
		$row = self::find_by_order( $order_id );
		if ( ! $row ) {
			return false;
		}
		$state  = isset( $fields['state'] ) ? $fields['state'] : $row['state'];
		$status = isset( $fields['status'] ) ? $fields['status'] : $row['status'];
		if ( isset( $fields['state'] ) || isset( $fields['status'] ) ) {
			$fields['status_group'] = Status::group( $state, $status );
		}
		$fields['updated_at'] = self::now();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update( self::table(), $fields, array( 'order_id' => (int) $order_id ) );
	}

	/**
	 * Move a stuck in_progress row (crashed request) to uncertain. Never back to retryable.
	 */
	public static function mark_stale_in_progress_uncertain( $older_than_seconds = 600 ) {
		global $wpdb;
		$cut = gmdate( 'Y-m-d H:i:s', time() - $older_than_seconds );
		// phpcs:ignore WordPress.DB
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET state = %s, status_group = 'failed', error_message = %s, updated_at = %s WHERE state = %s AND last_attempt_at < %s",
				self::table(),
				Status::STATE_UNCERTAIN,
				'Booking request did not complete (possible crash or timeout). Verify with ParcelFlow before retrying.',
				self::now(),
				Status::STATE_IN_PROGRESS,
				$cut
			)
		);
	}

	/**
	 * @param array $args search, group, date_from, date_to (Y-m-d, site timezone), limit, offset.
	 * @return array{rows:array,total:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'search'    => '',
				'group'     => '',
				'date_from' => '',
				'date_to'   => '',
				'limit'     => 20,
				'offset'    => 0,
			)
		);
		// Static SQL: each optional filter is "(<value> = '' OR <condition>)", so no SQL text is
		// assembled at runtime and every value goes through a placeholder.
		$search = (string) $args['search'];
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$group  = (string) $args['group'];
		$from   = '' !== $args['date_from'] ? get_gmt_from_date( $args['date_from'] . ' 00:00:00' ) : '';
		$to     = '' !== $args['date_to'] ? get_gmt_from_date( $args['date_to'] . ' 23:59:59' ) : '';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE (%s = '' OR (order_number LIKE %s OR shipment_number LIKE %s OR tracking_number LIKE %s OR order_id = %d)) AND (%s = '' OR status_group = %s) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s)",
				self::table(),
				$search,
				$like,
				$like,
				$like,
				(int) $search,
				$group,
				$group,
				$from,
				$from,
				$to,
				$to
			)
		);

		if ( (int) $args['limit'] > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE (%s = '' OR (order_number LIKE %s OR shipment_number LIKE %s OR tracking_number LIKE %s OR order_id = %d)) AND (%s = '' OR status_group = %s) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) ORDER BY id DESC LIMIT %d OFFSET %d",
					self::table(),
					$search,
					$like,
					$like,
					$like,
					(int) $search,
					$group,
					$group,
					$from,
					$from,
					$to,
					$to,
					(int) $args['limit'],
					(int) $args['offset']
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE (%s = '' OR (order_number LIKE %s OR shipment_number LIKE %s OR tracking_number LIKE %s OR order_id = %d)) AND (%s = '' OR status_group = %s) AND (%s = '' OR created_at >= %s) AND (%s = '' OR created_at <= %s) ORDER BY id DESC",
					self::table(),
					$search,
					$like,
					$like,
					$like,
					(int) $search,
					$group,
					$group,
					$from,
					$from,
					$to,
					$to
				),
				ARRAY_A
			);
		}

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * @return array<string,int> Counts keyed by status group plus "all".
	 */
	public static function counts() {
		global $wpdb;
		$out = array( 'all' => 0 );
		foreach ( array_keys( Status::groups() ) as $g ) {
			$out[ $g ] = 0;
		}
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status_group, COUNT(*) AS c FROM %i GROUP BY status_group', self::table() ), ARRAY_A );
		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r['status_group'] ] ) ) {
				$out[ $r['status_group'] ] = (int) $r['c'];
			}
			$out['all'] += (int) $r['c'];
		}
		return $out;
	}

	/**
	 * Booked, non-final shipments due for a tracking refresh.
	 */
	public static function due_for_tracking( $older_than_hours, $limit = 25 ) {
		global $wpdb;
		$cut = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $older_than_hours ) * HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.DB
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE state = %s AND shipment_number <> '' AND status_group NOT IN ('delivered','failed') AND (last_tracked_at IS NULL OR last_tracked_at < %s) ORDER BY last_tracked_at ASC LIMIT %d",
				self::table(),
				Status::STATE_BOOKED,
				$cut,
				(int) $limit
			),
			ARRAY_A
		);
	}
}

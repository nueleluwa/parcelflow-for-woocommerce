<?php
/**
 * Tracking and history via the documented ParcelFlow endpoints.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Tracking;

use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Api\Response;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;
use ParcelFlow\WooCommerce\Orders\OrderIntegration;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;

defined( 'ABSPATH' ) || exit;

final class TrackingService {

	/**
	 * Normalise a track-shipment response. Only documented fields are read; events are
	 * shown exactly as returned (description/timestamp), sorted chronologically.
	 *
	 * @return array{status:string,events:array<int,array{id:string,timestamp:string,description:string}>}
	 */
	public static function parse( Response $res ) {
		$d      = $res->data();
		$d      = is_array( $d ) ? $d : array();
		$events = array();
		if ( isset( $d['events'] ) && is_array( $d['events'] ) ) {
			foreach ( $d['events'] as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$events[] = array(
					'id'          => isset( $e['id'] ) ? sanitize_text_field( (string) $e['id'] ) : '',
					'timestamp'   => isset( $e['timestamp'] ) ? sanitize_text_field( (string) $e['timestamp'] ) : '',
					'description' => isset( $e['description'] ) ? sanitize_text_field( (string) $e['description'] ) : '',
				);
			}
		}
		usort(
			$events,
			static function ( $a, $b ) {
				$c = strcmp( $a['timestamp'], $b['timestamp'] );
				return 0 !== $c ? $c : (int) $a['id'] - (int) $b['id'];
			}
		);
		return array(
			'status' => isset( $d['status'] ) && is_scalar( $d['status'] ) ? sanitize_text_field( (string) $d['status'] ) : '',
			'events' => $events,
		);
	}

	/**
	 * Fetch tracking for a stored shipment row and persist the result.
	 *
	 * @param array $row Shipment row (must have shipment_number).
	 * @return array{ok:bool,message:string,status:string,events:array}
	 */
	public static function refresh( array $row ) {
		$res = ( new Client() )->track_shipment( $row['shipment_number'], array( 'order_id' => (int) $row['order_id'] ) );
		if ( ! $res->success ) {
			return array(
				'ok'      => false,
				'message' => $res->message,
				'status'  => $row['status'],
				'events'  => array(),
			);
		}
		$t      = self::parse( $res );
		$fields = array(
			'events_json'     => wp_json_encode( $t['events'] ),
			'last_tracked_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		// The sample response shows an empty status string; never overwrite a known status with ''.
		if ( '' !== $t['status'] ) {
			$fields['status'] = $t['status'];
		}
		ShipmentRepository::update( (int) $row['order_id'], $fields );

		if ( '' !== $t['status'] && $t['status'] !== $row['status'] ) {
			$order = wc_get_order( (int) $row['order_id'] );
			if ( $order ) {
				$order->update_meta_data( '_parcelflow_status', $t['status'] );
				$order->save_meta_data();
			}
		}
		return array(
			'ok'      => true,
			'message' => '',
			'status'  => '' !== $t['status'] ? $t['status'] : $row['status'],
			'events'  => $t['events'],
		);
	}

	/**
	 * Public lookup for the tracking page. Only shipments booked through this store (known
	 * locally) can be looked up, so the shared API token cannot be used to browse other
	 * companies'/customers' shipments. Sender/receiver addresses are never returned.
	 *
	 * @param string $input Shipment or tracking number typed by the visitor.
	 * @return array{state:string,message:string,row?:array,status?:string,events?:array}
	 *         state: empty|invalid|throttled|not_found|error|ok
	 */
	public static function lookup_public( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return array(
				'state'   => 'empty',
				'message' => __( 'Please enter a tracking number.', 'parcelflow-for-woocommerce' ),
			);
		}
		if ( strlen( $input ) > 64 || ! preg_match( '/^[A-Za-z0-9._\-\/ ]+$/', $input ) ) {
			return array(
				'state'   => 'invalid',
				'message' => __( 'That tracking number format is not valid. Use letters, numbers and hyphens only.', 'parcelflow-for-woocommerce' ),
			);
		}
		if ( self::throttled() ) {
			return array(
				'state'   => 'throttled',
				'message' => __( 'Too many lookups. Please wait a minute and try again.', 'parcelflow-for-woocommerce' ),
			);
		}

		$row = ShipmentRepository::find_by_reference( $input );
		if ( ! $row || '' === $row['shipment_number'] ) {
			return array(
				'state'   => 'not_found',
				'message' => __( 'We could not find a shipment with that number. Check the number and try again.', 'parcelflow-for-woocommerce' ),
			);
		}

		$cache_key = 'pf_track_' . md5( $row['shipment_number'] );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_merge(
				array(
					'state'   => 'ok',
					'message' => '',
					'row'     => $row,
				),
				$cached
			);
		}

		$r = self::refresh( $row );
		if ( ! $r['ok'] ) {
			if ( ! empty( $row['events_json'] ) ) {
				// Offer the last known timeline rather than nothing.
				$events = json_decode( $row['events_json'], true );
				return array(
					'state'   => 'ok',
					'message' => __( 'Live tracking is temporarily unavailable; showing the last known update.', 'parcelflow-for-woocommerce' ),
					'row'     => $row,
					'status'  => $row['status'],
					'events'  => is_array( $events ) ? $events : array(),
				);
			}
			return array(
				'state'   => 'error',
				'message' => __( 'Tracking is temporarily unavailable. Please try again later.', 'parcelflow-for-woocommerce' ),
			);
		}
		$payload = array(
			'status' => $r['status'],
			'events' => $r['events'],
		);
		set_transient( $cache_key, $payload, 3 * MINUTE_IN_SECONDS );
		return array_merge(
			array(
				'state'   => 'ok',
				'message' => '',
				'row'     => $row,
			),
			$payload
		);
	}

	/** 15 lookups / minute / visitor IP. */
	private static function throttled() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore
		/**
		 * Filter the visitor identifier used for throttling. Behind a reverse proxy or CDN
		 * REMOTE_ADDR is shared by every visitor; return the trusted client IP here instead.
		 *
		 * @param string $ip Visitor IP address.
		 */
		$ip  = (string) apply_filters( 'parcelflow_wc_client_ip', $ip );
		$key = 'pf_trk_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 15 ) {
			return true;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return false;
	}

	/* --------------------------- Background jobs ------------------------- */

	/** Action Scheduler callback: refresh open shipments. */
	public static function refresh_open_shipments() {
		$hours = (int) \ParcelFlow\WooCommerce\Settings\Settings::get( 'tracking_refresh_hours', 6 );
		ShipmentRepository::mark_stale_in_progress_uncertain();
		if ( $hours <= 0 ) {
			return;
		}
		foreach ( ShipmentRepository::due_for_tracking( $hours ) as $row ) {
			self::refresh( $row );
		}
	}
}

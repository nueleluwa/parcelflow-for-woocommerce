<?php
/**
 * Shipment booking with duplicate protection and careful handling of uncertain outcomes.
 *
 * Safety model
 * ------------
 *  1. Eligibility: the order must be paid (processing/completed) and contain a ParcelFlow
 *     shipping line. Unpaid, failed, cancelled and refunded orders are never booked.
 *  2. All validation happens BEFORE the lock, so a fixable problem never consumes an attempt.
 *  3. The lock is an atomic conditional UPDATE (awaiting|failed -> in_progress) on a row
 *     with UNIQUE(order_id). Only one concurrent request can win it.
 *  4. Outcome classification:
 *       - success with a shipment reference         -> booked
 *       - HTTP 4xx + success=false, or request that never left -> failed (explicit retry OK)
 *       - everything else (timeout, 5xx, malformed body, 2xx without reference, crash)
 *                                                     -> uncertain (NO retry; reconcile)
 *  5. "uncertain" and "in_progress" can never be retried; they require an operator to
 *     link the existing shipment or confirm that none was created.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Booking;

use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;
use ParcelFlow\WooCommerce\Orders\OrderIntegration;
use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Shipping\PackageBuilder;
use ParcelFlow\WooCommerce\Shipping\PayloadMapper;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Logger;
use ParcelFlow\WooCommerce\Support\Status;

defined( 'ABSPATH' ) || exit;

final class BookingService {

	/**
	 * Can this order be booked right now? (Does not look at the booking lock.)
	 *
	 * @return string[] Reasons it cannot; empty when eligible.
	 */
	public static function eligibility_errors( \WC_Order $order ) {
		$errors = array();
		if ( ! Settings::is_configured() ) {
			$errors[] = __( 'ParcelFlow is not enabled or no access token is configured.', 'parcelflow-for-woocommerce' );
		}
		if ( ! $order->is_paid() ) {
			$errors[] = __( 'The order has not been paid (status must be Processing or Completed).', 'parcelflow-for-woocommerce' );
		}
		if ( ! OrderIntegration::shipping_item( $order ) ) {
			$errors[] = __( 'The order does not use a ParcelFlow shipping method.', 'parcelflow-for-woocommerce' );
		}
		return $errors;
	}

	/**
	 * @param int   $order_id Order ID.
	 * @param array $opts     rate_id (manual, optional), source ('manual'|'automatic').
	 * @return array{ok:bool,code:string,message:string}
	 */
	public static function book( $order_id, array $opts = array() ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return self::result( false, 'blocked', __( 'Order not found.', 'parcelflow-for-woocommerce' ) );
		}
		$source = isset( $opts['source'] ) ? $opts['source'] : 'manual';

		$errors = self::eligibility_errors( $order );
		if ( $errors ) {
			return self::blocked( $order, $errors );
		}

		// Existing row? Refuse anything that could create a duplicate.
		$row = ShipmentRepository::find_by_order( $order->get_id() );
		if ( $row ) {
			switch ( $row['state'] ) {
				case Status::STATE_BOOKED:
					return self::result( false, 'duplicate', __( 'A shipment has already been booked for this order.', 'parcelflow-for-woocommerce' ) );
				case Status::STATE_IN_PROGRESS:
					return self::result( false, 'duplicate', __( 'A booking request for this order is already in progress.', 'parcelflow-for-woocommerce' ) );
				case Status::STATE_UNCERTAIN:
					return self::result( false, 'duplicate', __( 'The previous booking outcome is uncertain. Reconcile it with ParcelFlow before booking again.', 'parcelflow-for-woocommerce' ) );
			}
		}

		// ---- Validate everything before taking the lock ----
		$item = OrderIntegration::shipping_item( $order );
		$rate = OrderIntegration::rate_from_item( $item );
		$msgs = array();

		if ( '' === $rate['carrier_id'] || null === $rate['selling_price'] ) {
			$msgs[] = __( 'The selected ParcelFlow carrier/rate was not retained on the order.', 'parcelflow-for-woocommerce' );
		}

		// rate_id: only ever the value returned by the API at checkout, or one an
		// administrator typed in from ParcelFlow. Never derived or guessed.
		$rate_id = $rate['rate_id'];
		if ( '' === $rate_id && ! empty( $opts['rate_id'] ) ) {
			$rate_id = sanitize_text_field( $opts['rate_id'] );
		}
		if ( '' === $rate_id ) {
			$msgs[] = __( 'No rate_id is available. The Create Shipment endpoint requires one, but the Calculate Shipment Cost response did not return it. Enter the rate ID supplied by ParcelFlow, or wait for ParcelFlow to confirm where it is returned.', 'parcelflow-for-woocommerce' );
		}

		$sender   = PayloadMapper::sender();
		$receiver = PayloadMapper::receiver_from_order( $order );
		$built    = PackageBuilder::from_order( $order );
		$carton   = array();
		$msgs     = array_merge( $msgs, $sender['errors'], $receiver['errors'], $built['errors'] );
		$override = self::package_override( $opts );
		$msgs     = array_merge( $msgs, $override['errors'] );
		if ( ! $built['errors'] ) {
			$carton = PackageBuilder::carton( $built['lines'] );
			if ( $override['values'] ) {
				// Administrator-entered carton: replaces the calculated weight/dimensions
				// (including "missing dimensions" errors). Item-level data is untouched.
				$carton = array_merge( $carton, $override['values'], array( 'errors' => array() ) );
			}
			$msgs = array_merge( $msgs, $carton['errors'] );
		}
		$type = PayloadMapper::shipment_type( $sender, $receiver );
		if ( '' === $type ) {
			$msgs[] = __( 'Shipment type is not set. Choose one under ParcelFlow > Settings > Shipping & Booking.', 'parcelflow-for-woocommerce' );
		}
		if ( Settings::rate_currency() !== $order->get_currency() ) {
			$msgs[] = __( 'The order currency differs from the ParcelFlow price currency; no conversion is performed.', 'parcelflow-for-woocommerce' );
		}

		$payload = array();
		if ( ! $msgs ) {
			$built_payload = PayloadMapper::create_payload( $sender, $receiver, $built['lines'], $carton, $type, $rate, $rate_id );
			$msgs          = $built_payload['errors'];
			$payload       = $built_payload['payload'];
		}
		if ( $msgs ) {
			return self::blocked( $order, array_values( array_unique( $msgs ) ) );
		}

		// ---- Take the lock ----
		if ( ! $row ) {
			ShipmentRepository::create_awaiting( $order->get_id(), $order->get_order_number(), self::rate_columns( $rate, $rate_id, $order ) );
		}
		if ( ! ShipmentRepository::claim_for_booking( $order->get_id() ) ) {
			return self::result( false, 'duplicate', __( 'Another booking request holds this order. No request was sent.', 'parcelflow-for-woocommerce' ) );
		}
		ShipmentRepository::update(
			$order->get_id(),
			array_merge(
				self::rate_columns( $rate, $rate_id, $order ),
				array( 'request_json' => wp_json_encode( $payload ) )
			)
		);
		if ( $override['values'] ) {
			$order->add_order_note(
				sprintf(
					/* translators: 1: weight kg, 2: length, 3: width, 4: height */
					__( 'ParcelFlow booking uses an administrator package override: %1$s kg, %2$s x %3$s x %4$s cm.', 'parcelflow-for-woocommerce' ),
					$override['values']['weight'],
					$override['values']['length'],
					$override['values']['width'],
					$override['values']['height']
				)
			);
		}
		Logger::log(
			array(
				'event_type' => 'booking',
				'order_id'   => $order->get_id(),
				'message'    => sprintf( /* translators: %s: manual/automatic */ __( 'Booking attempt started (%s).', 'parcelflow-for-woocommerce' ), $source ),
			)
		);

		// ---- Send exactly once ----
		$client = new Client();
		$res    = $client->create_shipment( $payload, array( 'order_id' => $order->get_id() ) );

		if ( $res->success ) {
			$d      = $res->data();
			$d      = is_array( $d ) ? $d : array();
			$number = isset( $d['shipment_number'] ) ? sanitize_text_field( (string) $d['shipment_number'] ) : '';
			$track  = isset( $d['tracking_number'] ) ? sanitize_text_field( (string) $d['tracking_number'] ) : '';
			$sid    = isset( $d['shipment_id'] ) ? sanitize_text_field( (string) $d['shipment_id'] ) : '';

			if ( '' === $number && '' === $sid && '' === $track ) {
				// Success flag but nothing to reference: a shipment may exist.
				return self::uncertain( $order, __( 'ParcelFlow reported success but returned no shipment reference.', 'parcelflow-for-woocommerce' ) );
			}
			$api_status = isset( $d['status'] ) ? sanitize_text_field( (string) $d['status'] ) : '';
			$fields     = array(
				'state'           => Status::STATE_BOOKED,
				'pf_shipment_id'  => $sid,
				'shipment_number' => $number,
				'tracking_number' => $track,
				'label_url'       => isset( $d['label_url'] ) ? Format::safe_url( $d['label_url'] ) : '',
				'status'          => $api_status,
				'total_fee'       => isset( $d['total_fee'] ) ? Format::money( $d['total_fee'] ) : null,
				'error_message'   => '',
				'booked_at'       => gmdate( 'Y-m-d H:i:s' ),
			);
			ShipmentRepository::update( $order->get_id(), $fields );
			OrderIntegration::mirror_to_order( $order, ShipmentRepository::find_by_order( $order->get_id() ) );
			$order->add_order_note(
				sprintf( /* translators: 1: shipment number, 2: tracking number */ __( 'ParcelFlow shipment booked. Shipment %1$s, tracking %2$s.', 'parcelflow-for-woocommerce' ), $number, $track )
			);
			return self::result( true, 'booked', __( 'Shipment booked with ParcelFlow.', 'parcelflow-for-woocommerce' ) );
		}

		if ( $res->is_safe_to_retry() ) {
			ShipmentRepository::update(
				$order->get_id(),
				array(
					'state'         => Status::STATE_FAILED,
					'error_message' => $res->message,
				)
			);
			$order->add_order_note( sprintf( /* translators: %s: error */ __( 'ParcelFlow booking failed: %s', 'parcelflow-for-woocommerce' ), $res->message ) );
			self::notify( $order, $res->message, false );
			return self::result( false, 'failed', $res->message );
		}

		return self::uncertain( $order, $res->message );
	}

	/**
	 * Validate an optional administrator package override.
	 *
	 * Either all four values (weight kg; length, width, height cm; each > 0) or none.
	 *
	 * @param array $opts Booking options; optional key 'package'.
	 * @return array{values:array|null,errors:string[]}
	 */
	public static function package_override( array $opts ) {
		if ( empty( $opts['package'] ) || ! is_array( $opts['package'] ) ) {
			return array(
				'values' => null,
				'errors' => array(),
			);
		}
		$keys   = array( 'weight', 'length', 'width', 'height' );
		$given  = array();
		$values = array();
		foreach ( $keys as $k ) {
			$v = isset( $opts['package'][ $k ] ) ? trim( (string) $opts['package'][ $k ] ) : '';
			if ( '' !== $v ) {
				$given[] = $k;
			}
			$values[ $k ] = $v;
		}
		if ( ! $given ) {
			return array(
				'values' => null,
				'errors' => array(),
			);
		}
		foreach ( $keys as $k ) {
			if ( '' === $values[ $k ] || ! is_numeric( $values[ $k ] ) || (float) $values[ $k ] <= 0 || (float) $values[ $k ] > 100000 ) {
				return array(
					'values' => null,
					'errors' => array( __( 'Package override: enter weight, length, width and height as positive numbers, or leave all four blank.', 'parcelflow-for-woocommerce' ) ),
				);
			}
			$values[ $k ] = round( (float) $values[ $k ], 3 );
		}
		return array(
			'values' => $values,
			'errors' => array(),
		);
	}

	/* ------------------------------ Reconcile ---------------------------- */

	/**
	 * Operator links an existing ParcelFlow shipment (found via history) to the order.
	 */
	public static function link_existing( $order_id, $shipment_number, $pf_shipment_id = '' ) {
		$order = wc_get_order( $order_id );
		$row   = ShipmentRepository::find_by_order( $order_id );
		if ( ! $order || ! $row || Status::STATE_UNCERTAIN !== $row['state'] ) {
			return self::result( false, 'blocked', __( 'Only orders with an uncertain booking can be linked.', 'parcelflow-for-woocommerce' ) );
		}
		$number = sanitize_text_field( $shipment_number );
		if ( '' === $number ) {
			return self::result( false, 'blocked', __( 'A shipment number is required.', 'parcelflow-for-woocommerce' ) );
		}
		ShipmentRepository::update(
			$order_id,
			array(
				'state'           => Status::STATE_BOOKED,
				'shipment_number' => $number,
				'pf_shipment_id'  => sanitize_text_field( $pf_shipment_id ),
				'error_message'   => '',
				'booked_at'       => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		OrderIntegration::mirror_to_order( $order, ShipmentRepository::find_by_order( $order_id ) );
		$order->add_order_note( sprintf( /* translators: %s: shipment number */ __( 'ParcelFlow shipment %s was linked manually during reconciliation.', 'parcelflow-for-woocommerce' ), $number ) );
		Logger::log(
			array(
				'event_type'      => 'booking',
				'order_id'        => $order_id,
				'shipment_number' => $number,
				'message'         => 'Existing shipment linked by administrator.',
			)
		);
		return self::result( true, 'booked', __( 'Shipment linked. Tracking details will update on the next refresh.', 'parcelflow-for-woocommerce' ) );
	}

	/**
	 * Operator confirms (outside the plugin) that ParcelFlow did NOT create a shipment, so a
	 * new booking attempt is permitted.
	 */
	public static function confirm_not_created( $order_id ) {
		$row = ShipmentRepository::find_by_order( $order_id );
		if ( ! $row || Status::STATE_UNCERTAIN !== $row['state'] ) {
			return self::result( false, 'blocked', __( 'Only uncertain bookings can be cleared.', 'parcelflow-for-woocommerce' ) );
		}
		ShipmentRepository::update(
			$order_id,
			array(
				'state'         => Status::STATE_FAILED,
				'error_message' => __( 'Administrator confirmed that no shipment was created; retry permitted.', 'parcelflow-for-woocommerce' ),
			)
		);
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->add_order_note( __( 'Administrator confirmed no ParcelFlow shipment exists for this order; booking can be retried.', 'parcelflow-for-woocommerce' ) );
		}
		Logger::log(
			array(
				'event_type' => 'booking',
				'order_id'   => $order_id,
				'message'    => 'Administrator cleared uncertain booking; retry permitted.',
			)
		);
		return self::result( true, 'failed', __( 'Booking unlocked. You can retry.', 'parcelflow-for-woocommerce' ) );
	}

	/* ------------------------------- Helpers ----------------------------- */

	private static function rate_columns( array $rate, $rate_id, \WC_Order $order ) {
		return array(
			'carrier_id'   => $rate['carrier_id'],
			'carrier_name' => $rate['carrier_name'],
			'rate_id'      => (string) $rate_id,
			'shipping_fee' => null === $rate['selling_price'] ? null : (float) $rate['selling_price'],
			'currency'     => Settings::rate_currency(),
		);
	}

	private static function uncertain( \WC_Order $order, $message ) {
		$msg = $message . ' ' . __( 'The shipment may or may not have been created; it will not be retried automatically.', 'parcelflow-for-woocommerce' );
		ShipmentRepository::update(
			$order->get_id(),
			array(
				'state'         => Status::STATE_UNCERTAIN,
				'error_message' => $msg,
			)
		);
		$order->add_order_note( __( 'ParcelFlow booking outcome is UNCERTAIN. Do not rebook until reconciled (ParcelFlow → Shipments).', 'parcelflow-for-woocommerce' ) );
		self::notify( $order, $msg, true );
		return self::result( false, 'uncertain', $msg );
	}

	private static function blocked( \WC_Order $order, array $messages ) {
		$msg = implode( ' ', $messages );
		Logger::log(
			array(
				'event_type' => 'booking',
				'success'    => false,
				'order_id'   => $order->get_id(),
				'message'    => 'Booking blocked: ' . $msg,
			)
		);
		return self::result( false, 'blocked', $msg );
	}

	private static function result( $ok, $code, $message ) {
		return array(
			'ok'      => $ok,
			'code'    => $code,
			'message' => $message,
		);
	}

	private static function notify( \WC_Order $order, $message, $uncertain ) {
		if ( ! Settings::is( 'notify_on_failure' ) ) {
			return;
		}
		$subject = sprintf(
			/* translators: 1: site name, 2: order number */
			$uncertain ? __( '[%1$s] ParcelFlow booking UNCERTAIN for order #%2$s', 'parcelflow-for-woocommerce' ) : __( '[%1$s] ParcelFlow booking failed for order #%2$s', 'parcelflow-for-woocommerce' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$order->get_order_number()
		);
		$body = $message . "\n\n" . admin_url( 'admin.php?page=parcelflow-shipments' );
		wp_mail( Settings::notify_email(), $subject, $body );
	}
}

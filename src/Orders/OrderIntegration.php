<?php
/**
 * WooCommerce order integration: payment eligibility, order metadata, admin order panel,
 * and the customer-facing shipment section.
 *
 * Uses WooCommerce CRUD only (HPOS compatible by construction).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Orders;

use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Booking\BookingService;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;
use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Shipping\ShippingMethod;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;
use ParcelFlow\WooCommerce\Tracking\TrackingPage;

defined( 'ABSPATH' ) || exit;

final class OrderIntegration {

	const BOOK_HOOK = 'parcelflow_wc_book_order';

	public function register() {
		// Payment => eligibility (never at rate time, never for unpaid orders).
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_paid' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid' ) );
		add_action( self::BOOK_HOOK, array( $this, 'run_scheduled_booking' ) );

		// Admin order screen (HPOS and legacy).
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

		// Customer views.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_customer_section' ) );
	}

	/* ------------------------------ Helpers ------------------------------ */

	/**
	 * @return \WC_Order_Item_Shipping|null
	 */
	public static function shipping_item( \WC_Order $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( ShippingMethod::METHOD_ID === $item->get_method_id() ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Selected rate context retained from checkout (copied from the WC_Shipping_Rate meta).
	 */
	public static function rate_from_item( $item ) {
		$get   = static function ( $k ) use ( $item ) {
			return $item ? (string) $item->get_meta( '_parcelflow_' . $k ) : '';
		};
		$price = $get( 'selling_price' );
		return array(
			'carrier_id'    => $get( 'carrier_id' ),
			'carrier_name'  => $get( 'carrier_name' ),
			'delivery_time' => $get( 'delivery_time' ),
			'selling_price' => '' === $price ? null : (float) $price,
			'pickup_fee'    => (float) $get( 'pickup_fee' ),
			'rate_id'       => $get( 'rate_id' ),
			'shipment_type' => $get( 'shipment_type' ),
			'quoted_at'     => (int) $get( 'quoted_at' ),
		);
	}

	/**
	 * Mirror shipment references onto the order (order meta, via CRUD).
	 */
	public static function mirror_to_order( \WC_Order $order, $row ) {
		if ( ! $row ) {
			return;
		}
		$map = array(
			'_parcelflow_shipment_id'     => $row['pf_shipment_id'],
			'_parcelflow_shipment_number' => $row['shipment_number'],
			'_parcelflow_tracking_number' => $row['tracking_number'],
			'_parcelflow_label_url'       => $row['label_url'],
			'_parcelflow_status'          => $row['status'],
			'_parcelflow_total_fee'       => $row['total_fee'],
			'_parcelflow_carrier_id'      => $row['carrier_id'],
			'_parcelflow_carrier_name'    => $row['carrier_name'],
			'_parcelflow_rate_id'         => $row['rate_id'],
			'_parcelflow_booking_state'   => $row['state'],
		);
		foreach ( $map as $k => $v ) {
			$order->update_meta_data( $k, (string) $v );
		}
		$order->save_meta_data();
	}

	/* ---------------------------- Payment hooks -------------------------- */

	public function on_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || ! Settings::is( 'enabled' ) || ! self::shipping_item( $order ) ) {
			return;
		}
		$rate = self::rate_from_item( self::shipping_item( $order ) );

		$created = ShipmentRepository::create_awaiting(
			$order->get_id(),
			$order->get_order_number(),
			array(
				'carrier_id'   => $rate['carrier_id'],
				'carrier_name' => $rate['carrier_name'],
				'rate_id'      => $rate['rate_id'],
				'shipping_fee' => $rate['selling_price'],
				'currency'     => $order->get_currency(),
			)
		);
		if ( $created ) {
			$order->update_meta_data( '_parcelflow_booking_state', Status::STATE_AWAITING );
			$order->update_meta_data( '_parcelflow_carrier_id', $rate['carrier_id'] );
			$order->update_meta_data( '_parcelflow_carrier_name', $rate['carrier_name'] );
			$order->save_meta_data();
			$order->add_order_note( __( 'Order is eligible for ParcelFlow shipment booking.', 'parcelflow-for-woocommerce' ) );
		}

		if ( 'automatic' === Settings::get( 'booking_mode' ) && function_exists( 'as_schedule_single_action' ) ) {
			$row = ShipmentRepository::find_by_order( $order->get_id() );
			if ( $row && Status::STATE_AWAITING === $row['state'] && ! as_next_scheduled_action( self::BOOK_HOOK, array( $order->get_id() ), 'parcelflow' ) ) {
				as_schedule_single_action( time() + 30, self::BOOK_HOOK, array( $order->get_id() ), 'parcelflow' );
			}
		}
	}

	/**
	 * Action Scheduler worker. Only ever attempts a row still in the awaiting state;
	 * BookingService repeats all safety checks.
	 */
	public function run_scheduled_booking( $order_id ) {
		$row = ShipmentRepository::find_by_order( (int) $order_id );
		if ( ! $row || Status::STATE_AWAITING !== $row['state'] || 'automatic' !== Settings::get( 'booking_mode' ) ) {
			return;
		}
		BookingService::book( (int) $order_id, array( 'source' => 'automatic' ) );
	}

	/* ------------------------------ Admin box ---------------------------- */

	public function add_meta_box( $post_type, $post_or_order ) {
		if ( ! function_exists( 'wc_get_page_screen_id' ) ) {
			return;
		}
		$screen = wc_get_page_screen_id( 'shop-order' );
		if ( $post_type !== $screen ) {
			return;
		}
		add_meta_box(
			'parcelflow-order-shipment',
			__( 'ParcelFlow Shipment', 'parcelflow-for-woocommerce' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * @param \WC_Order|\WP_Post $post_or_order Order object (HPOS) or post (legacy).
	 */
	public function render_meta_box( $post_or_order ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$item = self::shipping_item( $order );
		View::render(
			'order-shipment-panel',
			array(
				'order'    => $order,
				'has_item' => (bool) $item,
				'rate'     => self::rate_from_item( $item ),
				'row'      => ShipmentRepository::find_by_order( $order->get_id() ),
				'errors'   => BookingService::eligibility_errors( $order ),
			)
		);
	}

	/* ---------------------------- Customer view -------------------------- */

	/**
	 * May the current visitor see this order's shipment? WooCommerce already guards the
	 * view-order endpoint; we verify ownership again (defence in depth) and for the
	 * order-received page require the order key for guests.
	 */
	public static function current_user_can_view( \WC_Order $order ) {
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				return true;
			}
			return (int) $order->get_customer_id() === get_current_user_id() && $order->get_customer_id() > 0;
		}
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '' !== $key && $order->key_is_valid( $key );
	}

	public function render_customer_section( $order ) {
		if ( ! $order instanceof \WC_Order || ! self::shipping_item( $order ) || ! self::current_user_can_view( $order ) ) {
			return;
		}
		$row = ShipmentRepository::find_by_order( $order->get_id() );
		View::render_frontend(
			'order-shipment-info',
			array(
				'order'     => $order,
				'row'       => $row,
				'booked'    => $row && Status::STATE_BOOKED === $row['state'],
				'track_url' => ( $row && '' !== $row['shipment_number'] ) ? TrackingPage::url_for( $row['shipment_number'] ) : '',
			)
		);
	}
}

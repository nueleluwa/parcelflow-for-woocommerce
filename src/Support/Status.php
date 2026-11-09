<?php
/**
 * Maps ParcelFlow status strings onto the dashboard groups.
 *
 * Source of truth: the ParcelFlow Shipments API documentation. It names only these shipment
 * statuses: "Created" (create-shipment response), and "Pending", "In Transit", "Delivered"
 * (get-shipment-history filter, which then says "etc."). Only those are recognised. Any other
 * value is displayed exactly as returned and counted under "Processing"; no other status
 * text is interpreted or invented. The "Failed" group reflects this plugin's own booking
 * states (failed / uncertain), never an API status.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

final class Status {

	const STATE_AWAITING    = 'awaiting';    // Paid, eligible, not yet booked.
	const STATE_IN_PROGRESS = 'in_progress'; // Booking request in flight (lock).
	const STATE_BOOKED      = 'booked';
	const STATE_FAILED      = 'failed';      // Definitive API rejection; safe to retry.
	const STATE_UNCERTAIN   = 'uncertain';   // Outcome unknown; needs reconciliation.

	public static function groups() {
		return array(
			'pending'    => __( 'Pending', 'parcelflow-for-woocommerce' ),
			'processing' => __( 'Processing', 'parcelflow-for-woocommerce' ),
			'in_transit' => __( 'In transit', 'parcelflow-for-woocommerce' ),
			'delivered'  => __( 'Delivered', 'parcelflow-for-woocommerce' ),
			'failed'     => __( 'Failed', 'parcelflow-for-woocommerce' ),
		);
	}

	public static function state_label( $state ) {
		$labels = array(
			self::STATE_AWAITING    => __( 'Awaiting booking', 'parcelflow-for-woocommerce' ),
			self::STATE_IN_PROGRESS => __( 'Booking in progress', 'parcelflow-for-woocommerce' ),
			self::STATE_BOOKED      => __( 'Booked', 'parcelflow-for-woocommerce' ),
			self::STATE_FAILED      => __( 'Booking failed', 'parcelflow-for-woocommerce' ),
			self::STATE_UNCERTAIN   => __( 'Outcome uncertain – reconcile', 'parcelflow-for-woocommerce' ),
		);
		return isset( $labels[ $state ] ) ? $labels[ $state ] : $state;
	}

	/**
	 * @param string $state      Plugin booking state.
	 * @param string $api_status Raw status string from ParcelFlow.
	 */
	public static function group( $state, $api_status ) {
		if ( self::STATE_AWAITING === $state ) {
			return 'pending';
		}
		if ( self::STATE_IN_PROGRESS === $state ) {
			return 'processing';
		}
		if ( self::STATE_FAILED === $state || self::STATE_UNCERTAIN === $state ) {
			return 'failed';
		}
		$s = strtolower( trim( (string) $api_status ) );
		if ( 'delivered' === $s ) {
			return 'delivered';
		}
		if ( 'in transit' === $s ) {
			return 'in_transit';
		}
		if ( 'pending' === $s ) {
			return 'pending';
		}
		return 'processing';
	}

	/**
	 * Only "Delivered" is known to be final; every other status keeps being refreshed.
	 */
	public static function is_final( $state, $api_status ) {
		return self::STATE_BOOKED === $state && 'delivered' === self::group( $state, $api_status );
	}
}

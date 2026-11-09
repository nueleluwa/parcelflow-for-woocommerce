<?php
/**
 * Maps ParcelFlow status strings onto the dashboard groups.
 *
 * The API documents only a few status strings ("Created", "Pending", "In Transit",
 * "Delivered" ...) and says the list is open-ended ("etc."). The raw API status is
 * always displayed and stored unchanged; the grouping below is used ONLY for the
 * dashboard counters/filters and is deliberately keyword-based and conservative.
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
		foreach ( array( 'cancel', 'fail', 'return', 'exception', 'reject' ) as $w ) {
			if ( false !== strpos( $s, $w ) ) {
				return 'failed';
			}
		}
		if ( 'delivered' === $s ) {
			return 'delivered';
		}
		foreach ( array( 'transit', 'picked up', 'going to deliver', 'out for', 'dispatch', 'shipped' ) as $w ) {
			if ( false !== strpos( $s, $w ) ) {
				return 'in_transit';
			}
		}
		return 'processing';
	}

	/**
	 * Final statuses need no more tracking refreshes.
	 */
	public static function is_final( $state, $api_status ) {
		return ( self::STATE_BOOKED === $state && 'delivered' === self::group( $state, $api_status ) )
			|| false !== strpos( strtolower( (string) $api_status ), 'cancel' );
	}
}

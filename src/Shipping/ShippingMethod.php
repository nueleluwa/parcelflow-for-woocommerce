<?php
/**
 * ParcelFlow WooCommerce shipping method (supports shipping zones).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Shipping;

use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Logger;

defined( 'ABSPATH' ) || exit;

class ShippingMethod extends \WC_Shipping_Method {

	const METHOD_ID = 'parcelflow';

	/** Customer-facing reason when no rates are offered (read by the registrar). */
	public static $notice = '';

	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'ParcelFlow', 'parcelflow-for-woocommerce' );
		$this->method_description = __( 'Live carrier rates from ParcelFlow. Configure credentials and sender details under ParcelFlow → Settings.', 'parcelflow-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
		$this->init();
	}

	private function init() {
		$this->instance_form_fields = array(
			'title'      => array(
				'title'       => __( 'Method title', 'parcelflow-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown in the zone list. Customers see the carrier names returned by ParcelFlow.', 'parcelflow-for-woocommerce' ),
				'default'     => __( 'ParcelFlow', 'parcelflow-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'tax_status' => array(
				'title'   => __( 'Tax status', 'parcelflow-for-woocommerce' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'default' => 'taxable',
				'options' => array(
					'taxable' => __( 'Taxable', 'parcelflow-for-woocommerce' ),
					'none'    => _x( 'None', 'Tax status', 'parcelflow-for-woocommerce' ),
				),
			),
		);
		$this->init_instance_settings();
		$this->title      = $this->get_option( 'title' );
		$this->tax_status = $this->get_option( 'tax_status' );
		add_action( 'woocommerce_update_options_shipping_' . $this->id . '_' . $this->instance_id, array( $this, 'process_admin_options' ) );
	}

	public function is_available( $package ) {
		$available = Settings::is_configured() && Settings::is( 'enable_checkout_rates' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- intentionally mirrors WooCommerce's shipping-method filter convention.
		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this );
	}

	/**
	 * @param array $package WooCommerce package.
	 */
	public function calculate_shipping( $package = array() ) {
		self::$notice = '';

		if ( empty( $package['destination']['country'] ) ) {
			self::$notice = __( 'Enter your delivery address to see ParcelFlow shipping options.', 'parcelflow-for-woocommerce' );
			return;
		}

		// Rates are in Settings::rate_currency(); never silently mix currencies.
		if ( Settings::rate_currency() !== get_woocommerce_currency() ) {
			self::$notice = __( 'Shipping rates are currently unavailable.', 'parcelflow-for-woocommerce' );
			$this->log_blocked_once( 'currency', __( 'ParcelFlow price currency differs from the store currency; no conversion is performed so no rates were offered.', 'parcelflow-for-woocommerce' ) );
			return;
		}

		$receiver = PayloadMapper::receiver_from_cart_package( $package );
		if ( $receiver['errors'] ) {
			self::$notice = __( 'Complete your name, address, phone and email to see ParcelFlow shipping options.', 'parcelflow-for-woocommerce' );
			return;
		}

		$sender = PayloadMapper::sender();
		if ( $sender['errors'] ) {
			self::$notice = __( 'Shipping rates are currently unavailable.', 'parcelflow-for-woocommerce' );
			$this->log_blocked_once( 'sender', implode( ' ', $sender['errors'] ) );
			return;
		}

		$built  = PackageBuilder::from_cart_package( $package );
		$errs   = $built['errors'];
		$carton = array();
		if ( ! $errs ) {
			$carton = PackageBuilder::carton( $built['lines'] );
			$errs   = $carton['errors'];
		}
		if ( $errs ) {
			self::$notice = __( 'Shipping cannot be calculated online for these items. Please contact us to arrange delivery.', 'parcelflow-for-woocommerce' );
			$this->log_blocked_once( 'package', implode( ' ', $errs ) );
			return;
		}

		$type = PayloadMapper::shipment_type( $sender, $receiver );
		if ( '' === $type ) {
			self::$notice = __( 'Shipping rates are currently unavailable.', 'parcelflow-for-woocommerce' );
			$this->log_blocked_once( 'type', __( 'Shipment type is not set. Choose one under ParcelFlow > Settings > Shipping & Booking.', 'parcelflow-for-woocommerce' ) );
			return;
		}

		$payload = PayloadMapper::calculate_payload( $sender, $receiver, $built['lines'], $carton, $type );
		$quote   = RateService::quote( $payload );

		if ( '' !== $quote['error'] ) {
			// Honest failure: no fabricated or fallback price.
			self::$notice = __( 'We could not retrieve ParcelFlow shipping rates right now. Please try again shortly.', 'parcelflow-for-woocommerce' );
			return;
		}
		if ( ! $quote['rates'] ) {
			self::$notice = __( 'No ParcelFlow carriers are available for this destination and package.', 'parcelflow-for-woocommerce' );
			return;
		}

		$request_hash = md5( wp_json_encode( $payload['package'] ) . wp_json_encode( $payload['booking'] ) );
		foreach ( $quote['rates'] as $r ) {
			$label = $r['carrier_name'] . ( '' !== $r['delivery_time'] ? ' (' . $r['delivery_time'] . ')' : '' );
			$this->add_rate(
				array(
					'id'        => $this->get_rate_id( preg_replace( '/[^A-Za-z0-9_\-]/', '', $r['carrier_id'] ) ),
					'label'     => $label,
					'cost'      => $r['selling_price'],
					'package'   => $package,
					'meta_data' => array(
						// Underscore keys are hidden in admin item meta but copied to the order item.
						'_parcelflow_carrier_id'      => $r['carrier_id'],
						'_parcelflow_carrier_name'    => $r['carrier_name'],
						'_parcelflow_carrier_logo'    => $r['carrier_logo'],
						'_parcelflow_delivery_time'   => $r['delivery_time'],
						'_parcelflow_additional_info' => $r['additional_info'],
						'_parcelflow_selling_price'   => $r['selling_price'],
						'_parcelflow_pickup_fee'      => $r['pickup_fee'],
						'_parcelflow_rate_id'         => $r['rate_id'],
						'_parcelflow_shipment_type'   => $type,
						'_parcelflow_request_hash'    => $request_hash,
						'_parcelflow_quoted_at'       => time(),
					),
				)
			);
		}
	}

	/**
	 * Log a configuration/validation block at most once per 10 minutes per reason so busy
	 * checkouts do not flood the log.
	 */
	private function log_blocked_once( $reason, $message ) {
		$key = 'pf_blocked_' . md5( $reason . $message );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
		Logger::log(
			array(
				'event_type' => 'rate_request',
				'success'    => false,
				'message'    => __( 'Rates not requested: ', 'parcelflow-for-woocommerce' ) . $message,
			)
		);
	}
}

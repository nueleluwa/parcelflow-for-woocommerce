<?php
/**
 * Settings storage, schema and sanitisation.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

final class Settings {

	const OPTION       = 'parcelflow_wc_settings';
	const TOKEN_OPTION = 'parcelflow_wc_access_token';
	// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- server-side API endpoint documented by ParcelFlow, not offloaded JS/CSS/images.
	const STAGING_URL   = 'https://parcelflow-admin-staging-f10dbf53d922.herokuapp.com/';
	const SEEN_CARRIERS = 'parcelflow_wc_seen_carriers';

	/** @var array|null */
	private static $cache = null;

	/**
	 * Settings tabs.
	 *
	 * @return array<string,string>
	 */
	public static function tabs() {
		return array(
			'general'  => __( 'General', 'parcelflow-for-woocommerce' ),
			'sender'   => __( 'Sender / Warehouse', 'parcelflow-for-woocommerce' ),
			'shipping' => __( 'Shipping & Booking', 'parcelflow-for-woocommerce' ),
			'carriers' => __( 'Carriers / Services', 'parcelflow-for-woocommerce' ),
			'advanced' => __( 'Advanced', 'parcelflow-for-woocommerce' ),
		);
	}

	/**
	 * Field schema. Drives defaults, rendering and sanitisation.
	 *
	 * @return array<string,array>
	 */
	public static function schema() {
		$yes_no = array(
			'yes' => __( 'Yes', 'parcelflow-for-woocommerce' ),
			'no'  => __( 'No', 'parcelflow-for-woocommerce' ),
		);
		unset( $yes_no );

		return array(
			// General.
			'enabled'                  => array(
				'tab'     => 'general',
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Enable ParcelFlow', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Master switch. When off, no ParcelFlow rates are requested and no shipments are booked.', 'parcelflow-for-woocommerce' ),
			),
			'environment'              => array(
				'tab'     => 'general',
				'type'    => 'select',
				'default' => 'staging',
				'label'   => __( 'API environment', 'parcelflow-for-woocommerce' ),
				'options' => array(
					'staging'    => __( 'Staging', 'parcelflow-for-woocommerce' ),
					'production' => __( 'Production', 'parcelflow-for-woocommerce' ),
				),
				'desc'    => __( 'Label only. The API base URL below is what is actually used.', 'parcelflow-for-woocommerce' ),
			),
			'base_url'                 => array(
				'tab'     => 'general',
				'type'    => 'url',
				'default' => self::STAGING_URL,
				'label'   => __( 'API base URL', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'The supplied documentation only provides the staging URL. A production URL has not been provided yet; obtain it from ParcelFlow before launch.', 'parcelflow-for-woocommerce' ),
			),
			'access_token'             => array(
				'tab'   => 'general',
				'type'  => 'token',
				'label' => __( 'Access token', 'parcelflow-for-woocommerce' ),
			),
			'enable_checkout_rates'    => array(
				'tab'     => 'general',
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Enable checkout rates', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Request live ParcelFlow rates at cart and checkout.', 'parcelflow-for-woocommerce' ),
			),
			'show_carrier_logos'       => array(
				'tab'     => 'general',
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Display carrier logos', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Classic checkout only. Logo URLs are taken from the API response.', 'parcelflow-for-woocommerce' ),
			),
			'rate_currency'            => array(
				'tab'         => 'general',
				'type'        => 'currency',
				'default'     => '',
				'label'       => __( 'Currency of ParcelFlow prices', 'parcelflow-for-woocommerce' ),
				'placeholder' => 'NGN',
				'desc'        => __( 'The API response does not state the currency of selling_price. Leave blank to assume it equals the store currency. If you enter a code different from the store currency, rates are NOT shown at checkout (no currency conversion is performed).', 'parcelflow-for-woocommerce' ),
			),

			// Sender.
			'sender_name'              => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Business name', 'parcelflow-for-woocommerce' ),
			),
			'sender_address_1'         => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Address line 1', 'parcelflow-for-woocommerce' ),
			),
			'sender_address_2'         => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Address line 2', 'parcelflow-for-woocommerce' ),
			),
			'sender_city'              => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'City', 'parcelflow-for-woocommerce' ),
			),
			'sender_state'             => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'State / Province', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'State name or WooCommerce state code (e.g. Lagos or LA).', 'parcelflow-for-woocommerce' ),
			),
			'sender_country'           => array(
				'tab'     => 'sender',
				'type'    => 'country',
				'default' => '',
				'label'   => __( 'Country', 'parcelflow-for-woocommerce' ),
			),
			'sender_postcode'          => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Postcode', 'parcelflow-for-woocommerce' ),
			),
			'sender_phone'             => array(
				'tab'     => 'sender',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Phone', 'parcelflow-for-woocommerce' ),
			),
			'sender_email'             => array(
				'tab'     => 'sender',
				'type'    => 'email',
				'default' => '',
				'label'   => __( 'Email', 'parcelflow-for-woocommerce' ),
			),

			// Shipping & booking.
			'shipment_type_mode'       => array(
				'tab'     => 'shipping',
				'type'    => 'select',
				'default' => '',
				'label'   => __( 'Default shipment type', 'parcelflow-for-woocommerce' ),
				'options' => array(
					''              => __( '— Select (required) —', 'parcelflow-for-woocommerce' ),
					'Local'         => 'Local',
					'Interstate'    => 'Interstate',
					'International' => 'International',
					'Import'        => 'Import',
					'auto'          => __( 'Auto-detect (experimental, NOT defined by the API documentation)', 'parcelflow-for-woocommerce' ),
				),
				'desc'    => __( 'The API documentation lists these four values but does not say how to choose between them, so you must choose. No rates are requested until a type is selected. Auto-detect is an unconfirmed guess made by this plugin (different country = International; same country and state = Local; same country, different state = Interstate) and has not been confirmed by ParcelFlow.', 'parcelflow-for-woocommerce' ),
			),
			'package_type'             => array(
				'tab'     => 'shipping',
				'type'    => 'select',
				'default' => 'Parcel',
				'label'   => __( 'Default package type', 'parcelflow-for-woocommerce' ),
				'options' => array(
					'Parcel'   => 'Parcel',
					'Document' => 'Document',
				),
			),
			'booking_mode'             => array(
				'tab'     => 'shipping',
				'type'    => 'select',
				'default' => 'manual',
				'label'   => __( 'Booking mode', 'parcelflow-for-woocommerce' ),
				'options' => array(
					'manual'    => __( 'Manual – administrator books each paid order', 'parcelflow-for-woocommerce' ),
					'automatic' => __( 'Automatic – book in the background after payment', 'parcelflow-for-woocommerce' ),
				),
				'desc'    => __( 'Automatic booking is intended for future use: the Create Shipment endpoint requires a rate_id and a ParcelFlow wallet payment, neither of which is fully confirmed. Keep manual until ParcelFlow confirms both.', 'parcelflow-for-woocommerce' ),
			),
			'payment_method'           => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => 'Wallet',
				'label'   => __( 'Booking payment method', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Sent as booking[payment_method]. The documentation states it "must be Wallet for customers". How the wallet is funded is unconfirmed.', 'parcelflow-for-woocommerce' ),
			),
			'processing_station_id'    => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Processing station ID', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Optional. Sent as booking[processing_station_id].', 'parcelflow-for-woocommerce' ),
			),
			'service_station_id'       => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Service station ID', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Optional. Sent at booking only.', 'parcelflow-for-woocommerce' ),
			),
			'request_pickup'           => array(
				'tab'     => 'shipping',
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Request pickup', 'parcelflow-for-woocommerce' ),
			),
			'pickup_type_id'           => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Pickup type ID', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Required when pickup is requested. The catalogue of pickup IDs is not documented; use the value supplied by ParcelFlow.', 'parcelflow-for-woocommerce' ),
			),
			'request_insurance'        => array(
				'tab'     => 'shipping',
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Request insurance', 'parcelflow-for-woocommerce' ),
			),
			'insurance_type'           => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Insurance type', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Required when insurance is requested (example in docs: "Full Coverage").', 'parcelflow-for-woocommerce' ),
			),
			'insurance_fee'            => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => '',
				'label'   => __( 'Insurance fee', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Fixed fee submitted at booking. How ParcelFlow calculates insurance is not documented.', 'parcelflow-for-woocommerce' ),
			),
			'rate_cache_minutes'       => array(
				'tab'     => 'shipping',
				'type'    => 'int',
				'default' => 10,
				'min'     => 0,
				'max'     => 120,
				'label'   => __( 'Rate cache duration (minutes)', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Rates are cached per exact payload (addresses, contents, weights, options). 0 disables caching. Rate validity is not documented by ParcelFlow, so keep this short.', 'parcelflow-for-woocommerce' ),
			),
			'carton_strategy'          => array(
				'tab'     => 'shipping',
				'type'    => 'select',
				'default' => 'fixed',
				'label'   => __( 'Package dimension strategy', 'parcelflow-for-woocommerce' ),
				'options' => array(
					'fixed'   => __( 'Fixed carton dimensions (below), recommended', 'parcelflow-for-woocommerce' ),
					'stacked' => __( 'Estimate from products (experimental, NOT defined by the API documentation)', 'parcelflow-for-woocommerce' ),
				),
				'desc'    => __( 'The API requires package length, width and height but does not say how to derive them from a cart, so enter the carton you actually ship in. The whole cart is sent as ONE package. The experimental estimate uses the largest length and width with heights stacked; it is an unconfirmed heuristic of this plugin, not defined by ParcelFlow. Until dimensions are available no rates are requested.', 'parcelflow-for-woocommerce' ),
			),
			'carton_length'            => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => '',
				'label'   => __( 'Fixed carton length (cm)', 'parcelflow-for-woocommerce' ),
			),
			'carton_width'             => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => '',
				'label'   => __( 'Fixed carton width (cm)', 'parcelflow-for-woocommerce' ),
			),
			'carton_height'            => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => '',
				'label'   => __( 'Fixed carton height (cm)', 'parcelflow-for-woocommerce' ),
			),
			'packaging_weight'         => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => 0,
				'label'   => __( 'Packaging weight (kg)', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Added to the total weight of the products.', 'parcelflow-for-woocommerce' ),
			),
			'fallback_item_weight'     => array(
				'tab'     => 'shipping',
				'type'    => 'float',
				'default' => '',
				'label'   => __( 'Fallback item weight (kg)', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Used only for products with no weight. Leave blank to block rates when a weight is missing (recommended: no fabricated values).', 'parcelflow-for-woocommerce' ),
			),
			'default_hs_code'          => array(
				'tab'     => 'shipping',
				'type'    => 'text',
				'default' => '',
				'label'   => __( 'Fallback HS code', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'hs_code is a required API field. Products without an HS code use this value; if blank, products without an HS code block rates.', 'parcelflow-for-woocommerce' ),
			),
			'declared_value_basis'     => array(
				'tab'     => 'shipping',
				'type'    => 'select',
				'default' => 'line_total',
				'label'   => __( 'Declared value basis', 'parcelflow-for-woocommerce' ),
				'options' => array(
					'line_total'    => __( 'Amount paid per unit (after discounts, excl. tax)', 'parcelflow-for-woocommerce' ),
					'product_price' => __( 'Current product price per unit', 'parcelflow-for-woocommerce' ),
				),
				'desc'    => __( 'A per-product declared value overrides this. Values are sent in the store currency; no conversion is done. The API defines value as "declared value per unit".', 'parcelflow-for-woocommerce' ),
			),

			// Carriers / services.
			'services_config'          => array(
				'tab'         => 'carriers',
				'type'        => 'textarea',
				'default'     => '',
				'label'       => __( 'Additional services', 'parcelflow-for-woocommerce' ),
				'placeholder' => '5|Gift wrapping|500',
				'desc'        => __( 'One per line: service_id|label|amount. Applied to every shipment (rate request and booking). Service IDs are provider-controlled; no catalogue endpoint is documented.', 'parcelflow-for-woocommerce' ),
			),

			// Advanced.
			'api_timeout'              => array(
				'tab'     => 'advanced',
				'type'    => 'int',
				'default' => 20,
				'min'     => 5,
				'max'     => 60,
				'label'   => __( 'API timeout (seconds)', 'parcelflow-for-woocommerce' ),
			),
			'auth_header_name'         => array(
				'tab'     => 'advanced',
				'type'    => 'header',
				'default' => 'ACCESS_TOKEN',
				'label'   => __( 'Authentication header name', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'The documentation says ACCESS_TOKEN is passed in the HTTP header and received as HTTP_ACCESS_TOKEN. Some web servers (e.g. nginx defaults) drop headers containing underscores; if authentication fails, ask ParcelFlow whether "Access-Token" is accepted.', 'parcelflow-for-woocommerce' ),
			),
			'debug_logging'            => array(
				'tab'     => 'advanced',
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Debug logging', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Also store redacted request/response bodies in the log and mirror errors to the WooCommerce logger. Personal data and the token are redacted.', 'parcelflow-for-woocommerce' ),
			),
			'tracking_refresh_hours'   => array(
				'tab'     => 'advanced',
				'type'    => 'int',
				'default' => 6,
				'min'     => 0,
				'max'     => 72,
				'label'   => __( 'Tracking refresh frequency (hours)', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Background polling of open shipments via Action Scheduler. 0 disables. ParcelFlow does not document webhooks.', 'parcelflow-for-woocommerce' ),
			),
			'notify_on_failure'        => array(
				'tab'     => 'advanced',
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Email on booking failure', 'parcelflow-for-woocommerce' ),
			),
			'notify_email'             => array(
				'tab'     => 'advanced',
				'type'    => 'email',
				'default' => '',
				'label'   => __( 'Notification email', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Defaults to the site admin email.', 'parcelflow-for-woocommerce' ),
			),
			'log_retention_days'       => array(
				'tab'     => 'advanced',
				'type'    => 'int',
				'default' => 30,
				'min'     => 1,
				'max'     => 365,
				'label'   => __( 'Log retention (days)', 'parcelflow-for-woocommerce' ),
			),
			'delete_data_on_uninstall' => array(
				'tab'     => 'advanced',
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Delete all data on uninstall', 'parcelflow-for-woocommerce' ),
				'desc'    => __( 'Removes settings, token, shipment index and logs when the plugin is deleted. Order metadata is kept.', 'parcelflow-for-woocommerce' ),
			),
		);
	}

	public static function defaults() {
		$out = array();
		foreach ( self::schema() as $key => $def ) {
			if ( 'token' === $def['type'] ) {
				continue;
			}
			$out[ $key ] = isset( $def['default'] ) ? $def['default'] : '';
		}
		$out['disabled_carriers'] = array();
		return $out;
	}

	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function is( $key ) {
		return 'yes' === self::get( $key );
	}

	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitise and save the fields belonging to one tab.
	 *
	 * @param string $tab   Tab slug.
	 * @param array  $input Unslashed raw input.
	 * @return string[] Validation warnings (non-fatal).
	 */
	public static function save_tab( $tab, array $input ) {
		$current  = self::all();
		$warnings = array();

		foreach ( self::schema() as $key => $def ) {
			if ( $def['tab'] !== $tab ) {
				continue;
			}
			if ( 'token' === $def['type'] ) {
				$new = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
				if ( '' !== $new ) {
					self::set_token( $new );
				}
				if ( ! empty( $input['clear_access_token'] ) ) {
					delete_option( self::TOKEN_OPTION );
				}
				continue;
			}
			$raw             = isset( $input[ $key ] ) ? $input[ $key ] : '';
			$current[ $key ] = self::sanitize_value( $def, $raw );
		}

		if ( 'carriers' === $tab ) {
			$seen                         = array_keys( (array) get_option( self::SEEN_CARRIERS, array() ) );
			$enabled                      = isset( $input['enabled_carriers'] ) ? array_map( 'sanitize_text_field', (array) $input['enabled_carriers'] ) : array();
			$disabled                     = array_values( array_diff( array_map( 'strval', $seen ), $enabled ) );
			$current['disabled_carriers'] = $disabled;
		}

		if ( 'shipping' === $tab ) {
			if ( 'yes' === $current['request_pickup'] && '' === $current['pickup_type_id'] ) {
				$warnings[] = __( 'Pickup is enabled but no pickup type ID is set; bookings will be blocked until it is provided.', 'parcelflow-for-woocommerce' );
			}
			if ( 'yes' === $current['request_insurance'] && ( '' === $current['insurance_type'] || '' === (string) $current['insurance_fee'] ) ) {
				$warnings[] = __( 'Insurance is enabled but the insurance type or fee is missing; bookings will be blocked until both are provided.', 'parcelflow-for-woocommerce' );
			}
			if ( 'fixed' === $current['carton_strategy'] && ( '' === (string) $current['carton_length'] || '' === (string) $current['carton_width'] || '' === (string) $current['carton_height'] ) ) {
				$warnings[] = __( 'Fixed carton strategy is selected but the carton dimensions are incomplete; rates will be blocked.', 'parcelflow-for-woocommerce' );
			}
		}

		update_option( self::OPTION, $current, false );
		self::flush_cache();
		return $warnings;
	}

	private static function sanitize_value( array $def, $raw ) {
		if ( is_array( $raw ) ) {
			$raw = '';
		}
		$raw = is_string( $raw ) ? $raw : (string) $raw;

		switch ( $def['type'] ) {
			case 'checkbox':
				return ( 'yes' === $raw || '1' === $raw || 'on' === $raw ) ? 'yes' : 'no';
			case 'select':
				return array_key_exists( $raw, $def['options'] ) ? $raw : $def['default'];
			case 'url':
				$url = esc_url_raw( trim( $raw ), array( 'https', 'http' ) );
				return '' === $url ? $def['default'] : trailingslashit( $url );
			case 'email':
				return sanitize_email( $raw );
			case 'int':
				$n = (int) $raw;
				return max( $def['min'], min( $def['max'], $n ) );
			case 'float':
				return '' === trim( $raw ) ? '' : max( 0, (float) $raw );
			case 'country':
				$code = strtoupper( sanitize_text_field( $raw ) );
				return ( function_exists( 'WC' ) && WC()->countries && isset( WC()->countries->get_countries()[ $code ] ) ) ? $code : '';
			case 'currency':
				$c = strtoupper( preg_replace( '/[^A-Za-z]/', '', $raw ) );
				return 3 === strlen( $c ) ? $c : '';
			case 'header':
				$h = preg_replace( '/[^A-Za-z0-9_-]/', '', $raw );
				return '' === $h ? $def['default'] : $h;
			case 'textarea':
				return implode( "\n", array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', $raw ) ) );
			default:
				return sanitize_text_field( $raw );
		}
	}

	/*
	---------------------------------------------------------------------
	 * Access token (encrypted at rest; never rendered or sent to the browser).
	 * ------------------------------------------------------------------ */

	public static function set_token( $token ) {
		update_option( self::TOKEN_OPTION, Crypto::encrypt( $token ), false );
	}

	/**
	 * Token from wp-config constant PARCELFLOW_WC_ACCESS_TOKEN or the encrypted option.
	 */
	public static function get_token() {
		if ( defined( 'PARCELFLOW_WC_ACCESS_TOKEN' ) && PARCELFLOW_WC_ACCESS_TOKEN ) {
			return (string) PARCELFLOW_WC_ACCESS_TOKEN;
		}
		$stored = get_option( self::TOKEN_OPTION, '' );
		return $stored ? Crypto::decrypt( $stored ) : '';
	}

	public static function has_token() {
		return '' !== self::get_token();
	}

	public static function token_from_constant() {
		return defined( 'PARCELFLOW_WC_ACCESS_TOKEN' ) && PARCELFLOW_WC_ACCESS_TOKEN;
	}

	/*
	---------------------------------------------------------------------
	 * Derived helpers.
	 * ------------------------------------------------------------------ */

	public static function is_configured() {
		return self::is( 'enabled' ) && self::has_token() && '' !== self::get( 'base_url' );
	}

	/**
	 * Currency in which ParcelFlow prices are interpreted.
	 */
	public static function rate_currency() {
		$c = (string) self::get( 'rate_currency' );
		return '' !== $c ? $c : get_woocommerce_currency();
	}

	/**
	 * Parse the "services" textarea.
	 *
	 * @return array<int,array{service_id:string,label:string,amount:float}>
	 */
	public static function services() {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) self::get( 'services_config' ) ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( '' === $parts[0] || ! ctype_digit( $parts[0] ) ) {
				continue;
			}
			$out[] = array(
				'service_id' => $parts[0],
				'label'      => isset( $parts[1] ) ? $parts[1] : '',
				'amount'     => isset( $parts[2] ) ? (float) $parts[2] : 0.0,
			);
		}
		return $out;
	}

	public static function notify_email() {
		$e = (string) self::get( 'notify_email' );
		return is_email( $e ) ? $e : get_option( 'admin_email' );
	}
}

<?php
/**
 * Maps WooCommerce data + plugin settings onto the documented ParcelFlow request structures.
 *
 * Only documented fields are produced. Anything the documentation leaves undefined
 * (rate_id origin, shipment-type rules, insurance/pickup catalogues, wallet funding) is
 * isolated here and in Settings so it can be adjusted when ParcelFlow answers.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Shipping;

use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Format;

defined( 'ABSPATH' ) || exit;

final class PayloadMapper {

	/* ------------------------------ Parties ------------------------------ */

	private static function country_name( $code ) {
		$list = WC()->countries->get_countries();
		return isset( $list[ $code ] ) ? html_entity_decode( $list[ $code ], ENT_QUOTES, 'UTF-8' ) : (string) $code;
	}

	private static function state_name( $country, $state ) {
		$states = WC()->countries->get_states( $country );
		if ( is_array( $states ) && isset( $states[ $state ] ) ) {
			return html_entity_decode( $states[ $state ], ENT_QUOTES, 'UTF-8' );
		}
		return (string) $state;
	}

	/**
	 * Sender from plugin settings.
	 *
	 * @return array{party:array,errors:string[],country_code:string,state_code:string}
	 */
	public static function sender() {
		$s       = Settings::all();
		$country = (string) $s['sender_country'];
		$party   = array(
			'name'      => Format::alnum( $s['sender_name'], 100 ),
			'address_1' => (string) $s['sender_address_1'],
			'address_2' => (string) $s['sender_address_2'],
			'city'      => (string) $s['sender_city'],
			'state'     => '' !== $country ? self::state_name( $country, (string) $s['sender_state'] ) : (string) $s['sender_state'],
			'country'   => '' !== $country ? self::country_name( $country ) : '',
			'postcode'  => (string) $s['sender_postcode'],
			'phone_no'  => (string) $s['sender_phone'],
			'email'     => (string) $s['sender_email'],
		);
		return array(
			'party'        => $party,
			'errors'       => self::validate_party( $party, __( 'Sender', 'parcelflow-for-woocommerce' ), __( 'Settings → Sender / Warehouse', 'parcelflow-for-woocommerce' ) ),
			'country_code' => $country,
			'state_code'   => (string) $s['sender_state'],
		);
	}

	/**
	 * Receiver for a cart package at checkout. Name/e-mail/phone are not part of the
	 * WooCommerce package, so they come from the customer object (kept in sync by checkout).
	 */
	public static function receiver_from_cart_package( array $package ) {
		$d        = $package['destination'];
		$customer = WC()->customer;
		$first    = $customer ? ( $customer->get_shipping_first_name() ? $customer->get_shipping_first_name() : $customer->get_billing_first_name() ) : '';
		$last     = $customer ? ( $customer->get_shipping_last_name() ? $customer->get_shipping_last_name() : $customer->get_billing_last_name() ) : '';
		$phone    = $customer ? ( $customer->get_shipping_phone() ? $customer->get_shipping_phone() : $customer->get_billing_phone() ) : '';
		$email    = $customer ? $customer->get_billing_email() : '';

		return self::receiver_party(
			array(
				'name'      => trim( $first . ' ' . $last ),
				'address_1' => isset( $d['address_1'] ) ? $d['address_1'] : ( isset( $d['address'] ) ? $d['address'] : '' ),
				'address_2' => isset( $d['address_2'] ) ? $d['address_2'] : '',
				'city'      => isset( $d['city'] ) ? $d['city'] : '',
				'state'     => isset( $d['state'] ) ? $d['state'] : '',
				'country'   => isset( $d['country'] ) ? $d['country'] : '',
				'postcode'  => isset( $d['postcode'] ) ? $d['postcode'] : '',
				'phone_no'  => $phone,
				'email'     => $email,
			)
		);
	}

	public static function receiver_from_order( \WC_Order $order ) {
		$has_shipping = $order->get_shipping_address_1() || $order->get_shipping_city();
		$first        = $has_shipping ? $order->get_shipping_first_name() : $order->get_billing_first_name();
		$last         = $has_shipping ? $order->get_shipping_last_name() : $order->get_billing_last_name();
		$phone        = $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
		return self::receiver_party(
			array(
				'name'      => trim( $first . ' ' . $last ),
				'address_1' => $has_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
				'address_2' => $has_shipping ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
				'city'      => $has_shipping ? $order->get_shipping_city() : $order->get_billing_city(),
				'state'     => $has_shipping ? $order->get_shipping_state() : $order->get_billing_state(),
				'country'   => $has_shipping ? $order->get_shipping_country() : $order->get_billing_country(),
				'postcode'  => $has_shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
				'phone_no'  => $phone,
				'email'     => $order->get_billing_email(),
			)
		);
	}

	/**
	 * @param array $raw Raw receiver fields; country/state are WooCommerce codes.
	 */
	private static function receiver_party( array $raw ) {
		$country_code = strtoupper( (string) $raw['country'] );
		$state_code   = (string) $raw['state'];
		$state_name   = '' !== $country_code ? self::state_name( $country_code, $state_code ) : $state_code;
		$party        = array(
			'name'      => Format::alnum( $raw['name'], 100 ),
			'address_1' => trim( (string) $raw['address_1'] ),
			'address_2' => trim( (string) $raw['address_2'] ),
			'city'      => trim( (string) $raw['city'] ),
			'state'     => $state_name,
			'country'   => '' !== $country_code ? self::country_name( $country_code ) : '',
			'postcode'  => trim( (string) $raw['postcode'] ),
			'phone_no'  => trim( (string) $raw['phone_no'] ),
			'email'     => sanitize_email( (string) $raw['email'] ),
		);
		return array(
			'party'        => $party,
			'errors'       => self::validate_party( $party, __( 'Receiver', 'parcelflow-for-woocommerce' ), '' ),
			'country_code' => $country_code,
			'state_code'   => $state_code,
		);
	}

	/**
	 * @return string[] Messages for missing required fields (address_2 is optional).
	 */
	public static function validate_party( array $p, $label, $where ) {
		$required = array(
			'name'      => __( 'name', 'parcelflow-for-woocommerce' ),
			'address_1' => __( 'address', 'parcelflow-for-woocommerce' ),
			'city'      => __( 'city', 'parcelflow-for-woocommerce' ),
			'state'     => __( 'state', 'parcelflow-for-woocommerce' ),
			'country'   => __( 'country', 'parcelflow-for-woocommerce' ),
			'postcode'  => __( 'postcode', 'parcelflow-for-woocommerce' ),
			'phone_no'  => __( 'phone', 'parcelflow-for-woocommerce' ),
			'email'     => __( 'email', 'parcelflow-for-woocommerce' ),
		);
		$errors   = array();
		foreach ( $required as $k => $name ) {
			if ( '' === trim( (string) $p[ $k ] ) ) {
				/* translators: 1: Sender/Receiver, 2: field name */
				$errors[] = sprintf( __( '%1$s %2$s is missing.', 'parcelflow-for-woocommerce' ), $label, $name );
			}
		}
		if ( '' !== $p['email'] && ! is_email( $p['email'] ) ) {
			/* translators: %s: Sender/Receiver */
			$errors[] = sprintf( __( '%s email is not valid.', 'parcelflow-for-woocommerce' ), $label );
		}
		if ( $errors && $where ) {
			$errors[] = sprintf( /* translators: %s: settings location */ __( 'Configure it under %s.', 'parcelflow-for-woocommerce' ), $where );
		}
		return $errors;
	}

	/* --------------------------- Shipment type --------------------------- */

	/**
	 * Local / Interstate / International / Import.
	 *
	 * The API documentation defines no rule for choosing a type, so the administrator must choose one
	 * (or opt in to the experimental auto-detect). Empty means "not chosen".
	 *
	 * @param array $sender   sender() result.
	 * @param array $receiver receiver result.
	 */
	public static function shipment_type( array $sender, array $receiver ) {
		$mode = (string) Settings::get( 'shipment_type_mode', '' );
		if ( '' === $mode ) {
			return ''; // Not chosen: the API documentation defines no rule, so none is assumed.
		}
		if ( 'auto' !== $mode ) {
			return $mode;
		}
		if ( '' === $sender['country_code'] || '' === $receiver['country_code'] ) {
			return '';
		}
		if ( $sender['country_code'] !== $receiver['country_code'] ) {
			return 'International';
		}
		$a = strtolower( self::state_name( $sender['country_code'], $sender['state_code'] ) );
		$b = strtolower( self::state_name( $receiver['country_code'], $receiver['state_code'] ) );
		return ( '' !== $a && $a === $b ) ? 'Local' : 'Interstate';
	}

	/* ------------------------------ Payloads ----------------------------- */

	/**
	 * Package group shared by calculate-shipment and create-shipment.
	 */
	public static function package_group( array $lines, array $carton, $for_booking = false ) {
		$currency = get_woocommerce_currency();
		$items    = array();
		foreach ( $lines as $l ) {
			$item = array(
				'name'           => $l['name'],
				'description'    => $l['description'],
				'quantity'       => (int) $l['quantity'],
				'value'          => (float) $l['value'],
				'value_currency' => $currency,
				'weight'         => (float) $l['weight'],
				'hs_code'        => $l['hs_code'],
			);
			if ( $for_booking && ! empty( $l['image_url'] ) ) {
				// Documented as required when insurance is requested.
				$item['image_url'] = $l['image_url'];
			}
			$items[] = $item;
		}
		$pkg = array(
			'number_of_items' => (int) $carton['items'],
			'package_weight'  => (float) $carton['weight'],
			'package_length'  => (float) $carton['length'],
			'package_width'   => (float) $carton['width'],
			'package_height'  => (float) $carton['height'],
		);
		if ( $for_booking ) {
			// Present in the documented Create Shipment example payload.
			$pkg['length'] = (float) $carton['length'];
			$pkg['width']  = (float) $carton['width'];
			$pkg['height'] = (float) $carton['height'];
		}
		$pkg['package_items'] = $items;

		return array(
			'package_type' => (string) Settings::get( 'package_type', 'Parcel' ),
			'packages'     => array( $pkg ),
		);
	}

	/**
	 * Calculate Shipment Cost payload.
	 */
	public static function calculate_payload( array $sender, array $receiver, array $lines, array $carton, $shipment_type ) {
		$booking = array(
			'shipment_type' => $shipment_type,
			'shipping_date' => 'Now',
		);
		$station = (string) Settings::get( 'processing_station_id' );
		if ( '' !== $station ) {
			$booking['processing_station_id'] = $station;
		}
		if ( Settings::is( 'request_insurance' ) ) {
			$booking['insurance'] = array( 'request_insurance' => 'Yes' );
		}
		if ( Settings::is( 'request_pickup' ) && '' !== (string) Settings::get( 'pickup_type_id' ) ) {
			$booking['pickup'] = array(
				'request_pickup' => 'Yes',
				'pickup_type_id' => (string) Settings::get( 'pickup_type_id' ),
			);
		}
		$services = Settings::services();
		if ( $services ) {
			$booking['services'] = array_map(
				static function ( $s ) {
					return array( 'service_id' => $s['service_id'] );
				},
				$services
			);
		}

		return array(
			'sender'   => $sender['party'],
			'receiver' => $receiver['party'],
			'package'  => self::package_group( $lines, $carton ),
			'booking'  => $booking,
		);
	}

	/**
	 * Create Shipment payload.
	 *
	 * Fee mapping (partly UNCONFIRMED, see docs/API-QUESTIONS.md):
	 *   standard_fee = selling_price returned by calculation
	 *   extras_fee   = pickup_fee (when pickup requested) + insurance_fee
	 *   services_fee = sum of configured service amounts
	 *   shipping_fee = standard + extras + services
	 * It is not documented whether selling_price already includes pickup_fee.
	 *
	 * @param array  $rate     Stored rate: carrier_id, selling_price, pickup_fee.
	 * @param string $rate_id  The rate_id (from the API or entered by an administrator).
	 * @return array{payload:array,errors:string[]}
	 */
	public static function create_payload( array $sender, array $receiver, array $lines, array $carton, $shipment_type, array $rate, $rate_id ) {
		$errors  = array();
		$booking = array(
			'shipment_type'  => $shipment_type,
			'carrier_id'     => (string) $rate['carrier_id'],
			'rate_id'        => (string) $rate_id,
			'shipping_date'  => 'Now',
			'currency'       => Settings::rate_currency(),
			'payment_method' => (string) Settings::get( 'payment_method', 'Wallet' ),
		);
		if ( '' !== (string) Settings::get( 'processing_station_id' ) ) {
			$booking['processing_station_id'] = (string) Settings::get( 'processing_station_id' );
		}
		if ( '' !== (string) Settings::get( 'service_station_id' ) ) {
			$booking['service_station_id'] = (string) Settings::get( 'service_station_id' );
		}

		$standard = (float) $rate['selling_price'];
		$extras   = 0.0;

		if ( Settings::is( 'request_insurance' ) ) {
			$ins_fee  = Settings::get( 'insurance_fee' );
			$ins_type = (string) Settings::get( 'insurance_type' );
			if ( '' === $ins_type || '' === (string) $ins_fee ) {
				$errors[] = __( 'Insurance is enabled but the insurance type/fee is not configured.', 'parcelflow-for-woocommerce' );
			} else {
				$booking['insurance'] = array(
					'request_insurance' => 'Yes',
					'insurance_type'    => $ins_type,
					'insurance_fee'     => (float) $ins_fee,
				);
				$extras              += (float) $ins_fee;
				foreach ( $lines as $l ) {
					if ( empty( $l['image_url'] ) ) {
						/* translators: %s: product name */
						$errors[] = sprintf( __( '"%s" needs a product image because insurance is requested.', 'parcelflow-for-woocommerce' ), $l['name'] );
					}
				}
			}
		}
		if ( Settings::is( 'request_pickup' ) ) {
			if ( '' === (string) Settings::get( 'pickup_type_id' ) ) {
				$errors[] = __( 'Pickup is enabled but no pickup type ID is configured.', 'parcelflow-for-woocommerce' );
			} else {
				$pickup_fee        = isset( $rate['pickup_fee'] ) ? (float) $rate['pickup_fee'] : 0.0;
				$booking['pickup'] = array(
					'request_pickup' => 'Yes',
					'pickup_type_id' => (string) Settings::get( 'pickup_type_id' ),
					'pickup_fee'     => $pickup_fee,
				);
				$extras           += $pickup_fee;
			}
		}
		$services_fee = 0.0;
		$services     = Settings::services();
		if ( $services ) {
			$booking['services'] = array();
			foreach ( $services as $s ) {
				$booking['services'][] = array(
					'service_id'     => $s['service_id'],
					'service_amount' => (float) $s['amount'],
				);
				$services_fee         += (float) $s['amount'];
			}
		}
		$booking['fees'] = array(
			'standard_fee' => round( $standard, 2 ),
			'services_fee' => round( $services_fee, 2 ),
			'extras_fee'   => round( $extras, 2 ),
			'shipping_fee' => round( $standard + $extras + $services_fee, 2 ),
		);

		return array(
			'payload' => array(
				'sender'   => $sender['party'],
				'receiver' => $receiver['party'],
				'package'  => self::package_group( $lines, $carton, true ),
				'booking'  => $booking,
			),
			'errors'  => $errors,
		);
	}
}

<?php
/**
 * Adapter that converts a Calculate Shipment Cost response into rate objects.
 *
 * This is the single place that knows the response shape, so that when ParcelFlow
 * confirms where rate_id is returned only this class needs to change.
 *
 * rate_id: the documented sample carrier object has NO rate_id. We therefore only
 * capture a literal "rate_id" key if the API returns one inside a carrier object. We never
 * derive, guess or synthesise a rate_id from carrier_id or anything else.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Api;

use ParcelFlow\WooCommerce\Support\Format;

defined( 'ABSPATH' ) || exit;

final class RateParser {

	/**
	 * @param Response $response Successful calculate-shipment response.
	 * @return array[] List of normalised rates (may be empty).
	 */
	public static function parse( Response $response ) {
		$data = $response->data();
		if ( ! is_array( $data ) || ! isset( $data['carriers'] ) || ! is_array( $data['carriers'] ) ) {
			return array();
		}
		$rates = array();
		foreach ( $data['carriers'] as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$carrier_id = isset( $c['carrier_id'] ) && ( is_scalar( $c['carrier_id'] ) ) ? sanitize_text_field( (string) $c['carrier_id'] ) : '';
			$price      = isset( $c['selling_price'] ) ? Format::money( $c['selling_price'] ) : null;
			// Never show a rate without an id or a parseable customer price.
			if ( '' === $carrier_id || null === $price ) {
				continue;
			}
			$pickup = isset( $c['pickup_fee'] ) ? Format::money( $c['pickup_fee'] ) : 0.0;

			$rates[] = array(
				'carrier_id'      => $carrier_id,
				'carrier_name'    => isset( $c['carrier_name'] ) ? sanitize_text_field( (string) $c['carrier_name'] ) : $carrier_id,
				'carrier_logo'    => isset( $c['carrier_logo'] ) ? Format::safe_url( $c['carrier_logo'] ) : '',
				'delivery_time'   => isset( $c['delivery_time'] ) ? sanitize_text_field( (string) $c['delivery_time'] ) : '',
				'weight'          => isset( $c['weight'] ) && is_scalar( $c['weight'] ) ? sanitize_text_field( (string) $c['weight'] ) : '',
				'additional_info' => isset( $c['additional_info'] ) && is_scalar( $c['additional_info'] ) ? sanitize_text_field( (string) $c['additional_info'] ) : '',
				'selling_price'   => $price,
				'pickup_fee'      => null === $pickup ? 0.0 : $pickup,
				// Only if the API literally supplies it. Otherwise stays '' (unknown).
				'rate_id'         => isset( $c['rate_id'] ) && is_scalar( $c['rate_id'] ) ? sanitize_text_field( (string) $c['rate_id'] ) : '',
				// carrier_cost_price is intentionally dropped: it must never reach customers
				// and is not stored.
			);
		}
		return $rates;
	}
}

<?php
/**
 * Fetches (and briefly caches) ParcelFlow rates for an exact payload.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Shipping;

use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Api\RateParser;
use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class RateService {

	/**
	 * @param array $payload calculate-shipment payload.
	 * @return array{rates:array,error:string,cached:bool}
	 */
	public static function quote( array $payload ) {
		$minutes = (int) Settings::get( 'rate_cache_minutes', 10 );
		$key     = self::cache_key( $payload );

		if ( $minutes > 0 ) {
			$hit = get_transient( $key );
			if ( is_array( $hit ) ) {
				return array(
					'rates'  => self::filter_disabled( $hit ),
					'error'  => '',
					'cached' => true,
				);
			}
		}

		$client = new Client();
		$res    = $client->calculate_shipment( $payload );

		if ( ! $res->success ) {
			return array(
				'rates'  => array(),
				'error'  => $res->message,
				'cached' => false,
			);
		}

		$rates = RateParser::parse( $res );
		self::remember_carriers( $rates );

		// Cache successful responses only (including a valid empty result), never errors.
		if ( $minutes > 0 ) {
			set_transient( $key, $rates, $minutes * MINUTE_IN_SECONDS );
		}
		return array(
			'rates'  => self::filter_disabled( $rates ),
			'error'  => '',
			'cached' => false,
		);
	}

	/**
	 * Cache key covers every field that can change a price: addresses (country/state/city/
	 * postcode/address), package contents, weights, dimensions, currency, booking options,
	 * the API URL. Contact details (name, phone, e-mail) do not affect pricing and are
	 * excluded so typing them does not trigger a new API call.
	 */
	private static function cache_key( array $payload ) {
		$p = $payload;
		foreach ( array( 'sender', 'receiver' ) as $party ) {
			unset( $p[ $party ]['name'], $p[ $party ]['phone_no'], $p[ $party ]['email'] );
		}
		return 'pf_rates_' . md5( wp_json_encode( $p ) . '|' . get_woocommerce_currency() . '|' . Settings::get( 'base_url' ) );
	}

	private static function filter_disabled( array $rates ) {
		$disabled = array_map( 'strval', (array) Settings::get( 'disabled_carriers', array() ) );
		if ( ! $disabled ) {
			return $rates;
		}
		return array_values(
			array_filter(
				$rates,
				static function ( $r ) use ( $disabled ) {
					return ! in_array( (string) $r['carrier_id'], $disabled, true );
				}
			)
		);
	}

	/**
	 * Remember carriers seen in real API responses for the Carriers settings tab.
	 * (Carrier IDs are provider-controlled and never hardcoded.)
	 */
	private static function remember_carriers( array $rates ) {
		if ( ! $rates ) {
			return;
		}
		$seen    = (array) get_option( Settings::SEEN_CARRIERS, array() );
		$changed = false;
		foreach ( $rates as $r ) {
			$id = (string) $r['carrier_id'];
			if ( ! isset( $seen[ $id ] ) || $seen[ $id ]['name'] !== $r['carrier_name'] ) {
				$changed = true;
			}
			$seen[ $id ] = array(
				'name'      => $r['carrier_name'],
				'logo'      => $r['carrier_logo'],
				'last_seen' => time(),
			);
		}
		if ( $changed ) {
			update_option( Settings::SEEN_CARRIERS, $seen, false );
		}
	}
}

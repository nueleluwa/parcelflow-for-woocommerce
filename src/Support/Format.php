<?php
/**
 * Small formatting / normalisation helpers.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Support;

defined( 'ABSPATH' ) || exit;

final class Format {

	/**
	 * Parse an API money value. The API returns strings such as "64,803.24".
	 *
	 * @param mixed $value Raw value.
	 * @return float|null Null when it cannot be parsed as a non-negative number.
	 */
	public static function money( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value >= 0 ? (float) $value : null;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$clean = str_replace( array( ',', ' ' ), '', trim( $value ) );
		if ( '' === $clean || ! is_numeric( $clean ) || (float) $clean < 0 ) {
			return null;
		}
		return (float) $clean;
	}

	/**
	 * The API restricts names/descriptions to "letters and numbers only".
	 * Keep unicode letters/digits and single spaces; length-limit.
	 */
	public static function alnum( $text, $max = 120 ) {
		$text = wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
		$text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	public static function decimal( $n, $places = 4 ) {
		return null === $n || '' === $n ? null : round( (float) $n, $places );
	}

	public static function datetime_local( $gmt_mysql ) {
		if ( empty( $gmt_mysql ) ) {
			return '';
		}
		return get_date_from_gmt( $gmt_mysql, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	}

	/** Only http(s) URLs are ever linked/downloaded. */
	public static function safe_url( $url ) {
		$url = esc_url_raw( (string) $url, array( 'https', 'http' ) );
		return $url;
	}
}

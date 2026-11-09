<?php
/**
 * Redacts secrets and personal data before anything is logged.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Support;

use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Redactor {

	const MASK = '[redacted]';

	/** Keys whose values are always masked (compared case-insensitively). */
	private static $keys = array(
		'access_token',
		'access-token',
		'token',
		'authorization',
		'password',
		'secret',
		'name',
		'sender_name',
		'receiver_name',
		'address_1',
		'address_2',
		'sender_address',
		'receiver_address',
		'phone',
		'phone_no',
		'email',
		'postcode',
	);

	/**
	 * @param mixed $data Array/scalar to redact.
	 * @return mixed
	 */
	public static function redact( $data, $depth = 0 ) {
		if ( $depth > 8 ) {
			return self::MASK;
		}
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( is_string( $k ) && in_array( strtolower( $k ), self::$keys, true ) ) {
					$out[ $k ] = self::MASK;
				} else {
					$out[ $k ] = self::redact( $v, $depth + 1 );
				}
			}
			return $out;
		}
		if ( is_string( $data ) ) {
			return self::scrub_string( $data );
		}
		return $data;
	}

	/**
	 * Mask the token and e-mail addresses inside free text (error messages etc.).
	 */
	public static function scrub_string( $text ) {
		$token = Settings::get_token();
		if ( '' !== $token && strlen( $token ) >= 6 ) {
			$text = str_replace( $token, self::MASK, $text );
		}
		return preg_replace( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', self::MASK, $text );
	}
}

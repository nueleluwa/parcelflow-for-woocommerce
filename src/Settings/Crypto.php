<?php
/**
 * Authenticated encryption for the stored API token (libsodium secretbox).
 *
 * The key is derived from the site's secret salts, so a database dump alone does not
 * reveal the token. For stronger isolation, define PARCELFLOW_WC_ACCESS_TOKEN in
 * wp-config.php instead of storing it in the database.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Settings;

defined( 'ABSPATH' ) || exit;

final class Crypto {

	const PREFIX = 'pfv2:';

	private static function key() {
		return hash( 'sha256', wp_salt( 'secure_auth' ) . '|parcelflow', true );
	}

	public static function encrypt( $plain ) {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( (string) $plain, $nonce, self::key() );
		return self::PREFIX . bin2hex( $nonce . $cipher );
	}

	/**
	 * @return string Plain text, or '' when it cannot be decrypted (e.g. salts changed).
	 */
	public static function decrypt( $stored ) {
		if ( 0 !== strpos( (string) $stored, self::PREFIX ) ) {
			return '';
		}
		$hex = substr( $stored, strlen( self::PREFIX ) );
		$raw = ( '' !== $hex && 0 === strlen( $hex ) % 2 && ctype_xdigit( $hex ) ) ? hex2bin( $hex ) : false;
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		return false === $plain ? '' : $plain;
	}
}

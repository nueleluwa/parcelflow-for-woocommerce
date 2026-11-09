<?php
/**
 * Minimal PSR-4 style autoloader for the ParcelFlow\WooCommerce namespace.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	const PREFIX = 'ParcelFlow\\WooCommerce\\';

	/**
	 * Register the autoloader.
	 *
	 * @param string $base_dir Directory that maps to the namespace root (with trailing slash).
	 */
	public static function register( $base_dir ) {
		spl_autoload_register(
			static function ( $class_name ) use ( $base_dir ) {
				if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
					return;
				}
				$relative = substr( $class_name, strlen( self::PREFIX ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}

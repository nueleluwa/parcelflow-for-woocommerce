<?php
/**
 * Tiny template loader.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

defined( 'ABSPATH' ) || exit;

final class View {

	private static function path( $dir, $name ) {
		$name = preg_replace( '/[^a-z0-9\-]/', '', $name );
		return PARCELFLOW_WC_PATH . $dir . '/views/' . $name . '.php';
	}

	public static function render( $name, array $vars = array() ) {
		self::include_file( self::path( 'admin', $name ), $vars );
	}

	public static function render_frontend( $name, array $vars = array() ) {
		self::include_file( self::path( 'frontend', $name ), $vars );
	}

	public static function capture_frontend( $name, array $vars = array() ) {
		ob_start();
		self::render_frontend( $name, $vars );
		return ob_get_clean();
	}

	private static function include_file( $file, array $vars ) {
		if ( ! is_readable( $file ) ) {
			return;
		}
		// Variables are explicit view data passed by our own code.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars, EXTR_SKIP );
		include $file;
	}

	/** Brand header shown on every ParcelFlow admin screen. */
	public static function header( $title ) {
		echo '<div class="pf-header"><span class="pf-brand"><span class="pf-brand__mark" aria-hidden="true"></span>ParcelFlow</span><h1 class="pf-title">' . esc_html( $title ) . '</h1></div>';
	}

	/** Queue a flash notice for the current user (shown after a redirect). */
	public static function flash( $type, $message ) {
		set_transient(
			'parcelflow_wc_flash_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			120
		);
	}

	public static function print_flash() {
		$key   = 'parcelflow_wc_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( is_array( $flash ) ) {
			delete_transient( $key );
			$type = in_array( $flash['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $flash['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $flash['message'] ) . '</p></div>';
		}
	}
}

<?php
/**
 * Customer tracking: [parcelflow_tracking] shortcode, /track-shipment/ page, and the
 * "Track Shipment" My Account endpoint.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Tracking;

use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;

defined( 'ABSPATH' ) || exit;

final class TrackingPage {

	const SHORTCODE = 'parcelflow_tracking';
	const ENDPOINT  = 'parcelflow-tracking';
	const PAGE_OPT  = 'parcelflow_wc_tracking_page_id';
	const QUERY_ARG = 'pf_track';

	public function register() {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		// My Account endpoint.
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_account_endpoint' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( $this, 'endpoint_title' ) );
	}

	/**
	 * Create the /track-shipment/ page on activation (once, only if the slug is free).
	 */
	public static function ensure_page() {
		$id = (int) get_option( self::PAGE_OPT );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return;
		}
		if ( get_page_by_path( 'track-shipment' ) ) {
			return; // Do not hijack an existing page; the admin can add the shortcode.
		}
		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Track Shipment', 'parcelflow-for-woocommerce' ),
				'post_name'    => 'track-shipment',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[' . self::SHORTCODE . ']',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_option( self::PAGE_OPT, (int) $id, false );
		}
	}

	/** URL of the tracking page, optionally prefilled. */
	public static function url_for( $number = '' ) {
		$page = get_page_by_path( 'track-shipment' );
		$base = $page ? get_permalink( $page ) : home_url( '/track-shipment/' );
		return $number ? add_query_arg( self::QUERY_ARG, rawurlencode( $number ), $base ) : $base;
	}

	public function enqueue() {
		global $post;
		$needs = ( $post instanceof \WP_Post && has_shortcode( $post->post_content, self::SHORTCODE ) )
			|| ( function_exists( 'is_account_page' ) && is_account_page() );
		if ( $needs ) {
			wp_enqueue_style( 'parcelflow-frontend', PARCELFLOW_WC_URL . 'assets/css/frontend.css', array(), PARCELFLOW_WC_VERSION );
		}
	}

	public function shortcode() {
		return $this->render();
	}

	/**
	 * The lookup is read-only (GET) so it works on fully cached pages and cannot be used for
	 * CSRF; abuse is limited by validation, local-reference checks and per-IP throttling.
	 */
	private function render( $action_url = '' ) {
		$number = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = null;
		if ( isset( $_GET[ self::QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result = TrackingService::lookup_public( $number );
		}
		return View::capture_frontend(
			'tracking',
			array(
				'number'     => $number,
				'result'     => $result,
				'action_url' => $action_url ? $action_url : '',
				'arg'        => self::QUERY_ARG,
			)
		);
	}

	/* --------------------------- My Account tab -------------------------- */

	public function add_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public function menu_item( $items ) {
		$new = array();
		foreach ( $items as $k => $label ) {
			if ( 'customer-logout' === $k ) {
				$new[ self::ENDPOINT ] = __( 'Track Shipment', 'parcelflow-for-woocommerce' );
			}
			$new[ $k ] = $label;
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'Track Shipment', 'parcelflow-for-woocommerce' );
		}
		return $new;
	}

	public function endpoint_title() {
		return __( 'Track Shipment', 'parcelflow-for-woocommerce' );
	}

	public function render_account_endpoint() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- view escapes its own output.
		echo $this->render( wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) );
	}
}

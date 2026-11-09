<?php
/**
 * Plugin bootstrap: dependency checks and component wiring.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce;

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Data\Schema;
use ParcelFlow\WooCommerce\Orders\OrderIntegration;
use ParcelFlow\WooCommerce\Products\ProductFields;
use ParcelFlow\WooCommerce\Shipping\ShippingRegistrar;
use ParcelFlow\WooCommerce\Tracking\Scheduler;
use ParcelFlow\WooCommerce\Tracking\TrackingPage;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init() {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( PARCELFLOW_WC_FILE ), array( $this, 'action_links' ) );
	}

	public function boot() {
		if ( ! $this->dependencies_met() ) {
			return;
		}

		Schema::maybe_upgrade();

		( new ShippingRegistrar() )->register();
		( new ProductFields() )->register();
		( new OrderIntegration() )->register();
		( new TrackingPage() )->register();
		( new Scheduler() )->register();

		if ( is_admin() ) {
			( new Admin() )->register();
		}

		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
	}

	private function dependencies_met() {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_wc' ) );
			return false;
		}
		if ( version_compare( WC_VERSION, PARCELFLOW_WC_MIN_WC, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_old_wc' ) );
			return false;
		}
		return true;
	}

	public function notice_missing_wc() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'ParcelFlow for WooCommerce requires WooCommerce to be installed and active.', 'parcelflow-for-woocommerce' ) . '</p></div>';
	}

	public function notice_old_wc() {
		echo '<div class="notice notice-error"><p>' . esc_html(
			sprintf(
				/* translators: %s: minimum WooCommerce version */
				__( 'ParcelFlow for WooCommerce requires WooCommerce %s or newer.', 'parcelflow-for-woocommerce' ),
				PARCELFLOW_WC_MIN_WC
			)
		) . '</p></div>';
	}

	public function maybe_flush_rewrites() {
		if ( get_option( 'parcelflow_wc_flush_rewrite' ) ) {
			delete_option( 'parcelflow_wc_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'parcelflow-for-woocommerce' ) . '</a>' );
		return $links;
	}
}

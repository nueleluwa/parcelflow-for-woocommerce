<?php
/**
 * Admin menu, assets and page routing.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/** Day-to-day shipment operations (Shop Managers). */
	const CAP = 'manage_woocommerce';

	/**
	 * API credentials, API base URL and booking behaviour. Administrators only: whoever can
	 * edit the base URL can redirect the access token to a server they control.
	 */
	const CAP_SETTINGS = 'manage_options';

	/** @var string[] Screen hook suffixes of our pages. */
	private $screens = array();

	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'global_notices' ) );
		( new Actions() )->register();
	}

	public function menu() {
		$icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iI2E3YWFhZCIgZD0iTTIgNS41IDEwIDJsOCAzLjV2OUwxMCAxOGwtOC0zLjV2LTl6bTgtMS42TDUuMiA2IDEwIDguMSAxNC44IDYgMTAgMy45ek00IDcuNXY2LjJsNSAyLjJWOS43TDQgNy41em0xMiAwLTUgMi4ydjYuMmw1LTIuMlY3LjV6Ii8+PC9zdmc+';

		$this->screens[] = add_menu_page(
			__( 'ParcelFlow Shipments', 'parcelflow-for-woocommerce' ),
			__( 'ParcelFlow', 'parcelflow-for-woocommerce' ),
			self::CAP,
			'parcelflow-shipments',
			array( $this, 'page_shipments' ),
			$icon,
			56
		);
		$this->screens[] = add_submenu_page( 'parcelflow-shipments', __( 'Shipments', 'parcelflow-for-woocommerce' ), __( 'Shipments', 'parcelflow-for-woocommerce' ), self::CAP, 'parcelflow-shipments', array( $this, 'page_shipments' ) );
		$this->screens[] = add_submenu_page( 'parcelflow-shipments', __( 'Settings', 'parcelflow-for-woocommerce' ), __( 'Settings', 'parcelflow-for-woocommerce' ), self::CAP_SETTINGS, 'parcelflow-settings', array( $this, 'page_settings' ) );
		$this->screens[] = add_submenu_page( 'parcelflow-shipments', __( 'Logs', 'parcelflow-for-woocommerce' ), __( 'Logs', 'parcelflow-for-woocommerce' ), self::CAP, 'parcelflow-logs', array( $this, 'page_logs' ) );
		$this->screens[] = add_submenu_page( 'parcelflow-shipments', __( 'Help / API Status', 'parcelflow-for-woocommerce' ), __( 'Help / API Status', 'parcelflow-for-woocommerce' ), self::CAP, 'parcelflow-help', array( $this, 'page_help' ) );
	}

	public function assets( $hook ) {
		$is_pf    = in_array( $hook, $this->screens, true );
		$is_order = function_exists( 'wc_get_page_screen_id' ) && ( wc_get_page_screen_id( 'shop-order' ) === $hook || 'post.php' === $hook );
		if ( ! $is_pf && ! $is_order ) {
			return;
		}
		wp_enqueue_style( 'parcelflow-admin', PARCELFLOW_WC_URL . 'assets/css/admin.css', array(), PARCELFLOW_WC_VERSION );
		wp_enqueue_script( 'parcelflow-admin', PARCELFLOW_WC_URL . 'assets/js/admin.js', array(), PARCELFLOW_WC_VERSION, true );
		wp_localize_script(
			'parcelflow-admin',
			'parcelflowAdmin',
			array(
				'confirmBook'      => __( 'Book this shipment with ParcelFlow now? A request will be sent once and cannot be duplicated.', 'parcelflow-for-woocommerce' ),
				'confirmNotMade'   => __( 'Only continue if you have verified in ParcelFlow that NO shipment exists for this order. Allow a new booking attempt?', 'parcelflow-for-woocommerce' ),
				'confirmClearLogs' => __( 'Delete all log entries?', 'parcelflow-for-woocommerce' ),
			)
		);
	}

	public function global_notices() {
		if ( ! current_user_can( self::CAP ) || ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'parcelflow' ) ) {
			return;
		}
		View::print_flash();
		if ( ! Settings::has_token() ) {
			echo '<div class="notice notice-warning"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: settings URL */
					__( 'No ParcelFlow access token is stored. <a href="%s">Add it in Settings</a>.', 'parcelflow-for-woocommerce' ),
					esc_url( admin_url( 'admin.php?page=parcelflow-settings' ) )
				)
			) . '</p></div>';
		}
		if ( 'production' === Settings::get( 'environment' ) && false !== strpos( (string) Settings::get( 'base_url' ), 'staging' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Environment is set to Production but the API base URL still points at the staging server.', 'parcelflow-for-woocommerce' ) . '</p></div>';
		}
	}

	/* ------------------------------ Pages -------------------------------- */

	public function page_shipments() {
		$this->guard();
		( new ShipmentsPage() )->render();
	}

	public function page_settings() {
		$this->guard( self::CAP_SETTINGS );
		( new SettingsPage() )->render();
	}

	public function page_logs() {
		$this->guard();
		( new LogsPage() )->render();
	}

	public function page_help() {
		$this->guard();
		( new HelpPage() )->render();
	}

	private function guard( $cap = self::CAP ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'parcelflow-for-woocommerce' ), 403 );
		}
	}

	/** Build an admin URL to one of our pages. */
	public static function url( $page, array $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=parcelflow-' . $page ) );
	}
}

<?php
/**
 * admin-post.php handlers. Every handler: capability check + nonce + sanitised input,
 * then a redirect with a flash notice (post/redirect/get).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Booking\BookingService;
use ParcelFlow\WooCommerce\Data\LogRepository;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;
use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Logger;
use ParcelFlow\WooCommerce\Tracking\TrackingService;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- every handler calls $this->authorize() (capability + check_admin_referer) first.
final class Actions {

	public function register() {
		$actions = array(
			'save_settings',
			'book',
			'refresh_tracking',
			'link_existing',
			'confirm_not_created',
			'export_csv',
			'test_connection',
			'clear_logs',
		);
		foreach ( $actions as $a ) {
			add_action( 'admin_post_parcelflow_wc_' . $a, array( $this, $a ) );
		}
	}

	/** Verify capability and nonce; die otherwise. */
	private function authorize( $nonce_action, $cap = Admin::CAP ) {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'parcelflow-for-woocommerce' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private function back( $fallback ) {
		$to = wp_get_referer();
		wp_safe_redirect( $to ? $to : $fallback );
		exit;
	}

	private function order_id() {
		return isset( $_REQUEST['order_id'] ) ? absint( $_REQUEST['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in authorize().
	}

	/* ------------------------------ Settings ----------------------------- */

	public function save_settings() {
		$this->authorize( 'parcelflow_wc_save_settings', Admin::CAP_SETTINGS );
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		if ( ! array_key_exists( $tab, Settings::tabs() ) ) {
			$tab = 'general';
		}
		$input    = isset( $_POST['pf'] ) && is_array( $_POST['pf'] ) ? wp_unslash( $_POST['pf'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per-field in Settings::save_tab().
		$warnings = Settings::save_tab( $tab, $input );
		// Reflect a changed API configuration immediately.
		delete_transient( 'parcelflow_wc_connection' );

		View::flash( $warnings ? 'warning' : 'success', $warnings ? __( 'Settings saved. ', 'parcelflow-for-woocommerce' ) . implode( ' ', $warnings ) : __( 'Settings saved.', 'parcelflow-for-woocommerce' ) );
		wp_safe_redirect( Admin::url( 'settings', array( 'tab' => $tab ) ) );
		exit;
	}

	/* ------------------------------ Shipments ---------------------------- */

	public function book() {
		$order_id = $this->order_id();
		$this->authorize( 'parcelflow_wc_book_' . $order_id );
		$opts = array( 'source' => 'manual' );
		if ( ! empty( $_POST['rate_id'] ) ) {
			$opts['rate_id'] = sanitize_text_field( wp_unslash( $_POST['rate_id'] ) );
		}
		$r = BookingService::book( $order_id, $opts );
		View::flash( $r['ok'] ? 'success' : 'error', $r['message'] );
		$this->back( Admin::url( 'shipments' ) );
	}

	public function refresh_tracking() {
		$order_id = $this->order_id();
		$this->authorize( 'parcelflow_wc_refresh_' . $order_id );
		$row = ShipmentRepository::find_by_order( $order_id );
		if ( ! $row || '' === $row['shipment_number'] ) {
			View::flash( 'error', __( 'This order has no booked shipment to track.', 'parcelflow-for-woocommerce' ) );
		} else {
			$r = TrackingService::refresh( $row );
			View::flash( $r['ok'] ? 'success' : 'error', $r['ok'] ? __( 'Tracking refreshed.', 'parcelflow-for-woocommerce' ) : $r['message'] );
		}
		$this->back( Admin::url( 'shipments' ) );
	}

	public function link_existing() {
		$order_id = $this->order_id();
		$this->authorize( 'parcelflow_wc_link_' . $order_id );
		$number = isset( $_POST['shipment_number'] ) ? sanitize_text_field( wp_unslash( $_POST['shipment_number'] ) ) : '';
		$pf_id  = isset( $_POST['pf_shipment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['pf_shipment_id'] ) ) : '';
		$r      = BookingService::link_existing( $order_id, $number, $pf_id );
		View::flash( $r['ok'] ? 'success' : 'error', $r['message'] );
		$this->back( Admin::url( 'shipments' ) );
	}

	public function confirm_not_created() {
		$order_id = $this->order_id();
		$this->authorize( 'parcelflow_wc_clear_' . $order_id );
		if ( empty( $_POST['confirm'] ) ) {
			View::flash( 'error', __( 'Tick the confirmation box to continue.', 'parcelflow-for-woocommerce' ) );
		} else {
			$r = BookingService::confirm_not_created( $order_id );
			View::flash( $r['ok'] ? 'success' : 'error', $r['message'] );
		}
		$this->back( Admin::url( 'shipments' ) );
	}

	public function export_csv() {
		$this->authorize( 'parcelflow_wc_export' );
		$args           = ShipmentsPage::read_filters();
		$args['limit']  = 0;
		$args['offset'] = 0;
		$data           = ShipmentRepository::query( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="parcelflow-shipments-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'Order', 'Shipment number', 'Tracking number', 'Carrier', 'Shipping fee', 'Currency', 'Booking state', 'Status', 'Created (UTC)' ) );
		foreach ( $data['rows'] as $r ) {
			fputcsv(
				$out,
				array_map(
					array( self::class, 'csv_cell' ),
					array( $r['order_number'], $r['shipment_number'], $r['tracking_number'], $r['carrier_name'], $r['shipping_fee'], $r['currency'], $r['state'], $r['status'], $r['created_at'] )
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Neutralise spreadsheet formula injection. */
	public static function csv_cell( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	}

	/* ------------------------------- Help/Logs --------------------------- */

	/**
	 * Read-only connection check. There is no dedicated "ping" endpoint, so this uses the
	 * documented GET /get-shipment-history for today's date range with limit=1. It creates
	 * nothing and changes nothing.
	 */
	public function test_connection() {
		$this->authorize( 'parcelflow_wc_test_connection' );
		$today = gmdate( 'Y-m-d' );
		$res   = ( new Client() )->get_shipment_history( $today, $today, 'All', 1, 0 );
		$state = array(
			'time'    => time(),
			'ok'      => $res->success,
			'http'    => $res->http_code,
			'message' => $res->message,
		);
		set_transient( 'parcelflow_wc_connection', $state, DAY_IN_SECONDS );
		View::flash( $res->success ? 'success' : 'error', $res->success ? __( 'Connection OK: the API accepted the token and returned a valid response.', 'parcelflow-for-woocommerce' ) : $res->message );
		wp_safe_redirect( Admin::url( 'help' ) );
		exit;
	}

	public function clear_logs() {
		$this->authorize( 'parcelflow_wc_clear_logs' );
		LogRepository::clear();
		View::flash( 'success', __( 'Logs cleared.', 'parcelflow-for-woocommerce' ) );
		wp_safe_redirect( Admin::url( 'logs' ) );
		exit;
	}
}

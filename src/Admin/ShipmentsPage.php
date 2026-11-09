<?php
/**
 * ParcelFlow → Shipments (dashboard, list, detail, reconciliation).
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Data\ShipmentRepository;
use ParcelFlow\WooCommerce\Orders\OrderIntegration;
use ParcelFlow\WooCommerce\Shipping\PackageBuilder;
use ParcelFlow\WooCommerce\Shipping\PayloadMapper;
use ParcelFlow\WooCommerce\Support\Status;

defined( 'ABSPATH' ) || exit;

final class ShipmentsPage {

	const PER_PAGE = 20;

	/**
	 * Sanitised list filters from the query string (read-only GET filters).
	 */
	public static function read_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$group = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		if ( ! array_key_exists( $group, Status::groups() ) ) {
			$group = '';
		}
		$date = static function ( $key ) {
			$v = isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$out  = array(
			'search'    => isset( $_REQUEST['s'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) : '',
			'group'     => $group,
			'date_from' => $date( 'date_from' ),
			'date_to'   => $date( 'date_to' ),
		);
		// phpcs:enable
		return $out;
	}

	public function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
		if ( $view_id ) {
			$this->render_detail( $view_id );
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tab'] ) && 'api-history' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			$this->render_api_history();
			return;
		}

		$filters = self::read_filters();
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data    = ShipmentRepository::query(
			array_merge(
				$filters,
				array(
					'limit'  => self::PER_PAGE,
					'offset' => ( $paged - 1 ) * self::PER_PAGE,
				)
			)
		);

		View::render(
			'shipments',
			array(
				'filters'    => $filters,
				'rows'       => $data['rows'],
				'total'      => $data['total'],
				'paged'      => $paged,
				'pages'      => (int) ceil( $data['total'] / self::PER_PAGE ),
				'counts'     => ShipmentRepository::counts(),
				'export_url' => wp_nonce_url( add_query_arg( array_filter( array_merge( $filters, array( 'action' => 'parcelflow_wc_export_csv' ) ) ), admin_url( 'admin-post.php' ) ), 'parcelflow_wc_export' ),
			)
		);
	}

	/**
	 * Read-only browser for GET /get-shipment-history (documented date range, status, limit, offset).
	 * One API call per page view; requires only the page capability.
	 */
	private function render_api_history() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$date   = static function ( $key, $fallback ) {
			$v = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : $fallback;
		};
		$from   = $date( 'date_from', gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) );
		$to     = $date( 'date_to', gmdate( 'Y-m-d' ) );
		$status = isset( $_GET['status'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['status'] ) ) ) : '';
		$status = '' === $status ? 'All' : substr( $status, 0, 40 );
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$error = '';
		$rows  = array();
		$more  = false;
		if ( $from > $to ) {
			$error = __( 'The start date must not be after the end date.', 'parcelflow-for-woocommerce' );
		} else {
			// Ask for one extra record to know whether a next page exists (no total is documented).
			$res = ( new Client() )->get_shipment_history( $from, $to, $status, self::PER_PAGE + 1, ( $paged - 1 ) * self::PER_PAGE );
			if ( ! $res->success ) {
				$error = $res->message;
			} else {
				$data = $res->data();
				$data = is_array( $data ) ? array_values( array_filter( $data, 'is_array' ) ) : array();
				$more = count( $data ) > self::PER_PAGE;
				$rows = array_slice( $data, 0, self::PER_PAGE );
			}
		}

		// Which of these did this store book? Local lookup only, no extra API calls.
		$local = array();
		foreach ( $rows as $r ) {
			if ( ! empty( $r['shipment_number'] ) ) {
				$hit = ShipmentRepository::find_by_reference( (string) $r['shipment_number'] );
				if ( $hit ) {
					$local[ $r['shipment_number'] ] = $hit;
				}
			}
		}

		View::render(
			'shipments-api-history',
			array(
				'from'   => $from,
				'to'     => $to,
				'status' => $status,
				'paged'  => $paged,
				'more'   => $more,
				'rows'   => $rows,
				'local'  => $local,
				'error'  => $error,
			)
		);
	}

	private function render_detail( $id ) {
		$row = ShipmentRepository::find( $id );
		if ( ! $row ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Shipment record not found.', 'parcelflow-for-woocommerce' ) . '</p></div>';
			return;
		}
		$order = wc_get_order( (int) $row['order_id'] );

		// Stored booking snapshot if it exists, else a live preview.
		$request = ! empty( $row['request_json'] ) ? json_decode( $row['request_json'], true ) : null;
		$preview = false;
		if ( ! is_array( $request ) && $order ) {
			$sender   = PayloadMapper::sender();
			$receiver = PayloadMapper::receiver_from_order( $order );
			$built    = PackageBuilder::from_order( $order );
			if ( ! $built['errors'] ) {
				$carton  = PackageBuilder::carton( $built['lines'] );
				$request = array(
					'sender'   => $sender['party'],
					'receiver' => $receiver['party'],
					'package'  => PayloadMapper::package_group( $built['lines'], $carton ),
					'booking'  => array(),
				);
				$preview = true;
			}
		}

		$events = ! empty( $row['events_json'] ) ? json_decode( $row['events_json'], true ) : array();

		// Reconciliation candidates: only on explicit request (makes one read-only API call).
		$candidates = null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( Status::STATE_UNCERTAIN === $row['state'] && isset( $_GET['lookup'] ) && check_admin_referer( 'parcelflow_wc_lookup_' . $row['order_id'] ) ) {
			$ref        = strtotime( $row['last_attempt_at'] . ' UTC' );
			$ref        = $ref ? $ref : time();
			$res        = ( new Client() )->get_shipment_history( gmdate( 'Y-m-d', $ref - DAY_IN_SECONDS ), gmdate( 'Y-m-d', $ref + DAY_IN_SECONDS ), 'All', 50, 0, array( 'order_id' => (int) $row['order_id'] ) );
			$candidates = $res->success ? ( is_array( $res->data() ) ? $res->data() : array() ) : new \WP_Error( 'pf', $res->message );
		}

		View::render(
			'shipment-detail',
			array(
				'row'        => $row,
				'order'      => $order,
				'request'    => is_array( $request ) ? $request : array(),
				'preview'    => $preview,
				'events'     => is_array( $events ) ? $events : array(),
				'rate'       => $order ? OrderIntegration::rate_from_item( OrderIntegration::shipping_item( $order ) ) : array(),
				'candidates' => $candidates,
			)
		);
	}
}

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

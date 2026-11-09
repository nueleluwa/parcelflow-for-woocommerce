<?php
/**
 * ParcelFlow → Logs.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Data\LogRepository;
use ParcelFlow\WooCommerce\Support\Logger;

defined( 'ABSPATH' ) || exit;

final class LogsPage {

	const PER_PAGE = 25;

	public function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$types  = Logger::event_types();
		$type   = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';
		$type   = isset( $types[ $type ] ) ? $type : '';
		$status = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
		$status = in_array( $status, array( 'success', 'failure' ), true ) ? $status : '';
		$order  = isset( $_GET['order'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : '';
		$date   = static function ( $k ) {
			$v = isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$detail = isset( $_GET['log'] ) ? absint( $_GET['log'] ) : 0;
		// phpcs:enable

		$filters = array(
			'event_type' => $type,
			'status'     => $status,
			'order'      => (string) $order,
			'date_from'  => $date( 'date_from' ),
			'date_to'    => $date( 'date_to' ),
		);
		$data    = LogRepository::query(
			array_merge(
				$filters,
				array(
					'limit'  => self::PER_PAGE,
					'offset' => ( $paged - 1 ) * self::PER_PAGE,
				)
			)
		);

		View::render(
			'logs',
			array(
				'types'   => $types,
				'filters' => $filters,
				'rows'    => $data['rows'],
				'total'   => $data['total'],
				'paged'   => $paged,
				'pages'   => (int) ceil( $data['total'] / self::PER_PAGE ),
				'detail'  => $detail ? LogRepository::find( $detail ) : null,
			)
		);
	}
}

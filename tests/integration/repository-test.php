<?php
// Integration test run via `wp eval-file` against real WordPress + MariaDB.
use ParcelFlow\WooCommerce\Data\LogRepository;
use ParcelFlow\WooCommerce\Data\Schema;
use ParcelFlow\WooCommerce\Data\ShipmentRepository as R;
use ParcelFlow\WooCommerce\Support\Logger;

global $wpdb;
$fail = 0;
function t( $name, $cond ) { global $fail; echo ( $cond ? 'PASS' : 'FAIL' ) . "  $name\n"; if ( ! $cond ) { $fail++; } }

// Tables exist after activation.
t( 'shipments table exists', $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::shipments_table() ) ) === Schema::shipments_table() );
t( 'logs table exists', $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::logs_table() ) ) === Schema::logs_table() );

$wpdb->query( 'DELETE FROM ' . Schema::shipments_table() );
$wpdb->query( 'DELETE FROM ' . Schema::logs_table() );

// UNIQUE(order_id): idempotent create.
t( 'create_awaiting first', R::create_awaiting( 101, '101', array( 'carrier_name' => 'DHL' ) ) );
t( 'create_awaiting duplicate refused', false === R::create_awaiting( 101, '101' ) );
R::create_awaiting( 102, '102', array( 'carrier_name' => 'FedEx' ) );
R::create_awaiting( 103, '103' );

// Atomic claim: only one winner, and only from awaiting/failed.
t( 'claim wins once', R::claim_for_booking( 101 ) );
t( 'second claim loses (in_progress)', ! R::claim_for_booking( 101 ) );
$row = R::find_by_order( 101 );
t( 'state is in_progress, attempts=1', 'in_progress' === $row['state'] && 1 === (int) $row['attempts'] );

// Booked row cannot be claimed.
R::update( 101, array( 'state' => 'booked', 'shipment_number' => 'PF-1', 'tracking_number' => 'TRK1', 'status' => 'In Transit' ) );
t( 'claim refused after booked', ! R::claim_for_booking( 101 ) );
t( 'status_group recomputed', 'in_transit' === R::find_by_order( 101 )['status_group'] );

// Uncertain cannot be claimed; failed can.
R::claim_for_booking( 102 );
R::update( 102, array( 'state' => 'uncertain' ) );
t( 'claim refused when uncertain', ! R::claim_for_booking( 102 ) );
R::update( 103, array( 'state' => 'failed', 'error_message' => 'x' ) );
t( 'claim allowed after definitive failure', R::claim_for_booking( 103 ) );

// Lookups.
t( 'find_by_reference by shipment no.', R::find_by_reference( 'PF-1' )['order_id'] == 101 );
t( 'find_by_reference by tracking no.', R::find_by_reference( 'TRK1' )['order_id'] == 101 );
t( 'find_by_reference unknown => null', null === R::find_by_reference( 'NOPE' ) );
t( 'SQL injection string is inert', null === R::find_by_reference( "x' OR '1'='1" ) );

// Query / filters / pagination.
$all = R::query( array( 'limit' => 20 ) );
t( 'query all = 3', 3 === $all['total'] && 3 === count( $all['rows'] ) );
t( 'search by order number', 1 === R::query( array( 'search' => '101' ) )['total'] );
t( 'search by tracking', 1 === R::query( array( 'search' => 'TRK' ) )['total'] );
t( 'filter group in_transit', 1 === R::query( array( 'group' => 'in_transit' ) )['total'] );
t( 'filter group failed (uncertain)', 1 === R::query( array( 'group' => 'failed' ) )['total'] );
t( 'date range includes today', 3 === R::query( array( 'date_from' => gmdate( 'Y-m-d' ), 'date_to' => gmdate( 'Y-m-d' ) ) )['total'] );
t( 'date range excludes past', 0 === R::query( array( 'date_from' => '2000-01-01', 'date_to' => '2000-01-02' ) )['total'] );
$p = R::query( array( 'limit' => 2, 'offset' => 2 ) );
t( 'pagination offset', 3 === $p['total'] && 1 === count( $p['rows'] ) );
t( 'export (limit 0) returns all', 3 === count( R::query( array( 'limit' => 0 ) )['rows'] ) );
$c = R::counts();
t( 'counts all=3, in_transit=1, failed=1', 3 === $c['all'] && 1 === $c['in_transit'] && 1 === $c['failed'] );

// Stale in-progress sweep.
$wpdb->update( Schema::shipments_table(), array( 'last_attempt_at' => gmdate( 'Y-m-d H:i:s', time() - 7200 ) ), array( 'order_id' => 103 ) );
R::mark_stale_in_progress_uncertain( 600 );
t( 'stale in_progress => uncertain (never retryable)', 'uncertain' === R::find_by_order( 103 )['state'] );

// Tracking due list.
t( 'due_for_tracking finds booked, non-final', 1 === count( R::due_for_tracking( 1 ) ) );

// Logs: redaction + filters + purge.
Logger::log( array( 'event_type' => 'rate_request', 'success' => true, 'order_id' => 101, 'endpoint' => 'calculate-shipment', 'http_code' => 200, 'message' => 'ok', 'context' => array( 'receiver' => array( 'name' => 'Jane', 'email' => 'jane@example.com', 'city' => 'London' ) ) ) );
Logger::log( array( 'event_type' => 'shipment_create', 'success' => false, 'order_id' => 102, 'message' => 'Invalid shipping fee', 'http_code' => 400 ) );
$raw = $wpdb->get_var( 'SELECT context FROM ' . Schema::logs_table() . " WHERE event_type='rate_request'" );
t( 'log context redacted in DB', false === strpos( $raw, 'Jane' ) && false === strpos( $raw, 'jane@example.com' ) && false !== strpos( $raw, 'London' ) );
t( 'logs: all = 2', 2 === LogRepository::query( array( 'limit' => 25 ) )['total'] );
t( 'logs: failure filter', 1 === LogRepository::query( array( 'status' => 'failure' ) )['total'] );
t( 'logs: event filter', 1 === LogRepository::query( array( 'event_type' => 'rate_request' ) )['total'] );
t( 'logs: order filter', 1 === LogRepository::query( array( 'order' => '102' ) )['total'] );
t( 'logs: date filter', 2 === LogRepository::query( array( 'date_from' => gmdate( 'Y-m-d' ), 'date_to' => gmdate( 'Y-m-d' ) ) )['total'] );
$wpdb->query( 'UPDATE ' . Schema::logs_table() . " SET created_at='2000-01-01 00:00:00' WHERE event_type='rate_request'" );
Logger::purge_old();
t( 'purge removes old logs only', 1 === LogRepository::query( array() )['total'] );
LogRepository::clear();
t( 'clear empties logs', 0 === LogRepository::query( array() )['total'] );

$wpdb->query( 'DELETE FROM ' . Schema::shipments_table() );
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";

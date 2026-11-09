<?php
use ParcelFlow\WooCommerce\Data\ShipmentRepository as R;
use ParcelFlow\WooCommerce\Data\Schema;
use ParcelFlow\WooCommerce\Settings\Settings;

global $wpdb;
$wpdb->query( 'DELETE FROM ' . Schema::shipments_table() );
$wpdb->query( 'DELETE FROM ' . Schema::logs_table() );

// Settings: enabled, with a token, so the plugin is "live".
Settings::save_tab( 'general', array( 'enabled' => 'yes', 'base_url' => 'https://parcelflow-admin-staging-f10dbf53d922.herokuapp.com/', 'access_token' => 'SECRET_TOKEN_VALUE_123', 'environment' => 'staging', 'enable_checkout_rates' => 'yes' ) );

$a = get_user_by( 'login', 'custA' );
$p = new WC_Product_Simple();
$p->set_name( 'Test shoe' ); $p->set_regular_price( 50 ); $p->set_weight( 1 ); $p->set_length( 10 ); $p->set_width( 10 ); $p->set_height( 10 ); $p->save();

$order = wc_create_order( array( 'customer_id' => $a->ID ) );
$order->add_product( $p, 1 );
$order->set_billing_email( 'a@x.co' ); $order->set_billing_first_name( 'Alice' );
$ship = new WC_Order_Item_Shipping();
$ship->set_method_title( 'DHL' ); $ship->set_method_id( 'parcelflow' ); $ship->set_total( 10 );
$ship->add_meta_data( '_parcelflow_carrier_id', '176' );
$ship->add_meta_data( '_parcelflow_carrier_name', 'DHL <script>alert(1)</script>' );
$ship->add_meta_data( '_parcelflow_selling_price', '10' );
$order->add_item( $ship );
$order->calculate_totals();
$order->set_status( 'processing' );
$order->save();
echo 'ORDER_ID=' . $order->get_id() . ' KEY=' . $order->get_order_key() . "\n";

// Hostile API-derived data in a shipment row.
R::update( $order->get_id(), array(
	'state'           => 'booked',
	'shipment_number' => 'PF-TEST-1',
	'tracking_number' => 'TRK<img src=x onerror=alert(1)>',
	'carrier_name'    => '<script>alert("carrier")</script>',
	'status'          => '"><svg onload=alert(1)>',
	'label_url'       => 'javascript:alert(document.cookie)',
	'events_json'     => wp_json_encode( array( array( 'id' => '1', 'timestamp' => '2026-01-01', 'description' => '<script>alert("evt")</script>' ) ) ),
) );
ParcelFlow\WooCommerce\Support\Logger::log( array( 'event_type' => 'tracking', 'success' => false, 'message' => '<script>alert("log")</script> contact bob@evil.co token SECRET_TOKEN_VALUE_123', 'context' => array( 'x' => '<img src=x onerror=alert(2)>', 'Authorization' => 'Bearer SECRET_TOKEN_VALUE_123' ) ) );

// Second customer's order for isolation tests (no shipment needed).
$b = get_user_by( 'login', 'custB' );
$o2 = wc_create_order( array( 'customer_id' => $b->ID ) );
$o2->add_product( $p, 1 ); $o2->save();
echo 'ORDER_B=' . $o2->get_id() . "\n";
echo 'PAGES myaccount=' . wc_get_page_id( 'myaccount' ) . "\n";

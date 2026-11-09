<?php
// phpcs:disable
use ParcelFlow\WooCommerce\Api\Client;
use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Redactor;

final class ApiClientTest extends BaseTestCase {

	public function test_request_shape() {
		$this->respond( 200, '{"success":true,"data":{"shipment_number":"PF-1"}}' );
		$res = ( new Client() )->create_shipment( array( 'booking' => array( 'rate_id' => 'R' ) ), array( 'order_id' => 5 ) );
		$this->assertTrue( $res->success );
		$this->assertSame( 'https://api.example.test/engine/v1/apis/shipments/create-shipment', $GLOBALS['pf_last'][0] );
		$this->assertSame( 'tok_SECRET_123', $GLOBALS['pf_last'][1]['headers']['ACCESS_TOKEN'] );
		$this->assertIsArray( $GLOBALS['pf_last'][1]['body'] );
		$this->assertSame( 0, $GLOBALS['pf_last'][1]['redirection'] );
	}

	public function test_definitive_rejection_is_retry_safe() {
		$this->respond( 400, '{"message":"Invalid shipping fee","success":false}' );
		$res = ( new Client() )->create_shipment( array() );
		$this->assertTrue( $res->is_definitive_rejection() );
		$this->assertTrue( $res->is_safe_to_retry() );
		$this->assertSame( 'Invalid shipping fee', $res->message );
	}

	public function test_server_error_is_uncertain() {
		$this->respond( 500, '{"message":"boom","success":false}' );
		$res = ( new Client() )->create_shipment( array() );
		$this->assertFalse( $res->success );
		$this->assertFalse( $res->is_safe_to_retry() );
	}

	public function test_malformed_body_is_uncertain() {
		$this->respond( 200, '<html>gateway</html>' );
		$res = ( new Client() )->create_shipment( array() );
		$this->assertTrue( $res->malformed );
		$this->assertFalse( $res->is_safe_to_retry() );
	}

	public function test_timeout_is_uncertain_but_dns_failure_is_not() {
		$GLOBALS['pf_http'] = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 20001 milliseconds' );
		$res                = ( new Client() )->create_shipment( array() );
		$this->assertTrue( $res->transport_error );
		$this->assertFalse( $res->is_safe_to_retry() );

		$GLOBALS['pf_http'] = new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host: api.example.test' );
		$this->assertTrue( ( new Client() )->create_shipment( array() )->is_safe_to_retry() );
	}

	public function test_missing_token_and_insecure_url_are_blocked_before_sending() {
		$GLOBALS['pf_last'] = null;
		$res                = ( new Client( 'https://api.example.test/', '' ) )->calculate_shipment( array() );
		$this->assertTrue( $res->not_sent );
		$res = ( new Client( 'http://api.example.test/', 'x' ) )->calculate_shipment( array() );
		$this->assertTrue( $res->not_sent );
		$this->assertNull( $GLOBALS['pf_last'] );
	}

	public function test_token_and_pii_never_reach_logs() {
		$this->respond( 200, '{"success":true,"data":{"carriers":[]}}' );
		$GLOBALS['pf_opts'][ Settings::OPTION ]['debug_logging'] = 'yes';
		Settings::flush_cache();
		( new Client() )->calculate_shipment(
			array(
				'receiver' => array(
					'name'  => 'Jane Smith',
					'email' => 'jane@example.com',
					'city'  => 'London',
				),
			)
		);
		$this->assertStringNotContainsString( 'tok_SECRET_123', wp_json_encode( $GLOBALS['pf_logs'] ) );

		$red = Redactor::redact(
			array(
				'receiver' => array(
					'name'  => 'Jane',
					'email' => 'j@x.co',
					'city'  => 'London',
				),
				'note'     => 'tok_SECRET_123 a@b.co',
			)
		);
		$this->assertSame( '[redacted]', $red['receiver']['name'] );
		$this->assertSame( 'London', $red['receiver']['city'] );
		$this->assertStringNotContainsString( 'SECRET', $red['note'] );
		$this->assertStringNotContainsString( 'a@b.co', $red['note'] );
	}
}

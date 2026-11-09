<?php
// phpcs:disable
use ParcelFlow\WooCommerce\Api\RateParser;
use ParcelFlow\WooCommerce\Api\Response;
use ParcelFlow\WooCommerce\Settings\Crypto;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;
use ParcelFlow\WooCommerce\Tracking\TrackingService;

final class ParsingTest extends BaseTestCase {

	private function rate_response() {
		$r          = new Response();
		$r->success = true;
		$r->body    = json_decode( '{"data":{"carriers":[{"carrier_logo":"https://admin.parcelflow.io/x.svg","carrier_id":176,"carrier_name":"DHL Shipment","delivery_time":"3 to 5 working days","weight":"0.8","additional_info":"","selling_price":"64,803.24","pickup_fee":0,"carrier_cost_price":"59,892.09"},{"carrier_id":9,"carrier_name":"Bad","selling_price":"n/a"}]},"success":true}', true );
		return $r;
	}

	public function test_money_parsing() {
		$this->assertSame( 64803.24, Format::money( '64,803.24' ) );
		$this->assertNull( Format::money( 'abc' ) );
		$this->assertNull( Format::money( '-5' ) );
		$this->assertNull( Format::money( '' ) );
	}

	public function test_alnum_strips_punctuation() {
		$this->assertSame( 'Men s Black Shoes size 42', Format::alnum( "Men's <b>Black</b> Shoes (size 42)!" ) );
	}

	public function test_crypto_round_trip_hides_plaintext() {
		$enc = Crypto::encrypt( 'tok_SECRET_123' );
		$this->assertSame( 'tok_SECRET_123', Crypto::decrypt( $enc ) );
		$this->assertStringNotContainsString( 'SECRET', $enc );
		$this->assertSame( '', Crypto::decrypt( 'garbage' ) );
	}

	public function test_rate_parser_drops_invalid_and_cost_price() {
		$rates = RateParser::parse( $this->rate_response() );
		$this->assertCount( 1, $rates );
		$this->assertSame( 64803.24, $rates[0]['selling_price'] );
		$this->assertArrayNotHasKey( 'carrier_cost_price', $rates[0] );
	}

	public function test_rate_id_is_never_fabricated() {
		$res = $this->rate_response();
		$this->assertSame( '', RateParser::parse( $res )[0]['rate_id'] );
		$res->body['data']['carriers'][0]['rate_id'] = 'RATE_DHL_101';
		$this->assertSame( 'RATE_DHL_101', RateParser::parse( $res )[0]['rate_id'] );
	}

	public function test_status_groups() {
		$this->assertSame( 'delivered', Status::group( 'booked', 'Delivered' ) );
		$this->assertSame( 'in_transit', Status::group( 'booked', 'In Transit' ) );
		$this->assertSame( 'processing', Status::group( 'booked', 'Created' ) );
		$this->assertSame( 'failed', Status::group( 'booked', 'Cancelled' ) );
		$this->assertSame( 'failed', Status::group( 'uncertain', '' ) );
		$this->assertSame( 'pending', Status::group( 'awaiting', '' ) );
		$this->assertTrue( Status::is_final( 'booked', 'Delivered' ) );
		$this->assertFalse( Status::is_final( 'booked', 'In Transit' ) );
	}

	public function test_tracking_events_sorted_and_empty_status_kept() {
		$t       = new Response();
		$t->body = json_decode( '{"data":{"status":"","events":[{"id":3,"timestamp":"2026-08-21 16:04:29","description":"Picked Up"},{"id":1,"timestamp":"2026-08-21 14:33:38","description":"Created"}]},"success":true}', true );
		$p       = TrackingService::parse( $t );
		$this->assertSame( 'Created', $p['events'][0]['description'] );
		$this->assertSame( 'Picked Up', $p['events'][1]['description'] );
		$this->assertSame( '', $p['status'] );
	}
}

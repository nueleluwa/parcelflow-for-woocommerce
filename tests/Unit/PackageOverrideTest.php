<?php
// phpcs:disable
use ParcelFlow\WooCommerce\Booking\BookingService;

final class PackageOverrideTest extends BaseTestCase {

	public function test_no_override_is_null_and_valid() {
		$r = BookingService::package_override( array() );
		$this->assertNull( $r['values'] );
		$this->assertSame( array(), $r['errors'] );

		$r = BookingService::package_override( array( 'package' => array( 'weight' => '', 'length' => '', 'width' => '', 'height' => '' ) ) );
		$this->assertNull( $r['values'] );
		$this->assertSame( array(), $r['errors'] );
	}

	public function test_complete_valid_override_is_accepted_and_rounded() {
		$r = BookingService::package_override( array( 'package' => array( 'weight' => '2.5', 'length' => '30', 'width' => '20.1234', 'height' => '15' ) ) );
		$this->assertSame( array(), $r['errors'] );
		$this->assertSame( 2.5, $r['values']['weight'] );
		$this->assertSame( 20.123, $r['values']['width'] );
	}

	/**
	 * @dataProvider invalid_overrides
	 */
	public function test_partial_or_invalid_override_is_rejected( array $pkg ) {
		$r = BookingService::package_override( array( 'package' => $pkg ) );
		$this->assertNull( $r['values'] );
		$this->assertNotEmpty( $r['errors'] );
	}

	public function invalid_overrides() {
		return array(
			'partial'       => array( array( 'weight' => '2', 'length' => '', 'width' => '', 'height' => '' ) ),
			'zero'          => array( array( 'weight' => '0', 'length' => '1', 'width' => '1', 'height' => '1' ) ),
			'negative'      => array( array( 'weight' => '-1', 'length' => '1', 'width' => '1', 'height' => '1' ) ),
			'not numeric'   => array( array( 'weight' => 'abc', 'length' => '1', 'width' => '1', 'height' => '1' ) ),
			'absurd'        => array( array( 'weight' => '1', 'length' => '999999', 'width' => '1', 'height' => '1' ) ),
			'script string' => array( array( 'weight' => '<script>', 'length' => '1', 'width' => '1', 'height' => '1' ) ),
		);
	}
}

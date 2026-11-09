<?php
// phpcs:disable
use ParcelFlow\WooCommerce\Settings\Crypto;
use ParcelFlow\WooCommerce\Settings\Settings;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pf_opts'] = array(
			Settings::OPTION       => array(
				'base_url'         => 'https://api.example.test/',
				'auth_header_name' => 'ACCESS_TOKEN',
			),
			Settings::TOKEN_OPTION => Crypto::encrypt( 'tok_SECRET_123' ),
		);
		$GLOBALS['pf_logs'] = array();
		Settings::flush_cache();
	}

	protected function respond( $code, $body ) {
		$GLOBALS['pf_http'] = array(
			'code' => $code,
			'body' => $body,
		);
	}
}

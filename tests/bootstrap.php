<?php
/**
 * Unit-test bootstrap. These tests exercise pure logic with minimal WordPress function
 * stubs; they do NOT replace integration tests inside WordPress/WooCommerce.
 *
 * @package ParcelFlow\WooCommerce
 */

// phpcs:disable -- test stubs deliberately mimic global WordPress functions.

define( 'ABSPATH', __DIR__ . '/' );
define( 'PARCELFLOW_WC_VERSION', 'test' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['pf_opts'] = array();
$GLOBALS['pf_http'] = null;
$GLOBALS['pf_last'] = null;
$GLOBALS['pf_logs'] = array();

function __( $s ) { return $s; }
function _x( $s ) { return $s; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['pf_opts'] ) ? $GLOBALS['pf_opts'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['pf_opts'][ $k ] = $v; return true; }
function get_woocommerce_currency() { return 'NGN'; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return trim( $s ); }
function esc_url_raw( $u, $p = null ) { return preg_match( '#^https?://#', $u ) ? $u : ''; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_salt() { return 'test-salt'; }
function wp_http_validate_url( $u ) { return (bool) filter_var( $u, FILTER_VALIDATE_URL ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function apply_filters( $t, $v ) { return $v; }
function home_url() { return 'https://shop.test'; }
function add_query_arg( $a, $u ) { return $u . '?' . http_build_query( $a ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_post( $u, $a ) { $GLOBALS['pf_last'] = array( $u, $a ); return $GLOBALS['pf_http']; }
function wp_remote_get( $u, $a ) { $GLOBALS['pf_last'] = array( $u, $a ); return $GLOBALS['pf_http']; }
function wp_parse_args( $a, $d ) { return array_merge( $d, $a ); }

class WP_Error {
	private $m;
	public function __construct( $c, $m ) { $this->m = $m; }
	public function get_error_message() { return $this->m; }
}

require dirname( __DIR__ ) . '/src/Autoloader.php';
\ParcelFlow\WooCommerce\Autoloader::register( dirname( __DIR__ ) . '/src/' );
require __DIR__ . '/stubs/Logger.php';
require __DIR__ . '/Unit/BaseTestCase.php';

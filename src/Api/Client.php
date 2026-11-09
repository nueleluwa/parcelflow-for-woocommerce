<?php
/**
 * ParcelFlow Shipments API client.
 *
 * Only the five documented endpoints are implemented. All calls are server-side via the
 * WordPress HTTP API. The access token is sent only as a request header and never logged.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Api;

use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Logger;

defined( 'ABSPATH' ) || exit;

class Client {

	const MODULE_PATH = 'engine/v1/apis/shipments/';

	/** @var string */
	private $base_url;
	/** @var string */
	private $token;
	/** @var int */
	private $timeout;
	/** @var string */
	private $header;

	public function __construct( $base_url = null, $token = null, $timeout = null ) {
		$this->base_url = null !== $base_url ? $base_url : (string) Settings::get( 'base_url' );
		$this->token    = null !== $token ? $token : Settings::get_token();
		$this->timeout  = null !== $timeout ? (int) $timeout : (int) Settings::get( 'api_timeout', 20 );
		$this->header   = (string) Settings::get( 'auth_header_name', 'ACCESS_TOKEN' );
	}

	/* ------------------------- Documented endpoints ---------------------- */

	/** POST /calculate-shipment */
	public function calculate_shipment( array $payload, array $ctx = array() ) {
		return $this->request( 'POST', 'calculate-shipment', $payload, 'rate_request', $ctx );
	}

	/** POST /create-shipment */
	public function create_shipment( array $payload, array $ctx = array() ) {
		return $this->request( 'POST', 'create-shipment', $payload, 'shipment_create', $ctx );
	}

	/** POST /get-shipment-estimate */
	public function get_shipment_estimate( array $payload, array $ctx = array() ) {
		return $this->request( 'POST', 'get-shipment-estimate', $payload, 'estimate', $ctx );
	}

	/**
	 * GET /get-shipment-history
	 *
	 * @param string $start  Y-m-d.
	 * @param string $end    Y-m-d.
	 * @param string $status Status filter or "All".
	 */
	public function get_shipment_history( $start, $end, $status = 'All', $limit = 50, $offset = 0, array $ctx = array() ) {
		$query = array(
			'start-date' => $start,
			'end-date'   => $end,
			'status'     => $status ? $status : 'All',
			'limit'      => (int) $limit,
			'offset'     => (int) $offset,
		);
		return $this->request( 'GET', 'get-shipment-history', $query, 'history', $ctx );
	}

	/** GET /track-shipment */
	public function track_shipment( $shipment_number, array $ctx = array() ) {
		$ctx['shipment_number'] = $shipment_number;
		return $this->request( 'GET', 'track-shipment', array( 'shipment-number' => $shipment_number ), 'tracking', $ctx );
	}

	/* ----------------------------- Transport ----------------------------- */

	/**
	 * @param string $method     GET|POST.
	 * @param string $path       Endpoint relative to the module path.
	 * @param array  $params     Body (POST) or query (GET).
	 * @param string $event_type Log event type.
	 * @param array  $ctx        order_id / shipment_number for the log.
	 * @return Response
	 */
	public function request( $method, $path, array $params, $event_type, array $ctx = array() ) {
		$res = new Response();

		$guard = $this->guard();
		if ( null !== $guard ) {
			$res->message         = $guard;
			$res->transport_error = true;
			$res->not_sent        = true;
			$this->log( $event_type, $path, $res, $params, $ctx );
			return $res;
		}

		$url  = trailingslashit( $this->base_url ) . self::MODULE_PATH . $path;
		$args = array(
			'method'             => $method,
			'timeout'            => $this->timeout,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
			'user-agent'         => 'ParcelFlow-for-WooCommerce/' . PARCELFLOW_WC_VERSION . '; ' . home_url(),
			'headers'            => array(
				$this->header => $this->token,
				'Accept'      => 'application/json',
			),
		);

		if ( 'GET' === $method ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), $url );
		} else {
			// Array body => application/x-www-form-urlencoded with bracketed nested keys
			// (e.g. package[packages][0][package_weight]) as documented.
			$args['body'] = $params;
		}

		$raw = ( 'GET' === $method ) ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );

		if ( is_wp_error( $raw ) ) {
			$res->transport_error = true;
			$res->message         = $this->friendly_transport_message( $raw );
			$res->not_sent        = $this->looks_unsent( $raw );
			$this->log( $event_type, $path, $res, $params, $ctx, $raw->get_error_message() );
			return $res;
		}

		$res->http_code = (int) wp_remote_retrieve_response_code( $raw );
		$body_str       = wp_remote_retrieve_body( $raw );
		$decoded        = json_decode( $body_str, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$res->malformed = true;
			$res->message   = sprintf(
				/* translators: %d: HTTP status code */
				__( 'ParcelFlow returned an unreadable response (HTTP %d).', 'parcelflow-for-woocommerce' ),
				$res->http_code
			);
			$this->log( $event_type, $path, $res, $params, $ctx, null, substr( (string) $body_str, 0, 2000 ) );
			return $res;
		}

		$res->body    = $decoded;
		$res->success = $res->http_code >= 200 && $res->http_code < 300 && isset( $decoded['success'] ) && true === $decoded['success'];

		if ( $res->success ) {
			$res->message = isset( $decoded['message'] ) && is_string( $decoded['message'] ) ? $decoded['message'] : '';
		} elseif ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) && '' !== $decoded['message'] ) {
			$res->message = $decoded['message'];
		} else {
			$res->message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'ParcelFlow request failed (HTTP %d).', 'parcelflow-for-woocommerce' ),
				$res->http_code
			);
		}

		$this->log( $event_type, $path, $res, $params, $ctx, null, $decoded );
		return $res;
	}

	/**
	 * @return string|null Error message when the client cannot make a request.
	 */
	private function guard() {
		if ( '' === $this->token ) {
			return __( 'No ParcelFlow access token is configured.', 'parcelflow-for-woocommerce' );
		}
		if ( '' === $this->base_url || ! wp_http_validate_url( $this->base_url ) ) {
			return __( 'The ParcelFlow API base URL is missing or invalid.', 'parcelflow-for-woocommerce' );
		}
		$scheme = wp_parse_url( $this->base_url, PHP_URL_SCHEME );
		if ( 'https' !== $scheme && ! apply_filters( 'parcelflow_wc_allow_insecure_api_url', false ) ) {
			return __( 'The ParcelFlow API base URL must use HTTPS so the access token is not sent in clear text.', 'parcelflow-for-woocommerce' );
		}
		return null;
	}

	private function friendly_transport_message( \WP_Error $e ) {
		$msg = $e->get_error_message();
		if ( false !== stripos( $msg, 'timed out' ) || false !== stripos( $msg, 'cURL error 28' ) ) {
			return __( 'The request to ParcelFlow timed out.', 'parcelflow-for-woocommerce' );
		}
		return __( 'Could not connect to ParcelFlow.', 'parcelflow-for-woocommerce' );
	}

	/**
	 * Heuristic for "the request never left / never connected": DNS failure, refused
	 * connection, or SSL handshake failure. Anything else (notably timeouts and
	 * connection resets) is treated as possibly-delivered.
	 */
	private function looks_unsent( \WP_Error $e ) {
		$msg = $e->get_error_message();
		return (bool) preg_match( '/cURL error (6|7|35|51|58|60):|could not resolve host|connection refused|a valid URL was not provided/i', $msg );
	}

	private function log( $event_type, $path, Response $res, array $params, array $ctx, $raw_error = null, $response_body = null ) {
		$debug   = Settings::is( 'debug_logging' );
		$context = array();
		if ( $debug ) {
			$context['request']  = $params;
			$context['response'] = $response_body;
			if ( $raw_error ) {
				$context['transport_error'] = $raw_error;
			}
		} elseif ( $raw_error ) {
			$context['transport_error'] = $raw_error;
		}
		Logger::log(
			array(
				'event_type'      => $event_type,
				'success'         => $res->success,
				'order_id'        => isset( $ctx['order_id'] ) ? $ctx['order_id'] : null,
				'shipment_number' => isset( $ctx['shipment_number'] ) ? $ctx['shipment_number'] : '',
				'endpoint'        => $path,
				'http_code'       => $res->http_code,
				'message'         => $res->message,
				'context'         => $context,
			)
		);
	}
}

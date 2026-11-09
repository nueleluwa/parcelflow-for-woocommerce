<?php
/**
 * Normalised result of a ParcelFlow API call.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Api;

defined( 'ABSPATH' ) || exit;

final class Response {

	/** @var bool  True only for a 2xx response whose JSON body has success === true. */
	public $success = false;
	/** @var int|null */
	public $http_code = null;
	/** @var array|null Decoded JSON body (null when absent/malformed). */
	public $body = null;
	/** @var string Safe, human-readable message. */
	public $message = '';
	/** @var bool  WP_Error from the transport layer (timeout, DNS, TLS...). */
	public $transport_error = false;
	/** @var bool  Transport failed before any request could have reached the server. */
	public $not_sent = false;
	/** @var bool  Body was not valid JSON. */
	public $malformed = false;

	/**
	 * Convenience accessor for $body['data'].
	 *
	 * @return mixed
	 */
	public function data() {
		return is_array( $this->body ) && array_key_exists( 'data', $this->body ) ? $this->body['data'] : null;
	}

	/**
	 * The server explicitly and definitively refused the request (documented 4xx with
	 * success=false). Nothing was created, so retrying after a fix is safe.
	 */
	public function is_definitive_rejection() {
		if ( $this->success || $this->transport_error || $this->malformed || null === $this->body ) {
			return false;
		}
		return $this->http_code >= 400 && $this->http_code < 500 && isset( $this->body['success'] ) && false === $this->body['success'];
	}

	/**
	 * The request definitely never reached the server (e.g. DNS failure, connection
	 * refused). Also safe to retry.
	 */
	public function is_safe_to_retry() {
		return $this->is_definitive_rejection() || ( $this->transport_error && $this->not_sent );
	}
}

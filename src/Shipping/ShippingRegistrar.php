<?php
/**
 * Hooks that register the shipping method and tune checkout presentation.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Shipping;

use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Format;

defined( 'ABSPATH' ) || exit;

final class ShippingRegistrar {

	public function register() {
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_contact_state_to_packages' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'rich_label' ), 10, 2 );
		add_filter( 'woocommerce_no_shipping_available_html', array( $this, 'no_rates_message' ) );
		add_filter( 'woocommerce_cart_no_shipping_available_html', array( $this, 'no_rates_message' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_method( $methods ) {
		$methods[ ShippingMethod::METHOD_ID ] = ShippingMethod::class;
		return $methods;
	}

	/**
	 * WooCommerce caches rates per package hash. Receiver name/phone/e-mail are not part of
	 * a package, so without this a first (blocked, incomplete) calculation would be cached
	 * and rates would not refresh once the customer finishes the form. We add only a
	 * completeness flag, so the hash changes once when the contact details become valid
	 * and not on every keystroke.
	 */
	public function add_contact_state_to_packages( $packages ) {
		if ( ! Settings::is_configured() || ! WC()->customer ) {
			return $packages;
		}
		$c     = WC()->customer;
		$name  = trim( $c->get_shipping_first_name() . $c->get_shipping_last_name() . $c->get_billing_first_name() . $c->get_billing_last_name() );
		$phone = $c->get_shipping_phone() . $c->get_billing_phone();
		$flag  = ( '' !== $name ? '1' : '0' ) . ( '' !== trim( $phone ) ? '1' : '0' ) . ( is_email( $c->get_billing_email() ) ? '1' : '0' );
		foreach ( $packages as $i => $package ) {
			$packages[ $i ]['parcelflow_contact_state'] = $flag;
		}
		return $packages;
	}

	/**
	 * Classic checkout/cart: carrier logo + delivery estimate + info. (Block checkout renders
	 * plain labels, which already contain the carrier name and delivery estimate.)
	 */
	public function rich_label( $label, $method ) {
		if ( 0 !== strpos( $method->get_id(), ShippingMethod::METHOD_ID . ':' ) ) {
			return $label;
		}
		$meta = array();
		foreach ( $method->get_meta_data() as $k => $v ) {
			$meta[ $k ] = $v;
		}
		$name = isset( $meta['_parcelflow_carrier_name'] ) ? $meta['_parcelflow_carrier_name'] : $method->get_label();
		$html = '<span class="parcelflow-rate">';
		if ( Settings::is( 'show_carrier_logos' ) && ! empty( $meta['_parcelflow_carrier_logo'] ) && Format::safe_url( $meta['_parcelflow_carrier_logo'] ) ) {
			$html .= '<img class="parcelflow-rate__logo" src="' . esc_url( $meta['_parcelflow_carrier_logo'] ) . '" alt="" width="28" height="28" loading="lazy" decoding="async" /> ';
		}
		$html .= '<span class="parcelflow-rate__name">' . esc_html( $name ) . '</span>';
		if ( ! empty( $meta['_parcelflow_delivery_time'] ) ) {
			$html .= ' <span class="parcelflow-rate__eta">' . esc_html( $meta['_parcelflow_delivery_time'] ) . '</span>';
		}
		if ( ! empty( $meta['_parcelflow_additional_info'] ) ) {
			$html .= ' <small class="parcelflow-rate__info">' . esc_html( $meta['_parcelflow_additional_info'] ) . '</small>';
		}
		$html .= '</span>';

		$pos = strpos( $label, $method->get_label() );
		if ( false === $pos ) {
			return $label;
		}
		return substr_replace( $label, $html, $pos, strlen( $method->get_label() ) );
	}

	public function no_rates_message( $html ) {
		if ( '' !== ShippingMethod::$notice && Settings::is_configured() ) {
			return wpautop( esc_html( ShippingMethod::$notice ) );
		}
		return $html;
	}

	public function enqueue() {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			wp_enqueue_style( 'parcelflow-frontend', PARCELFLOW_WC_URL . 'assets/css/frontend.css', array(), PARCELFLOW_WC_VERSION );
		}
	}
}

<?php
/**
 * Product-level ParcelFlow/customs metadata (HS code, declared value, customs description).
 * Weight and dimensions use the native WooCommerce fields.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Products;

defined( 'ABSPATH' ) || exit;

final class ProductFields {

	const HS_CODE        = '_parcelflow_hs_code';
	const DECLARED_VALUE = '_parcelflow_declared_value';
	const DESCRIPTION    = '_parcelflow_customs_description';

	public function register() {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_fields' ) );
	}

	public function render_fields() {
		echo '<div class="options_group parcelflow-product-fields">';
		woocommerce_wp_text_input(
			array(
				'id'          => self::HS_CODE,
				'label'       => __( 'HS tariff code', 'parcelflow-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Harmonized System code sent to ParcelFlow for customs (e.g. 6403.99). Required by the API for every item.', 'parcelflow-for-woocommerce' ),
				'placeholder' => '6403.99',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::DECLARED_VALUE,
				'label'       => __( 'ParcelFlow declared value (per unit)', 'parcelflow-for-woocommerce' ),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => __( 'Optional. Overrides the declared value per unit sent to ParcelFlow. Leave blank to use the store setting.', 'parcelflow-for-woocommerce' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::DESCRIPTION,
				'label'       => __( 'Customs description', 'parcelflow-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Optional. Short plain description (letters and numbers only) used instead of the product description.', 'parcelflow-for-woocommerce' ),
			)
		);
		echo '</div>';
	}

	/**
	 * @param \WC_Product $product Product being saved (nonce/capability verified by WooCommerce).
	 */
	public function save_fields( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product save nonce.
		$hs = isset( $_POST[ self::HS_CODE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::HS_CODE ] ) ) : '';
		$hs = preg_replace( '/[^0-9.\-\s]/', '', $hs );
		$product->update_meta_data( self::HS_CODE, trim( $hs ) );

		$dv = isset( $_POST[ self::DECLARED_VALUE ] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST[ self::DECLARED_VALUE ] ) ) ) : '';
		$product->update_meta_data( self::DECLARED_VALUE, '' === $dv || (float) $dv < 0 ? '' : $dv );

		$desc = isset( $_POST[ self::DESCRIPTION ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::DESCRIPTION ] ) ) : '';
		$product->update_meta_data( self::DESCRIPTION, $desc );
		// phpcs:enable
	}

	/**
	 * Variations inherit the parent's customs data unless they set their own.
	 */
	private static function meta( \WC_Product $product, $key ) {
		$v = $product->get_meta( $key );
		if ( ( '' === $v || null === $v ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			$v      = $parent ? $parent->get_meta( $key ) : '';
		}
		return is_string( $v ) ? $v : '';
	}

	public static function get_hs_code( \WC_Product $product ) {
		return trim( self::meta( $product, self::HS_CODE ) );
	}

	/** @return float|null */
	public static function get_declared_value( \WC_Product $product ) {
		$v = self::meta( $product, self::DECLARED_VALUE );
		return '' === $v ? null : (float) $v;
	}

	public static function get_customs_description( \WC_Product $product ) {
		return trim( self::meta( $product, self::DESCRIPTION ) );
	}
}

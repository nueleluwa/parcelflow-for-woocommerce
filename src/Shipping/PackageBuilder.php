<?php
/**
 * Turns WooCommerce cart-package or order line items into normalised shipment lines and a
 * single carton (weight + dimensions).
 *
 * Strategy notes:
 *  - Weight = sum(unit weight x qty) + configured packaging weight.
 *  - Dimensions are NEVER added together blindly. Either a fixed carton is configured, or a
 *    documented estimate is used: longest length, widest width, heights stacked.
 *  - Missing weight / HS code / dimensions produce validation errors; nothing is fabricated
 *    unless the administrator configured an explicit fallback.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Shipping;

use ParcelFlow\WooCommerce\Products\ProductFields;
use ParcelFlow\WooCommerce\Settings\Settings;
use ParcelFlow\WooCommerce\Support\Format;

defined( 'ABSPATH' ) || exit;

final class PackageBuilder {

	/**
	 * @param array $package WooCommerce shipping package ($package['contents']).
	 * @return array{lines:array,errors:string[]}
	 */
	public static function from_cart_package( array $package ) {
		$entries = array();
		foreach ( (array) $package['contents'] as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof \WC_Product || ! $item['data']->needs_shipping() ) {
				continue;
			}
			$qty       = max( 1, (int) $item['quantity'] );
			$line_paid = isset( $item['line_total'] ) ? (float) $item['line_total'] : null;
			$entries[] = array( $item['data'], $qty, null === $line_paid ? null : $line_paid / $qty );
		}
		return self::build( $entries );
	}

	/**
	 * @return array{lines:array,errors:string[]}
	 */
	public static function from_order( \WC_Order $order ) {
		$entries = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}
			$qty       = max( 1, (int) $item->get_quantity() );
			$entries[] = array( $product, $qty, (float) $item->get_total() / $qty );
		}
		return self::build( $entries );
	}

	/**
	 * @param array $entries List of [WC_Product, qty, paid unit price|null].
	 */
	private static function build( array $entries ) {
		$lines  = array();
		$errors = array();

		if ( ! $entries ) {
			return array(
				'lines'  => array(),
				'errors' => array( __( 'No shippable items.', 'parcelflow-for-woocommerce' ) ),
			);
		}

		$fallback_weight = Settings::get( 'fallback_item_weight' );
		$fallback_hs     = (string) Settings::get( 'default_hs_code' );
		$basis           = Settings::get( 'declared_value_basis' );

		foreach ( $entries as $entry ) {
			list( $product, $qty, $paid_unit ) = $entry;
			$name                              = $product->get_name();

			$weight = $product->get_weight() !== '' ? (float) wc_get_weight( (float) $product->get_weight(), 'kg' ) : 0.0;
			if ( $weight <= 0 ) {
				if ( '' !== (string) $fallback_weight && (float) $fallback_weight > 0 ) {
					$weight = (float) $fallback_weight;
				} else {
					/* translators: %s: product name */
					$errors[] = sprintf( __( '"%s" has no weight.', 'parcelflow-for-woocommerce' ), $name );
				}
			}

			$hs = ProductFields::get_hs_code( $product );
			if ( '' === $hs ) {
				$hs = $fallback_hs;
			}
			if ( '' === $hs ) {
				/* translators: %s: product name */
				$errors[] = sprintf( __( '"%s" has no HS tariff code.', 'parcelflow-for-woocommerce' ), $name );
			}

			$override = ProductFields::get_declared_value( $product );
			if ( null !== $override ) {
				$value = $override;
			} elseif ( 'line_total' === $basis && null !== $paid_unit ) {
				$value = $paid_unit;
			} else {
				$value = (float) $product->get_price();
			}
			if ( $value < 0 ) {
				$value = 0.0;
			}

			$desc = ProductFields::get_customs_description( $product );
			if ( '' === $desc ) {
				$desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
			}
			$desc = Format::alnum( $desc, 120 );
			if ( '' === $desc ) {
				$desc = Format::alnum( $name, 120 );
			}

			$image_id  = $product->get_image_id();
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : '';

			$lines[] = array(
				'product_id'  => $product->get_id(),
				'name'        => Format::alnum( $name, 120 ),
				'description' => $desc,
				'quantity'    => $qty,
				'value'       => round( $value, 2 ),
				'weight'      => $weight,
				'length'      => $product->get_length() !== '' ? (float) wc_get_dimension( (float) $product->get_length(), 'cm' ) : 0.0,
				'width'       => $product->get_width() !== '' ? (float) wc_get_dimension( (float) $product->get_width(), 'cm' ) : 0.0,
				'height'      => $product->get_height() !== '' ? (float) wc_get_dimension( (float) $product->get_height(), 'cm' ) : 0.0,
				'hs_code'     => $hs,
				'image_url'   => $image_url ? $image_url : '',
			);
		}

		return array(
			'lines'  => $lines,
			'errors' => array_values( array_unique( $errors ) ),
		);
	}

	/**
	 * Calculate the single carton for the given lines.
	 *
	 * @return array{weight:float,length:float,width:float,height:float,items:int,errors:string[]}
	 */
	public static function carton( array $lines ) {
		$errors = array();
		$weight = (float) Settings::get( 'packaging_weight', 0 );
		$items  = 0;
		foreach ( $lines as $l ) {
			$weight += $l['weight'] * $l['quantity'];
			$items  += $l['quantity'];
		}

		if ( 'fixed' === Settings::get( 'carton_strategy' ) ) {
			$len = (float) Settings::get( 'carton_length' );
			$wid = (float) Settings::get( 'carton_width' );
			$hei = (float) Settings::get( 'carton_height' );
		} else {
			$len = 0.0;
			$wid = 0.0;
			$hei = 0.0;
			foreach ( $lines as $l ) {
				if ( $l['length'] <= 0 || $l['width'] <= 0 || $l['height'] <= 0 ) {
					/* translators: %s: product name */
					$errors[] = sprintf( __( '"%s" is missing length, width or height.', 'parcelflow-for-woocommerce' ), $l['name'] );
					continue;
				}
				// Pack each unit flat: largest face as length/width, stack the smallest side.
				$dims = array( $l['length'], $l['width'], $l['height'] );
				rsort( $dims );
				$len  = max( $len, $dims[0] );
				$wid  = max( $wid, $dims[1] );
				$hei += $dims[2] * $l['quantity'];
			}
		}

		if ( $len <= 0 || $wid <= 0 || $hei <= 0 ) {
			if ( 'fixed' === Settings::get( 'carton_strategy' ) ) {
				$errors[] = __( 'Fixed carton dimensions are not configured.', 'parcelflow-for-woocommerce' );
			}
		}
		if ( $weight <= 0 ) {
			$errors[] = __( 'Total package weight is zero.', 'parcelflow-for-woocommerce' );
		}

		return array(
			'weight' => round( $weight, 3 ),
			'length' => round( $len, 2 ),
			'width'  => round( $wid, 2 ),
			'height' => round( $hei, 2 ),
			'items'  => $items,
			'errors' => array_values( array_unique( $errors ) ),
		);
	}
}

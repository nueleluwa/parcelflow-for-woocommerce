<?php
/**
 * ParcelFlow → Settings.
 *
 * @package ParcelFlow\WooCommerce
 */

namespace ParcelFlow\WooCommerce\Admin;

use ParcelFlow\WooCommerce\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public function render() {
		$tabs = Settings::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		$fields = array();
		foreach ( Settings::schema() as $key => $def ) {
			if ( $def['tab'] === $tab ) {
				$fields[ $key ] = $def;
			}
		}
		View::render(
			'settings',
			array(
				'tabs'           => $tabs,
				'tab'            => $tab,
				'fields'         => $fields,
				'values'         => Settings::all(),
				'has_token'      => Settings::has_token(),
				'token_constant' => Settings::token_from_constant(),
				'seen_carriers'  => (array) get_option( Settings::SEEN_CARRIERS, array() ),
				'disabled'       => array_map( 'strval', (array) Settings::get( 'disabled_carriers', array() ) ),
			)
		);
	}
}

<?php
/**
 * Plugin Name:       ParcelFlow for WooCommerce
 * Description:       Integrates ParcelFlow shipping services with WooCommerce, enabling real-time shipping rate calculations, carrier selection, shipment booking and delivery tracking.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Emmanuel Egwa, Marvelous Benson, Eureka
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       parcelflow-for-woocommerce
 * Domain Path:       /languages
 *
 * @package ParcelFlow\WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'PARCELFLOW_WC_VERSION', '0.1.0' );
define( 'PARCELFLOW_WC_FILE', __FILE__ );
define( 'PARCELFLOW_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PARCELFLOW_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'PARCELFLOW_WC_MIN_WC', '8.5' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ParcelFlow for WooCommerce requires PHP 7.4 or newer.', 'parcelflow-for-woocommerce' ) . '</p></div>';
		}
	);
	return;
}

require_once PARCELFLOW_WC_PATH . 'src/Autoloader.php';
\ParcelFlow\WooCommerce\Autoloader::register( PARCELFLOW_WC_PATH . 'src/' );

register_activation_hook( __FILE__, array( \ParcelFlow\WooCommerce\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ParcelFlow\WooCommerce\Activator::class, 'deactivate' ) );

// Must be registered before WooCommerce initialises.
add_action(
	'before_woocommerce_init',
	array( \ParcelFlow\WooCommerce\Compatibility::class, 'declare_features' )
);

\ParcelFlow\WooCommerce\Plugin::instance()->init();

<?php
/**
 * Help / API status view.
 *
 * @var array|false $connection
 * @var string      $base_url
 * @var array       $env
 * @var string      $tracking_url
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Support\Format;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$yes = static function ( $b ) {
	if ( $b ) {
		echo '<span class="pf-ok">' . esc_html__( 'Yes', 'parcelflow-for-woocommerce' ) . '</span>';
	} else {
		echo '<span class="pf-no">' . esc_html__( 'No', 'parcelflow-for-woocommerce' ) . '</span>';
	}
};
?>
<div class="wrap parcelflow-admin">
	<?php View::header( __( 'Help / API Status', 'parcelflow-for-woocommerce' ) ); ?>
	<hr class="wp-header-end" />

	<div class="pf-grid">
		<section class="pf-card">
			<h2><?php esc_html_e( 'API connection', 'parcelflow-for-woocommerce' ); ?></h2>
			<table class="pf-kv">
				<tr><th><?php esc_html_e( 'Base URL', 'parcelflow-for-woocommerce' ); ?></th><td><code><?php echo esc_html( $base_url ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Token stored', 'parcelflow-for-woocommerce' ); ?></th><td><?php $yes( $env['token'] ); ?><?php echo $env['token_const'] ? ' (wp-config)' : ''; ?></td></tr>
				<tr><th><?php esc_html_e( 'Last test', 'parcelflow-for-woocommerce' ); ?></th><td>
					<?php
					if ( is_array( $connection ) ) {
						if ( $connection['ok'] ) {
							echo '<span class="pf-ok">' . esc_html__( 'Connected', 'parcelflow-for-woocommerce' ) . '</span>';
						} else {
							echo '<span class="pf-no">' . esc_html__( 'Failed', 'parcelflow-for-woocommerce' ) . '</span>';
						}
						echo ' – ' . esc_html( human_time_diff( $connection['time'] ) . ' ' . __( 'ago', 'parcelflow-for-woocommerce' ) );
						if ( $connection['http'] ) {
							echo ' (HTTP ' . esc_html( $connection['http'] ) . ')';
						}
						if ( ! $connection['ok'] && $connection['message'] ) {
							echo '<br />' . esc_html( $connection['message'] );
						}
					} else {
						esc_html_e( 'Not tested', 'parcelflow-for-woocommerce' );
					}
					?>
				</td></tr>
			</table>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="parcelflow_wc_test_connection" />
				<?php wp_nonce_field( 'parcelflow_wc_test_connection' ); ?>
				<button class="button button-primary pf-btn-primary"><?php esc_html_e( 'Test connection', 'parcelflow-for-woocommerce' ); ?></button>
			</form>
			<p class="description"><?php esc_html_e( 'ParcelFlow documents no dedicated ping endpoint. The test makes one read-only call to GET /get-shipment-history for today with limit=1. It creates and changes nothing.', 'parcelflow-for-woocommerce' ); ?></p>
		</section>

		<section class="pf-card">
			<h2><?php esc_html_e( 'Environment', 'parcelflow-for-woocommerce' ); ?></h2>
			<table class="pf-kv">
				<tr><th>ParcelFlow plugin</th><td><?php echo esc_html( $env['plugin'] ); ?></td></tr>
				<tr><th>WordPress</th><td><?php echo esc_html( $env['wp'] ); ?></td></tr>
				<tr><th>WooCommerce</th><td><?php echo esc_html( $env['wc'] ); ?> <span class="description">(<?php echo esc_html( sprintf( /* translators: %s: version */ __( 'minimum %s', 'parcelflow-for-woocommerce' ), PARCELFLOW_WC_MIN_WC ) ); ?>)</span></td></tr>
				<tr><th>PHP</th><td><?php echo esc_html( $env['php'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'HPOS enabled in this store', 'parcelflow-for-woocommerce' ); ?></th><td><?php $yes( $env['hpos'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Checkout page uses Checkout Block', 'parcelflow-for-woocommerce' ); ?></th><td><?php $yes( $env['blocks'] ); ?></td></tr>
				<tr><th>Action Scheduler</th><td><?php $yes( $env['as'] ); ?></td></tr>
			</table>
		</section>
	</div>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Set-up checklist', 'parcelflow-for-woocommerce' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( sprintf( /* translators: %s: URL */ __( 'Enter the API base URL and access token in <a href="%s">Settings → General</a> and enable ParcelFlow.', 'parcelflow-for-woocommerce' ), esc_url( Admin::url( 'settings' ) ) ) ); ?></li>
			<li><?php esc_html_e( 'Choose the default shipment type under Settings → Shipping & Booking. It is required: the API documentation gives no rule for picking one, so rates are not requested until you do.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Fill in the sender / warehouse address (Settings → Sender). This is the dispatch address, not necessarily the store address.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Add a ParcelFlow method to a WooCommerce shipping zone (WooCommerce → Settings → Shipping → zone → Add shipping method → ParcelFlow).', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'On every product set weight, dimensions (or use a fixed carton) and the HS tariff code (Product data → Shipping).', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Place a test order. After payment, open ParcelFlow → Shipments (or the order) to book the shipment.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php echo wp_kses_post( sprintf( /* translators: %s: URL */ __( 'Customers can track at <a href="%1$s">%1$s</a> and in My Account → Track Shipment.', 'parcelflow-for-woocommerce' ), esc_url( $tracking_url ) ) ); ?></li>
		</ol>
	</section>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Compatibility', 'parcelflow-for-woocommerce' ); ?></h2>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Order data is read and written only through WooCommerce CRUD, which is the HPOS-compatible approach. The plugin does NOT declare HPOS compatibility until it has been verified on a real store (see docs/TESTING.md).', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Classic checkout: rates, carrier logos, delivery estimate and additional info are shown. Block checkout: rates are returned through WooCommerce’s standard shipping-rate API and shown as “Carrier (delivery time) – price”; logos are not shown. Block checkout behaviour must be verified on your store before relying on it.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Storefront styling inherits your theme.', 'parcelflow-for-woocommerce' ); ?></li>
		</ul>
	</section>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Common errors & troubleshooting', 'parcelflow-for-woocommerce' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Symptom', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Likely cause / action', 'parcelflow-for-woocommerce' ); ?></th></tr></thead>
			<tbody>
				<tr><td><?php esc_html_e( 'No ParcelFlow option at checkout', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Plugin disabled, no token, method not added to the customer’s shipping zone, or a product is missing weight / dimensions / HS code. Check Logs for “Rates not requested”.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Authentication failures (401/403)', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Wrong token, or the header name is not received. Try the header name “Access-Token” in Settings → Advanced and confirm with ParcelFlow; many servers drop headers containing underscores.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Rates show but booking is blocked: “No rate_id”', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'The documented Calculate Shipment Cost response does not include rate_id. Obtain it from ParcelFlow; an administrator may enter it manually when booking.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Booking rejected: wallet / payment errors', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Create Shipment requires payment_method “Wallet”. Confirm with ParcelFlow how the wallet is funded.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Booking outcome uncertain', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'A timeout or unreadable response occurred. The plugin will not retry. Open the shipment, look up recent shipments in ParcelFlow, then link the existing one or confirm none exists.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Tracking shows no events', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'The tracking endpoint is keyed by shipment number. Events appear once ParcelFlow records them. Use Refresh tracking.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Prices not shown / “currently unavailable”', 'parcelflow-for-woocommerce' ); ?></td><td><?php esc_html_e( 'The ParcelFlow price currency (Settings → General) differs from the store currency. No conversion is done.', 'parcelflow-for-woocommerce' ); ?></td></tr>
			</tbody>
		</table>
	</section>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Open questions for ParcelFlow', 'parcelflow-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'The following are not defined by the API documentation and are isolated in the code and settings. See docs/API-QUESTIONS.md for the full list.', 'parcelflow-for-woocommerce' ); ?></p>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Where is rate_id returned? (Required by Create Shipment; absent from the Calculate Shipment Cost sample.)', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Production base URL and exact authentication header / token format.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Wallet payment: how the merchant wallet is funded and debited; can booking follow external WooCommerce payment?', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Shipment type rules (Local / Interstate / International / Import) and rate expiry.', 'parcelflow-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Webhook support (none documented; tracking uses polling).', 'parcelflow-for-woocommerce' ); ?></li>
		</ul>
	</section>
</div>

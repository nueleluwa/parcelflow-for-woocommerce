<?php
/**
 * Single shipment view.
 *
 * @var array          $row
 * @var \WC_Order|false $order
 * @var array          $request
 * @var bool           $preview
 * @var array          $events
 * @var array          $rate
 * @var array|\WP_Error|null $candidates
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$oid      = (int) $row['order_id'];
$sender   = isset( $request['sender'] ) ? $request['sender'] : array();
$receiver = isset( $request['receiver'] ) ? $request['receiver'] : array();
$pkgs     = isset( $request['package']['packages'] ) ? $request['package']['packages'] : array();
$fees     = isset( $request['booking']['fees'] ) ? $request['booking']['fees'] : array();
$currency = $row['currency'] ? $row['currency'] : get_woocommerce_currency();
$label    = Format::safe_url( $row['label_url'] );

$party = static function ( $p ) {
	if ( ! $p ) {
		echo '<p>—</p>';
		return;
	}
	echo '<address>';
	echo esc_html( $p['name'] ) . '<br />' . esc_html( $p['address_1'] ) . '<br />';
	if ( ! empty( $p['address_2'] ) ) {
		echo esc_html( $p['address_2'] ) . '<br />';
	}
	echo esc_html( trim( $p['city'] . ', ' . $p['state'] . ' ' . $p['postcode'], ' ,' ) ) . '<br />' . esc_html( $p['country'] ) . '<br />';
	echo esc_html( $p['phone_no'] ) . '<br />' . esc_html( $p['email'] );
	echo '</address>';
};
?>
<div class="wrap parcelflow-admin">
	<?php View::header( sprintf( /* translators: %s: order number */ __( 'Shipment for order #%s', 'parcelflow-for-woocommerce' ), $row['order_number'] ) ); ?>
	<hr class="wp-header-end" />
	<p><a href="<?php echo esc_url( Admin::url( 'shipments' ) ); ?>">&larr; <?php esc_html_e( 'All shipments', 'parcelflow-for-woocommerce' ); ?></a></p>

	<?php if ( Status::STATE_UNCERTAIN === $row['state'] ) : ?>
		<div class="notice notice-error inline"><p><strong><?php esc_html_e( 'Booking outcome uncertain.', 'parcelflow-for-woocommerce' ); ?></strong> <?php echo esc_html( $row['error_message'] ); ?> <?php esc_html_e( 'Do not rebook until you have checked ParcelFlow.', 'parcelflow-for-woocommerce' ); ?></p></div>
	<?php elseif ( Status::STATE_FAILED === $row['state'] && $row['error_message'] ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( $row['error_message'] ); ?></p></div>
	<?php endif; ?>

	<div class="pf-grid">
		<section class="pf-card">
			<h2><?php esc_html_e( 'Shipment', 'parcelflow-for-woocommerce' ); ?></h2>
			<table class="pf-kv">
				<tr><th><?php esc_html_e( 'Booking state', 'parcelflow-for-woocommerce' ); ?></th><td><span class="pf-pill pf-pill--<?php echo esc_attr( $row['status_group'] ); ?>"><?php echo esc_html( Status::state_label( $row['state'] ) ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'ParcelFlow status', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['status'] ? esc_html( $row['status'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Shipment number', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['shipment_number'] ? esc_html( $row['shipment_number'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'ParcelFlow shipment ID', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['pf_shipment_id'] ? esc_html( $row['pf_shipment_id'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Tracking number', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['tracking_number'] ? esc_html( $row['tracking_number'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( $row['carrier_name'] ); ?> <?php echo $row['carrier_id'] ? '<span class="description">(ID ' . esc_html( $row['carrier_id'] ) . ')</span>' : ''; ?></td></tr>
				<tr><th><?php esc_html_e( 'Rate ID', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['rate_id'] ? esc_html( $row['rate_id'] ) : '<em>' . esc_html__( 'not available', 'parcelflow-for-woocommerce' ) . '</em>'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Label', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo $label ? '<a class="button button-small" href="' . esc_url( $label ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Download label', 'parcelflow-for-woocommerce' ) . '</a>' : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Created', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( Format::datetime_local( $row['created_at'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Last tracking refresh', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo $row['last_tracked_at'] ? esc_html( Format::datetime_local( $row['last_tracked_at'] ) ) : '—'; ?></td></tr>
			</table>
			<p class="pf-buttons">
				<?php if ( $order ) : ?>
					<a class="button" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'View WooCommerce order', 'parcelflow-for-woocommerce' ); ?></a>
				<?php endif; ?>
				<?php if ( Status::STATE_BOOKED === $row['state'] && '' !== $row['shipment_number'] ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=parcelflow_wc_refresh_tracking&order_id=' . $oid ), 'parcelflow_wc_refresh_' . $oid ) ); ?>"><?php esc_html_e( 'Refresh tracking', 'parcelflow-for-woocommerce' ); ?></a>
				<?php endif; ?>
			</p>
		</section>

		<section class="pf-card">
			<h2><?php esc_html_e( 'Fees', 'parcelflow-for-woocommerce' ); ?></h2>
			<table class="pf-kv">
				<tr><th><?php esc_html_e( 'Rate quoted at checkout', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo null !== $row['shipping_fee'] ? wp_kses_post( wc_price( (float) $row['shipping_fee'], array( 'currency' => $currency ) ) ) : '—'; ?></td></tr>
				<?php if ( $fees ) : ?>
					<tr><th><?php esc_html_e( 'Standard fee', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $fees['standard_fee'], array( 'currency' => $currency ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Services fee', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $fees['services_fee'], array( 'currency' => $currency ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Extras (pickup + insurance)', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $fees['extras_fee'], array( 'currency' => $currency ) ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Submitted shipping fee', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo wp_kses_post( wc_price( (float) $fees['shipping_fee'], array( 'currency' => $currency ) ) ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Total fee (ParcelFlow)', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo null !== $row['total_fee'] ? wp_kses_post( wc_price( (float) $row['total_fee'], array( 'currency' => $currency ) ) ) : '—'; ?></td></tr>
			</table>
		</section>

		<section class="pf-card">
			<h2><?php esc_html_e( 'Sender', 'parcelflow-for-woocommerce' ); ?><?php echo $preview ? ' <small>(' . esc_html__( 'current settings', 'parcelflow-for-woocommerce' ) . ')</small>' : ''; ?></h2>
			<?php $party( $sender ); ?>
		</section>
		<section class="pf-card">
			<h2><?php esc_html_e( 'Receiver', 'parcelflow-for-woocommerce' ); ?></h2>
			<?php $party( $receiver ); ?>
		</section>
	</div>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Package contents', 'parcelflow-for-woocommerce' ); ?><?php echo $preview ? ' <small>(' . esc_html__( 'preview – not yet sent', 'parcelflow-for-woocommerce' ) . ')</small>' : ''; ?></h2>
		<?php if ( ! $pkgs ) : ?>
			<p><?php esc_html_e( 'Package data cannot be built yet (see the order for missing weights, dimensions or HS codes).', 'parcelflow-for-woocommerce' ); ?></p>
		<?php endif; ?>
		<?php foreach ( $pkgs as $p ) : ?>
			<p><strong><?php esc_html_e( 'Weight:', 'parcelflow-for-woocommerce' ); ?></strong> <?php echo esc_html( $p['package_weight'] ); ?> kg &nbsp; <strong><?php esc_html_e( 'Dimensions:', 'parcelflow-for-woocommerce' ); ?></strong> <?php echo esc_html( $p['package_length'] . ' × ' . $p['package_width'] . ' × ' . $p['package_height'] ); ?> cm &nbsp; <strong><?php esc_html_e( 'Items:', 'parcelflow-for-woocommerce' ); ?></strong> <?php echo esc_html( $p['number_of_items'] ); ?></p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Item', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Qty', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Declared value / unit', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Weight / unit (kg)', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'HS code', 'parcelflow-for-woocommerce' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $p['package_items'] as $it ) : ?>
					<tr><td><?php echo esc_html( $it['name'] ); ?></td><td><?php echo esc_html( $it['quantity'] ); ?></td><td><?php echo esc_html( $it['value'] . ' ' . $it['value_currency'] ); ?></td><td><?php echo esc_html( $it['weight'] ); ?></td><td><?php echo esc_html( $it['hs_code'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
	</section>

	<section class="pf-card pf-card--wide">
		<h2><?php esc_html_e( 'Tracking history', 'parcelflow-for-woocommerce' ); ?></h2>
		<?php if ( ! $events ) : ?>
			<p><?php esc_html_e( 'No tracking events stored yet. Use “Refresh tracking”.', 'parcelflow-for-woocommerce' ); ?></p>
		<?php else : ?>
			<ol class="pf-timeline">
				<?php foreach ( $events as $e ) : ?>
					<li><span class="pf-timeline__time"><?php echo esc_html( $e['timestamp'] ); ?></span> <?php echo esc_html( $e['description'] ); ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>

	<?php if ( in_array( $row['state'], array( Status::STATE_AWAITING, Status::STATE_FAILED ), true ) && $order && ! $order->is_paid() ) : ?>
		<p class="description"><?php esc_html_e( 'This order is no longer in a paid status, so it cannot be booked.', 'parcelflow-for-woocommerce' ); ?></p>
	<?php elseif ( in_array( $row['state'], array( Status::STATE_AWAITING, Status::STATE_FAILED ), true ) ) : ?>
		<section class="pf-card pf-card--wide">
			<h2><?php echo Status::STATE_FAILED === $row['state'] ? esc_html__( 'Retry booking', 'parcelflow-for-woocommerce' ) : esc_html__( 'Book shipment', 'parcelflow-for-woocommerce' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-pf-confirm="confirmBook">
				<input type="hidden" name="action" value="parcelflow_wc_book" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $oid ); ?>" />
				<?php wp_nonce_field( 'parcelflow_wc_book_' . $oid ); ?>
				<?php if ( '' === $rate['rate_id'] ) : ?>
					<p><label for="pf-rate-id"><strong><?php esc_html_e( 'Rate ID', 'parcelflow-for-woocommerce' ); ?></strong></label><br />
					<input type="text" id="pf-rate-id" name="rate_id" class="regular-text" autocomplete="off" />
					<span class="description"><?php esc_html_e( 'The Calculate Shipment Cost response did not return a rate_id, but Create Shipment requires one. Enter the rate ID supplied by ParcelFlow for this carrier and shipment. It is never guessed.', 'parcelflow-for-woocommerce' ); ?></span></p>
				<?php endif; ?>
				<details class="pf-override">
					<summary><?php esc_html_e( 'Override package weight and size (optional)', 'parcelflow-for-woocommerce' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Replaces the calculated package for this booking only. Enter all four values or leave them blank. The carrier may charge differently from the rate quoted at checkout.', 'parcelflow-for-woocommerce' ); ?></p>
					<p>
						<label><?php esc_html_e( 'Weight (kg)', 'parcelflow-for-woocommerce' ); ?> <input type="number" step="any" min="0" name="pkg_weight" class="small-text" /></label>
						<label><?php esc_html_e( 'Length (cm)', 'parcelflow-for-woocommerce' ); ?> <input type="number" step="any" min="0" name="pkg_length" class="small-text" /></label>
						<label><?php esc_html_e( 'Width (cm)', 'parcelflow-for-woocommerce' ); ?> <input type="number" step="any" min="0" name="pkg_width" class="small-text" /></label>
						<label><?php esc_html_e( 'Height (cm)', 'parcelflow-for-woocommerce' ); ?> <input type="number" step="any" min="0" name="pkg_height" class="small-text" /></label>
					</p>
				</details>
				<p><button class="button button-primary pf-btn-primary"><?php esc_html_e( 'Book ParcelFlow shipment', 'parcelflow-for-woocommerce' ); ?></button></p>
			</form>
		</section>
	<?php endif; ?>

	<?php if ( Status::STATE_UNCERTAIN === $row['state'] ) : ?>
		<section class="pf-card pf-card--wide">
			<h2><?php esc_html_e( 'Reconcile booking', 'parcelflow-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'The booking request may have reached ParcelFlow. Check whether a shipment exists, then either link it to this order or confirm that none was created.', 'parcelflow-for-woocommerce' ); ?></p>
			<p><a class="button" href="
			<?php
			echo esc_url(
				wp_nonce_url(
					Admin::url(
						'shipments',
						array(
							'view'   => (int) $row['id'],
							'lookup' => 1,
						)
					),
					'parcelflow_wc_lookup_' . $oid
				)
			);
			?>
										"><?php esc_html_e( 'Look up recent shipments in ParcelFlow', 'parcelflow-for-woocommerce' ); ?></a></p>

			<?php if ( is_wp_error( $candidates ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $candidates->get_error_message() ); ?></p></div>
			<?php elseif ( is_array( $candidates ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="parcelflow_wc_link_existing" />
					<input type="hidden" name="order_id" value="<?php echo esc_attr( $oid ); ?>" />
					<?php wp_nonce_field( 'parcelflow_wc_link_' . $oid ); ?>
					<table class="widefat striped">
						<thead><tr><th></th><th><?php esc_html_e( 'Shipment #', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Receiver', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Created', 'parcelflow-for-woocommerce' ); ?></th></tr></thead>
						<tbody>
						<?php if ( ! $candidates ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'ParcelFlow returned no shipments for this date range.', 'parcelflow-for-woocommerce' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $candidates as $c ) : ?>
							<?php
							if ( ! is_array( $c ) || empty( $c['shipment_number'] ) ) {
								continue; }
							?>
							<tr>
								<td><input type="radio" name="shipment_number" value="<?php echo esc_attr( $c['shipment_number'] ); ?>" required /></td>
								<td><?php echo esc_html( $c['shipment_number'] ); ?></td>
								<td><?php echo esc_html( isset( $c['receiver_name'] ) ? $c['receiver_name'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $c['carrier_name'] ) ? $c['carrier_name'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $c['status'] ) ? $c['status'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $c['created_at'] ) ? $c['created_at'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $candidates ) : ?>
						<p><button class="button button-primary pf-btn-primary"><?php esc_html_e( 'Link selected shipment to this order', 'parcelflow-for-woocommerce' ); ?></button></p>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<hr />
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-pf-confirm="confirmNotMade">
				<input type="hidden" name="action" value="parcelflow_wc_confirm_not_created" />
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $oid ); ?>" />
				<?php wp_nonce_field( 'parcelflow_wc_clear_' . $oid ); ?>
				<label><input type="checkbox" name="confirm" value="1" /> <?php esc_html_e( 'I have verified in ParcelFlow that no shipment was created for this order.', 'parcelflow-for-woocommerce' ); ?></label>
				<p><button class="button"><?php esc_html_e( 'Allow a new booking attempt', 'parcelflow-for-woocommerce' ); ?></button></p>
			</form>
		</section>
	<?php endif; ?>
</div>

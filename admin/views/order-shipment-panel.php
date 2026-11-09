<?php
/**
 * Order edit screen meta box. NOTE: this renders inside the order form, so it must not
 * contain nested <form> elements; actions are nonce-protected links to admin-post.php.
 *
 * @var \WC_Order  $order
 * @var bool       $has_item
 * @var array      $rate
 * @var array|null $row
 * @var string[]   $errors
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$oid = $order->get_id();

if ( ! $has_item ) {
	echo '<p>' . esc_html__( 'This order does not use a ParcelFlow shipping method.', 'parcelflow-for-woocommerce' ) . '</p>';
	return;
}
$label = $row ? Format::safe_url( $row['label_url'] ) : '';
?>
<div class="parcelflow-admin pf-metabox">
	<p><strong><?php esc_html_e( 'Selected carrier', 'parcelflow-for-woocommerce' ); ?>:</strong>
		<?php echo esc_html( $rate['carrier_name'] ? $rate['carrier_name'] : '—' ); ?>
		<?php echo $rate['delivery_time'] ? '<br /><span class="description">' . esc_html( $rate['delivery_time'] ) . '</span>' : ''; ?>
	</p>
	<p><strong><?php esc_html_e( 'Quoted rate', 'parcelflow-for-woocommerce' ); ?>:</strong> <?php echo null !== $rate['selling_price'] ? wp_kses_post( wc_price( $rate['selling_price'], array( 'currency' => $order->get_currency() ) ) ) : '—'; ?></p>

	<?php if ( ! $row ) : ?>
		<?php if ( $errors ) : ?>
			<p class="description"><?php echo esc_html( implode( ' ', $errors ) ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Shipment record is created when the order is paid. Reload this page.', 'parcelflow-for-woocommerce' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p><span class="pf-pill pf-pill--<?php echo esc_attr( $row['status_group'] ); ?>"><?php echo esc_html( Status::STATE_BOOKED === $row['state'] && '' !== $row['status'] ? $row['status'] : Status::state_label( $row['state'] ) ); ?></span></p>

		<?php if ( Status::STATE_BOOKED === $row['state'] ) : ?>
			<p><strong><?php esc_html_e( 'Shipment #', 'parcelflow-for-woocommerce' ); ?>:</strong> <?php echo esc_html( $row['shipment_number'] ); ?><br />
			<strong><?php esc_html_e( 'Tracking #', 'parcelflow-for-woocommerce' ); ?>:</strong> <?php echo '' !== $row['tracking_number'] ? esc_html( $row['tracking_number'] ) : '—'; ?></p>
			<p>
				<?php
				if ( $label ) :
					?>
					<a class="button button-small" href="<?php echo esc_url( $label ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download label', 'parcelflow-for-woocommerce' ); ?></a><?php endif; ?>
				<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=parcelflow_wc_refresh_tracking&order_id=' . $oid ), 'parcelflow_wc_refresh_' . $oid ) ); ?>"><?php esc_html_e( 'Refresh tracking', 'parcelflow-for-woocommerce' ); ?></a>
			</p>
		<?php elseif ( in_array( $row['state'], array( Status::STATE_AWAITING, Status::STATE_FAILED ), true ) ) : ?>
			<?php
			if ( $row['error_message'] ) :
				?>
				<p class="description"><?php echo esc_html( $row['error_message'] ); ?></p><?php endif; ?>
			<?php if ( $errors ) : ?>
				<p class="description"><?php echo esc_html( implode( ' ', $errors ) ); ?></p>
			<?php elseif ( '' === $rate['rate_id'] ) : ?>
				<p class="description"><?php esc_html_e( 'No rate_id was returned for this rate. Enter one on the shipment page to book.', 'parcelflow-for-woocommerce' ); ?></p>
				<a class="button button-primary pf-btn-primary" href="<?php echo esc_url( Admin::url( 'shipments', array( 'view' => (int) $row['id'] ) ) ); ?>"><?php esc_html_e( 'Open booking page', 'parcelflow-for-woocommerce' ); ?></a>
			<?php else : ?>
				<a class="button button-primary pf-btn-primary" data-pf-confirm="confirmBook" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=parcelflow_wc_book&order_id=' . $oid ), 'parcelflow_wc_book_' . $oid ) ); ?>">
					<?php echo Status::STATE_FAILED === $row['state'] ? esc_html__( 'Retry booking', 'parcelflow-for-woocommerce' ) : esc_html__( 'Book ParcelFlow Shipment', 'parcelflow-for-woocommerce' ); ?>
				</a>
			<?php endif; ?>
		<?php elseif ( Status::STATE_UNCERTAIN === $row['state'] ) : ?>
			<p class="description"><strong><?php esc_html_e( 'Outcome uncertain. Do not rebook.', 'parcelflow-for-woocommerce' ); ?></strong></p>
			<a class="button" href="<?php echo esc_url( Admin::url( 'shipments', array( 'view' => (int) $row['id'] ) ) ); ?>"><?php esc_html_e( 'Reconcile', 'parcelflow-for-woocommerce' ); ?></a>
		<?php elseif ( Status::STATE_IN_PROGRESS === $row['state'] ) : ?>
			<p class="description"><?php esc_html_e( 'A booking request is in progress.', 'parcelflow-for-woocommerce' ); ?></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( Admin::url( 'shipments', array( 'view' => (int) $row['id'] ) ) ); ?>"><?php esc_html_e( 'Full shipment details', 'parcelflow-for-woocommerce' ); ?></a></p>
	<?php endif; ?>
</div>

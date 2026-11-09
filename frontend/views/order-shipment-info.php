<?php
/**
 * "ParcelFlow Shipment Information" section on the customer order view.
 *
 * @var \WC_Order   $order
 * @var array|null  $row
 * @var bool        $booked
 * @var string      $track_url
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$paid = $order->is_paid();
?>
<section class="parcelflow-order-shipment woocommerce-order-details">
	<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Shipment Information', 'parcelflow-for-woocommerce' ); ?></h2>
	<?php if ( $booked ) : ?>
		<table class="woocommerce-table shop_table">
			<tbody>
				<tr><th scope="row"><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['carrier_name'] ? esc_html( $row['carrier_name'] ) : '—'; ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Shipment number', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['shipment_number'] ? esc_html( $row['shipment_number'] ) : '—'; ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Tracking number', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['tracking_number'] ? esc_html( $row['tracking_number'] ) : '—'; ?></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $row['status'] ? esc_html( $row['status'] ) : esc_html__( 'Booked', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<?php if ( $row['booked_at'] ) : ?>
					<tr><th scope="row"><?php esc_html_e( 'Shipping date', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( get_date_from_gmt( $row['booked_at'], get_option( 'date_format' ) ) ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php if ( $track_url ) : ?>
			<p><a class="button parcelflow-btn" href="<?php echo esc_url( $track_url ); ?>"><?php esc_html_e( 'Track Shipment', 'parcelflow-for-woocommerce' ); ?></a></p>
		<?php endif; ?>
	<?php elseif ( $paid ) : ?>
		<p><?php esc_html_e( 'Your order has been paid and your shipment is being prepared. Tracking details will appear here once it has been booked with the carrier.', 'parcelflow-for-woocommerce' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Shipment details will be available once your payment has been confirmed.', 'parcelflow-for-woocommerce' ); ?></p>
	<?php endif; ?>
</section>

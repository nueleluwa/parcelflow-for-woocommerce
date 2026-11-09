<?php
/**
 * ParcelFlow shipment history (live from the API), read-only.
 *
 * @var string $from
 * @var string $to
 * @var string $status
 * @var int    $paged
 * @var bool   $more
 * @var array  $rows
 * @var array  $local
 * @var string $error
 *
 * @package ParcelFlow\WooCommerce
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$pf_args = array(
	'tab'       => 'api-history',
	'date_from' => $from,
	'date_to'   => $to,
	'status'    => $status,
);
?>
<div class="wrap parcelflow-admin">
	<?php View::header( __( 'Shipments', 'parcelflow-for-woocommerce' ) ); ?>
	<hr class="wp-header-end" />
	<nav class="nav-tab-wrapper pf-tabs" aria-label="<?php esc_attr_e( 'Shipment views', 'parcelflow-for-woocommerce' ); ?>">
		<a href="<?php echo esc_url( Admin::url( 'shipments' ) ); ?>" class="nav-tab"><?php esc_html_e( 'This store', 'parcelflow-for-woocommerce' ); ?></a>
		<a href="<?php echo esc_url( Admin::url( 'shipments', array( 'tab' => 'api-history' ) ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'ParcelFlow history (API)', 'parcelflow-for-woocommerce' ); ?></a>
	</nav>

	<p class="description"><?php esc_html_e( 'Live from ParcelFlow (GET /get-shipment-history) for the company behind your access token. It can include shipments that were not booked through this store.', 'parcelflow-for-woocommerce' ); ?></p>

	<form method="get" class="pf-filters">
		<input type="hidden" name="page" value="parcelflow-shipments" />
		<input type="hidden" name="tab" value="api-history" />
		<label for="pf-h-from"><?php esc_html_e( 'From', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="date" id="pf-h-from" name="date_from" value="<?php echo esc_attr( $from ); ?>" required />
		<label for="pf-h-to"><?php esc_html_e( 'To', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="date" id="pf-h-to" name="date_to" value="<?php echo esc_attr( $to ); ?>" required />
		<label for="pf-h-status"><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="text" id="pf-h-status" name="status" value="<?php echo esc_attr( $status ); ?>" list="pf-h-status-list" class="regular-text" />
		<datalist id="pf-h-status-list">
			<option value="All"></option>
			<option value="Pending"></option>
			<option value="In Transit"></option>
			<option value="Delivered"></option>
		</datalist>
		<button class="button"><?php esc_html_e( 'Load', 'parcelflow-for-woocommerce' ); ?></button>
	</form>

	<?php if ( $error ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped pf-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Shipment #', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Sender', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Receiver', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Created', 'parcelflow-for-woocommerce' ); ?></th>
					<th><?php esc_html_e( 'In this store', 'parcelflow-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No shipments returned for this range and status.', 'parcelflow-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $pf_row ) : ?>
				<?php
				$pf_num = isset( $pf_row['shipment_number'] ) ? (string) $pf_row['shipment_number'] : '';
				$pf_hit = isset( $local[ $pf_num ] ) ? $local[ $pf_num ] : null;
				?>
				<tr>
					<td><?php echo isset( $pf_row['id'] ) ? esc_html( (string) $pf_row['id'] ) : '—'; ?></td>
					<td><?php echo '' !== $pf_num ? esc_html( $pf_num ) : '—'; ?></td>
					<td><?php echo isset( $pf_row['sender_name'] ) ? esc_html( (string) $pf_row['sender_name'] ) : '—'; ?></td>
					<td><?php echo isset( $pf_row['receiver_name'] ) ? esc_html( (string) $pf_row['receiver_name'] ) : '—'; ?></td>
					<td><?php echo isset( $pf_row['carrier_name'] ) ? esc_html( (string) $pf_row['carrier_name'] ) : '—'; ?></td>
					<td><?php echo isset( $pf_row['status'] ) ? esc_html( (string) $pf_row['status'] ) : '—'; ?></td>
					<td><?php echo isset( $pf_row['created_at'] ) ? esc_html( (string) $pf_row['created_at'] ) : '—'; ?></td>
					<td>
						<?php if ( $pf_hit ) : ?>
							<a href="<?php echo esc_url( Admin::url( 'shipments', array( 'view' => (int) $pf_hit['id'] ) ) ); ?>">#<?php echo esc_html( $pf_hit['order_number'] ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p class="pf-pager">
			<?php if ( $paged > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( Admin::url( 'shipments', array_merge( $pf_args, array( 'paged' => $paged - 1 ) ) ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'parcelflow-for-woocommerce' ); ?></a>
			<?php endif; ?>
			<span class="description"><?php echo esc_html( sprintf( /* translators: %d: page number */ __( 'Page %d', 'parcelflow-for-woocommerce' ), $paged ) ); ?></span>
			<?php if ( $more ) : ?>
				<a class="button" href="<?php echo esc_url( Admin::url( 'shipments', array_merge( $pf_args, array( 'paged' => $paged + 1 ) ) ) ); ?>"><?php esc_html_e( 'Next', 'parcelflow-for-woocommerce' ); ?> &rarr;</a>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>

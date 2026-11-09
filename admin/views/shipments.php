<?php
/**
 * Shipments list view.
 *
 * @var array  $filters
 * @var array  $rows
 * @var int    $total
 * @var int    $paged
 * @var int    $pages
 * @var array  $counts
 * @var string $export_url
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Support\Format;
use ParcelFlow\WooCommerce\Support\Status;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$groups   = Status::groups();
$base     = Admin::url( 'shipments' );
$base_arg = array_filter(
	array(
		's'         => $filters['search'],
		'date_from' => $filters['date_from'],
		'date_to'   => $filters['date_to'],
	)
);
?>
<div class="wrap parcelflow-admin">
	<?php View::header( __( 'Shipments', 'parcelflow-for-woocommerce' ) ); ?>
	<hr class="wp-header-end" />
	<nav class="nav-tab-wrapper pf-tabs" aria-label="<?php esc_attr_e( 'Shipment views', 'parcelflow-for-woocommerce' ); ?>">
		<a href="<?php echo esc_url( Admin::url( 'shipments' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'This store', 'parcelflow-for-woocommerce' ); ?></a>
		<a href="<?php echo esc_url( Admin::url( 'shipments', array( 'tab' => 'api-history' ) ) ); ?>" class="nav-tab"><?php esc_html_e( 'ParcelFlow history (API)', 'parcelflow-for-woocommerce' ); ?></a>
	</nav>

	<ul class="pf-stats" aria-label="<?php esc_attr_e( 'Shipment totals', 'parcelflow-for-woocommerce' ); ?>">
		<li><span class="pf-stats__n"><?php echo esc_html( number_format_i18n( $counts['all'] ) ); ?></span><span class="pf-stats__l"><?php esc_html_e( 'Total shipments', 'parcelflow-for-woocommerce' ); ?></span></li>
		<?php foreach ( $groups as $key => $label ) : ?>
			<li class="pf-stats--<?php echo esc_attr( $key ); ?>"><span class="pf-stats__n"><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></span><span class="pf-stats__l"><?php echo esc_html( $label ); ?></span></li>
		<?php endforeach; ?>
	</ul>

	<ul class="subsubsub pf-subsubsub">
		<li><a href="<?php echo esc_url( add_query_arg( $base_arg, $base ) ); ?>" class="<?php echo '' === $filters['group'] ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'parcelflow-for-woocommerce' ); ?> <span class="count">(<?php echo esc_html( $counts['all'] ); ?>)</span></a> | </li>
		<?php
		$i = 0;
		foreach ( $groups as $key => $label ) :
			++$i;
			?>
			<li><a href="<?php echo esc_url( add_query_arg( array_merge( $base_arg, array( 'status' => $key ) ), $base ) ); ?>" class="<?php echo $key === $filters['group'] ? 'current' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( $counts[ $key ] ); ?>)</span></a><?php echo $i < count( $groups ) ? ' | ' : ''; ?></li>
		<?php endforeach; ?>
	</ul>

	<form method="get" class="pf-filters">
		<input type="hidden" name="page" value="parcelflow-shipments" />
		<?php if ( $filters['group'] ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $filters['group'] ); ?>" />
		<?php endif; ?>
		<label class="screen-reader-text" for="pf-search"><?php esc_html_e( 'Search shipments', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="search" id="pf-search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Order #, shipment # or tracking #', 'parcelflow-for-woocommerce' ); ?>" class="pf-search" />
		<label for="pf-from"><?php esc_html_e( 'From', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="date" id="pf-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
		<label for="pf-to"><?php esc_html_e( 'To', 'parcelflow-for-woocommerce' ); ?></label>
		<input type="date" id="pf-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
		<button class="button"><?php esc_html_e( 'Filter', 'parcelflow-for-woocommerce' ); ?></button>
		<a class="button pf-btn-secondary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'parcelflow-for-woocommerce' ); ?></a>
	</form>

	<table class="wp-list-table widefat fixed striped pf-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Shipment #', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Tracking #', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Shipping fee', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Created', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'parcelflow-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $rows ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No shipments found. Shipments appear here once a ParcelFlow order has been paid.', 'parcelflow-for-woocommerce' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $rows as $r ) : ?>
			<?php
			$order_url = ( function_exists( 'wc_get_order' ) && wc_get_order( (int) $r['order_id'] ) ) ? wc_get_order( (int) $r['order_id'] )->get_edit_order_url() : '';
			$label     = Format::safe_url( $r['label_url'] );
			?>
			<tr>
				<td data-colname="<?php esc_attr_e( 'Order', 'parcelflow-for-woocommerce' ); ?>"><?php echo $order_url ? '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $r['order_number'] ) . '</a>' : '#' . esc_html( $r['order_number'] ); ?></td>
				<td><?php echo '' !== $r['shipment_number'] ? esc_html( $r['shipment_number'] ) : '—'; ?></td>
				<td><?php echo '' !== $r['tracking_number'] ? esc_html( $r['tracking_number'] ) : '—'; ?></td>
				<td><?php echo '' !== $r['carrier_name'] ? esc_html( $r['carrier_name'] ) : '—'; ?></td>
				<td><?php echo null !== $r['shipping_fee'] ? wp_kses_post( wc_price( (float) $r['shipping_fee'], array( 'currency' => $r['currency'] ) ) ) : '—'; ?></td>
				<td>
					<span class="pf-pill pf-pill--<?php echo esc_attr( $r['status_group'] ); ?>"><?php echo esc_html( Status::STATE_BOOKED === $r['state'] && '' !== $r['status'] ? $r['status'] : Status::state_label( $r['state'] ) ); ?></span>
				</td>
				<td><?php echo esc_html( Format::datetime_local( $r['created_at'] ) ); ?></td>
				<td class="pf-actions">
					<a href="<?php echo esc_url( Admin::url( 'shipments', array( 'view' => (int) $r['id'] ) ) ); ?>"><?php esc_html_e( 'View', 'parcelflow-for-woocommerce' ); ?></a>
					<?php if ( Status::STATE_BOOKED === $r['state'] && '' !== $r['shipment_number'] ) : ?>
						| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=parcelflow_wc_refresh_tracking&order_id=' . (int) $r['order_id'] ), 'parcelflow_wc_refresh_' . (int) $r['order_id'] ) ); ?>"><?php esc_html_e( 'Refresh', 'parcelflow-for-woocommerce' ); ?></a>
					<?php endif; ?>
					<?php if ( $label ) : ?>
						| <a href="<?php echo esc_url( $label ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Label', 'parcelflow-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<span class="displaying-num"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s item', '%s items', $total, 'parcelflow-for-woocommerce' ), number_format_i18n( $total ) ) ); ?></span>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
</div>

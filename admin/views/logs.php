<?php
/**
 * Logs view.
 *
 * @var array      $types
 * @var array      $filters
 * @var array      $rows
 * @var int        $total
 * @var int        $paged
 * @var int        $pages
 * @var array|null $detail
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;
use ParcelFlow\WooCommerce\Support\Format;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$qs = array_filter(
	array(
		'event_type' => $filters['event_type'],
		'result'     => $filters['status'],
		'order'      => $filters['order'],
		'date_from'  => $filters['date_from'],
		'date_to'    => $filters['date_to'],
	)
);
?>
<div class="wrap parcelflow-admin">
	<?php View::header( __( 'Logs', 'parcelflow-for-woocommerce' ) ); ?>
	<hr class="wp-header-end" />

	<?php if ( $detail ) : ?>
		<p><a href="<?php echo esc_url( Admin::url( 'logs', $qs ) ); ?>">&larr; <?php esc_html_e( 'Back to logs', 'parcelflow-for-woocommerce' ); ?></a></p>
		<section class="pf-card pf-card--wide">
			<h2><?php esc_html_e( 'Log entry', 'parcelflow-for-woocommerce' ); ?> #<?php echo esc_html( $detail['id'] ); ?></h2>
			<table class="pf-kv">
				<tr><th><?php esc_html_e( 'Date', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( Format::datetime_local( $detail['created_at'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Event', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( isset( $types[ $detail['event_type'] ] ) ? $types[ $detail['event_type'] ] : $detail['event_type'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Result', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo $detail['success'] ? esc_html__( 'Success', 'parcelflow-for-woocommerce' ) : esc_html__( 'Failed', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Order', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo $detail['order_id'] ? '#' . esc_html( $detail['order_id'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Shipment', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $detail['shipment_number'] ? esc_html( $detail['shipment_number'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Endpoint', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo '' !== $detail['endpoint'] ? '<code>' . esc_html( $detail['endpoint'] ) . '</code>' : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'HTTP code', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo null !== $detail['http_code'] ? esc_html( $detail['http_code'] ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Message', 'parcelflow-for-woocommerce' ); ?></th><td><?php echo esc_html( $detail['message'] ); ?></td></tr>
			</table>
			<?php if ( ! empty( $detail['context'] ) ) : ?>
				<h3><?php esc_html_e( 'Details (redacted)', 'parcelflow-for-woocommerce' ); ?></h3>
				<?php
				$ctx = json_decode( $detail['context'], true );
				$out = null !== $ctx ? wp_json_encode( $ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : $detail['context'];
				?>
				<pre class="pf-pre"><?php echo esc_html( $out ); ?></pre>
			<?php endif; ?>
		</section>
	<?php else : ?>
		<form method="get" class="pf-filters">
			<input type="hidden" name="page" value="parcelflow-logs" />
			<select name="event_type" aria-label="<?php esc_attr_e( 'Event type', 'parcelflow-for-woocommerce' ); ?>">
				<option value=""><?php esc_html_e( 'All events', 'parcelflow-for-woocommerce' ); ?></option>
				<?php foreach ( $types as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $filters['event_type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="result" aria-label="<?php esc_attr_e( 'Status', 'parcelflow-for-woocommerce' ); ?>">
				<option value=""><?php esc_html_e( 'Any status', 'parcelflow-for-woocommerce' ); ?></option>
				<option value="success" <?php selected( $filters['status'], 'success' ); ?>><?php esc_html_e( 'Success', 'parcelflow-for-woocommerce' ); ?></option>
				<option value="failure" <?php selected( $filters['status'], 'failure' ); ?>><?php esc_html_e( 'Failed', 'parcelflow-for-woocommerce' ); ?></option>
			</select>
			<input type="text" name="order" value="<?php echo esc_attr( $filters['order'] ); ?>" placeholder="<?php esc_attr_e( 'Order ID', 'parcelflow-for-woocommerce' ); ?>" class="small-text" />
			<label for="pf-lfrom"><?php esc_html_e( 'From', 'parcelflow-for-woocommerce' ); ?></label>
			<input type="date" id="pf-lfrom" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			<label for="pf-lto"><?php esc_html_e( 'To', 'parcelflow-for-woocommerce' ); ?></label>
			<input type="date" id="pf-lto" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			<button class="button"><?php esc_html_e( 'Filter', 'parcelflow-for-woocommerce' ); ?></button>
		</form>

		<table class="wp-list-table widefat fixed striped pf-table">
			<thead><tr>
				<th><?php esc_html_e( 'Date & time', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Event', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Order', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Shipment', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Endpoint', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'HTTP', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Status', 'parcelflow-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Message', 'parcelflow-for-woocommerce' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No log entries match.', 'parcelflow-for-woocommerce' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( Format::datetime_local( $r['created_at'] ) ); ?></td>
					<td><?php echo esc_html( isset( $types[ $r['event_type'] ] ) ? $types[ $r['event_type'] ] : $r['event_type'] ); ?></td>
					<td><?php echo $r['order_id'] ? '#' . esc_html( $r['order_id'] ) : '—'; ?></td>
					<td><?php echo '' !== $r['shipment_number'] ? esc_html( $r['shipment_number'] ) : '—'; ?></td>
					<td><?php echo '' !== $r['endpoint'] ? '<code>' . esc_html( $r['endpoint'] ) . '</code>' : '—'; ?></td>
					<td><?php echo null !== $r['http_code'] ? esc_html( $r['http_code'] ) : '—'; ?></td>
					<td><span class="pf-pill pf-pill--<?php echo $r['success'] ? 'delivered' : 'failed'; ?>"><?php echo $r['success'] ? esc_html__( 'Success', 'parcelflow-for-woocommerce' ) : esc_html__( 'Failed', 'parcelflow-for-woocommerce' ); ?></span></td>
					<td><?php echo esc_html( wp_trim_words( $r['message'], 14 ) ); ?></td>
					<td><a href="<?php echo esc_url( Admin::url( 'logs', array_merge( $qs, array( 'log' => (int) $r['id'] ) ) ) ); ?>"><?php esc_html_e( 'View details', 'parcelflow-for-woocommerce' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom"><div class="tablenav-pages">
				<span class="displaying-num"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s entry', '%s entries', $total, 'parcelflow-for-woocommerce' ), number_format_i18n( $total ) ) ); ?></span>
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

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pf-clear-logs" data-pf-confirm="confirmClearLogs">
			<input type="hidden" name="action" value="parcelflow_wc_clear_logs" />
			<?php wp_nonce_field( 'parcelflow_wc_clear_logs' ); ?>
			<button class="button pf-btn-secondary"><?php esc_html_e( 'Clear all logs', 'parcelflow-for-woocommerce' ); ?></button>
		</form>
	<?php endif; ?>
</div>

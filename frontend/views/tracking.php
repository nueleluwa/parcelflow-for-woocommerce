<?php
/**
 * Customer tracking form + timeline. Inherits the active theme's typography.
 *
 * @var string     $number
 * @var array|null $result
 * @var string     $action_url
 * @var string     $arg
 */

use ParcelFlow\WooCommerce\Support\Status;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;

$events = ( $result && 'ok' === $result['state'] ) ? $result['events'] : array();
?>
<div class="parcelflow-tracking">
	<form method="get" class="parcelflow-tracking__form" <?php echo $action_url ? 'action="' . esc_url( $action_url ) . '"' : ''; ?>>
		<label for="parcelflow-tracking-number"><?php esc_html_e( 'Enter your tracking number to get the latest delivery updates.', 'parcelflow-for-woocommerce' ); ?></label>
		<div class="parcelflow-tracking__row">
			<input type="text" id="parcelflow-tracking-number" name="<?php echo esc_attr( $arg ); ?>" value="<?php echo esc_attr( $number ); ?>" maxlength="64" autocomplete="off" required />
			<button type="submit" class="button parcelflow-btn"><?php esc_html_e( 'Track', 'parcelflow-for-woocommerce' ); ?></button>
		</div>
	</form>

	<?php if ( $result && 'ok' !== $result['state'] ) : ?>
		<div class="parcelflow-notice parcelflow-notice--<?php echo esc_attr( 'error' === $result['state'] ? 'error' : 'info' ); ?>" role="alert"><?php echo esc_html( $result['message'] ); ?></div>
	<?php endif; ?>

	<?php if ( $result && 'ok' === $result['state'] ) : ?>
		<?php if ( $result['message'] ) : ?>
			<div class="parcelflow-notice parcelflow-notice--info"><?php echo esc_html( $result['message'] ); ?></div>
		<?php endif; ?>
		<div class="parcelflow-tracking__summary">
			<p>
				<strong><?php echo esc_html( $result['row']['shipment_number'] ); ?></strong>
				<?php if ( '' !== $result['status'] ) : ?>
					<span class="parcelflow-status parcelflow-status--<?php echo esc_attr( Status::group( Status::STATE_BOOKED, $result['status'] ) ); ?>"><?php echo esc_html( $result['status'] ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $result['row']['carrier_name'] ) : ?>
				<p><?php esc_html_e( 'Carrier:', 'parcelflow-for-woocommerce' ); ?> <?php echo esc_html( $result['row']['carrier_name'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $result['row']['tracking_number'] ) : ?>
				<p><?php esc_html_e( 'Tracking number:', 'parcelflow-for-woocommerce' ); ?> <?php echo esc_html( $result['row']['tracking_number'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $events ) : ?>
			<ol class="parcelflow-timeline">
				<?php foreach ( $events as $i => $e ) : ?>
					<li class="parcelflow-timeline__item<?php echo ( count( $events ) - 1 === $i ) ? ' is-latest' : ''; ?>">
						<span class="parcelflow-timeline__desc"><?php echo esc_html( $e['description'] ); ?></span>
						<time class="parcelflow-timeline__time"><?php echo esc_html( $e['timestamp'] ); ?></time>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p><?php esc_html_e( 'No tracking updates are available yet. Please check back soon.', 'parcelflow-for-woocommerce' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>

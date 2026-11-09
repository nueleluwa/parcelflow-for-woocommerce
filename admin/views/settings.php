<?php
/**
 * Settings view.
 *
 * @var array  $tabs
 * @var string $tab
 * @var array  $fields
 * @var array  $values
 * @var bool   $has_token
 * @var bool   $token_constant
 * @var array  $seen_carriers
 * @var array  $disabled
 */

use ParcelFlow\WooCommerce\Admin\Admin;
use ParcelFlow\WooCommerce\Admin\View;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view templates are included inside a method scope; their variables are not globals.

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap parcelflow-admin">
	<?php View::header( __( 'Settings', 'parcelflow-for-woocommerce' ) ); ?>
	<hr class="wp-header-end" />

	<nav class="nav-tab-wrapper pf-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'parcelflow-for-woocommerce' ); ?>">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( Admin::url( 'settings', array( 'tab' => $slug ) ) ); ?>" class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pf-settings" autocomplete="off">
		<input type="hidden" name="action" value="parcelflow_wc_save_settings" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
		<?php wp_nonce_field( 'parcelflow_wc_save_settings' ); ?>

		<table class="form-table" role="presentation">
		<?php foreach ( $fields as $key => $def ) : ?>
			<?php
			$field_id = 'pf-' . $key;
			$name     = 'pf[' . $key . ']';
			$val      = isset( $values[ $key ] ) ? $values[ $key ] : '';
			?>
			<tr>
				<th scope="row"><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $def['label'] ); ?></label></th>
				<td>
				<?php
				switch ( $def['type'] ) {
					case 'checkbox':
						echo '<label class="pf-switch"><input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="yes" ' . checked( 'yes', $val, false ) . ' /> <span>' . esc_html__( 'Enabled', 'parcelflow-for-woocommerce' ) . '</span></label>';
						break;
					case 'select':
						echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '">';
						foreach ( $def['options'] as $ov => $ol ) {
							echo '<option value="' . esc_attr( $ov ) . '" ' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
						}
						echo '</select>';
						break;
					case 'textarea':
						echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" rows="5" class="large-text code" placeholder="' . esc_attr( isset( $def['placeholder'] ) ? $def['placeholder'] : '' ) . '">' . esc_textarea( $val ) . '</textarea>';
						break;
					case 'country':
						echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( '— Select —', 'parcelflow-for-woocommerce' ) . '</option>';
						foreach ( WC()->countries->get_countries() as $code => $cname ) {
							echo '<option value="' . esc_attr( $code ) . '" ' . selected( $val, $code, false ) . '>' . esc_html( $cname ) . '</option>';
						}
						echo '</select>';
						break;
					case 'token':
						if ( $token_constant ) {
							echo '<p><em>' . esc_html__( 'The token is defined by the PARCELFLOW_WC_ACCESS_TOKEN constant in wp-config.php.', 'parcelflow-for-woocommerce' ) . '</em></p>';
						} else {
							// The stored token is never rendered. A blank submit keeps the existing value.
							echo '<input type="password" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr( $has_token ? '••••••••••••••••' : '' ) . '" />';
							echo '<p class="description">' . ( $has_token ? esc_html__( 'A token is stored (encrypted). Enter a new value to replace it, or leave blank to keep it.', 'parcelflow-for-woocommerce' ) : esc_html__( 'Paste the ACCESS_TOKEN issued by ParcelFlow. It is stored encrypted and used only server-side.', 'parcelflow-for-woocommerce' ) ) . '</p>';
							if ( $has_token ) {
								echo '<label><input type="checkbox" name="pf[clear_access_token]" value="1" /> ' . esc_html__( 'Remove stored token', 'parcelflow-for-woocommerce' ) . '</label>';
							}
						}
						break;
					case 'int':
					case 'float':
						echo '<input type="number" step="' . ( 'int' === $def['type'] ? '1' : 'any' ) . '" min="' . esc_attr( isset( $def['min'] ) ? $def['min'] : 0 ) . '"' . ( isset( $def['max'] ) ? ' max="' . esc_attr( $def['max'] ) . '"' : '' ) . ' id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="small-text" />';
						break;
					case 'url':
						echo '<input type="url" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="large-text" />';
						break;
					case 'email':
						echo '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="regular-text" />';
						break;
					default:
						echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="' . esc_attr( isset( $def['placeholder'] ) ? $def['placeholder'] : '' ) . '" />';
				}
				if ( ! empty( $def['desc'] ) && 'token' !== $def['type'] ) {
					echo '<p class="description">' . esc_html( $def['desc'] ) . '</p>';
				}
				?>
				</td>
			</tr>
		<?php endforeach; ?>
		</table>

		<?php if ( 'carriers' === $tab ) : ?>
			<h2><?php esc_html_e( 'Available carriers', 'parcelflow-for-woocommerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Carrier IDs are controlled by ParcelFlow and are never hardcoded. Carriers appear here after they have been returned by a real rate calculation. Untick a carrier to hide it at checkout.', 'parcelflow-for-woocommerce' ); ?></p>
			<table class="widefat striped pf-table" style="max-width:720px">
				<thead><tr><th><?php esc_html_e( 'Show at checkout', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Carrier', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Carrier ID', 'parcelflow-for-woocommerce' ); ?></th><th><?php esc_html_e( 'Last seen', 'parcelflow-for-woocommerce' ); ?></th></tr></thead>
				<tbody>
				<?php if ( ! $seen_carriers ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No carriers seen yet. Place a test order at checkout with a valid token to populate this list.', 'parcelflow-for-woocommerce' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $seen_carriers as $cid => $c ) : ?>
					<tr>
						<td><input type="checkbox" name="pf[enabled_carriers][]" value="<?php echo esc_attr( $cid ); ?>" <?php checked( ! in_array( (string) $cid, $disabled, true ) ); ?> /></td>
						<td><?php echo esc_html( $c['name'] ); ?></td>
						<td><code><?php echo esc_html( $cid ); ?></code></td>
						<td><?php echo esc_html( human_time_diff( (int) $c['last_seen'] ) . ' ' . __( 'ago', 'parcelflow-for-woocommerce' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php submit_button( __( 'Save changes', 'parcelflow-for-woocommerce' ), 'primary pf-btn-primary' ); ?>
	</form>
</div>

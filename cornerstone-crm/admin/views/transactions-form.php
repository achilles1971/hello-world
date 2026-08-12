<?php
/**
 * Transaction create/edit form.
 *
 * @var array|null $transaction        Existing row, or null when creating.
 * @var array      $contacts           Contacts in scope, for the picker.
 * @var int        $default_contact_id Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$is_edit    = null !== $transaction;
$contact_id = $is_edit ? (int) $transaction['contact_id'] : $default_contact_id;

$key_dates = [];
if ( $is_edit && ! empty( $transaction['key_dates'] ) ) {
	$decoded   = json_decode( $transaction['key_dates'], true );
	$key_dates = is_array( $decoded ) ? $decoded : [];
}
?>
<div class="wrap cornerstone-crm">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Transaction', 'cornerstone-crm' ) : esc_html__( 'Add Transaction', 'cornerstone-crm' ); ?></h1>
	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_TRANSACTIONS ); ?>

	<?php if ( empty( $contacts ) ) : ?>
		<p><?php esc_html_e( 'Add a contact first before creating a transaction.', 'cornerstone-crm' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS . '&view=new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-form">
		<?php wp_nonce_field( 'cornerstone_save_transaction' ); ?>
		<input type="hidden" name="action" value="cornerstone_save_transaction">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $transaction['id']; ?>">
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th><label for="contact_id"><?php esc_html_e( 'Contact', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="contact_id" name="contact_id" required>
						<option value=""><?php esc_html_e( '— Select —', 'cornerstone-crm' ); ?></option>
						<?php foreach ( $contacts as $c ) : ?>
							<option value="<?php echo (int) $c['id']; ?>" <?php selected( $contact_id, (int) $c['id'] ); ?>><?php echo esc_html( trim( $c['first_name'] . ' ' . $c['last_name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="property_address"><?php esc_html_e( 'Property Address', 'cornerstone-crm' ); ?></label></th>
				<td><input type="text" id="property_address" name="property_address" class="regular-text" value="<?php echo esc_attr( $transaction['property_address'] ?? '' ); ?>" required></td>
			</tr>
			<tr>
				<th><label for="transaction_type"><?php esc_html_e( 'Transaction Type', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="transaction_type" name="transaction_type">
						<?php foreach ( Cornerstone_Validate::TRANSACTION_TYPES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $transaction['transaction_type'] ?? 'buy', $option ); ?>><?php echo esc_html( ucwords( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="status"><?php esc_html_e( 'Status', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="status" name="status">
						<?php foreach ( Cornerstone_Validate::TRANSACTION_STATUSES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $transaction['status'] ?? 'active', $option ); ?>><?php echo esc_html( ucwords( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="price"><?php esc_html_e( 'Price', 'cornerstone-crm' ); ?></label></th>
				<td><input type="number" step="0.01" min="0" id="price" name="price" class="regular-text" value="<?php echo esc_attr( $transaction['price'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Key Dates', 'cornerstone-crm' ); ?></th>
				<td>
					<p class="description"><?php esc_html_e( 'Optional milestones, e.g. "inspection", "closing".', 'cornerstone-crm' ); ?></p>
					<div id="cornerstone-key-dates">
						<?php
						$rows = $key_dates ?: [ '' => '' ];
						foreach ( $rows as $milestone => $date ) :
							?>
							<p>
								<input type="text" name="key_date_labels[]" placeholder="<?php esc_attr_e( 'Milestone', 'cornerstone-crm' ); ?>" value="<?php echo esc_attr( is_string( $milestone ) ? $milestone : '' ); ?>">
								<input type="date" name="key_date_values[]" value="<?php echo esc_attr( $date ); ?>">
							</p>
						<?php endforeach; ?>
						<p>
							<input type="text" name="key_date_labels[]" placeholder="<?php esc_attr_e( 'Milestone', 'cornerstone-crm' ); ?>">
							<input type="date" name="key_date_values[]">
						</p>
					</div>
					<p class="description"><?php esc_html_e( 'Leave a row blank to skip it. Save the form again to add more rows.', 'cornerstone-crm' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Transaction', 'cornerstone-crm' ) : __( 'Add Transaction', 'cornerstone-crm' ) ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TRANSACTIONS ) ); ?>" class="button-link"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
	</form>
</div>

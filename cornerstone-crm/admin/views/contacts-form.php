<?php
/**
 * Contact create/edit form.
 *
 * @var array|null $contact Existing contact row, or null when creating.
 */

defined( 'ABSPATH' ) || exit;

$is_edit = null !== $contact;
?>
<div class="wrap cornerstone-crm">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Contact', 'cornerstone-crm' ) : esc_html__( 'Add Contact', 'cornerstone-crm' ); ?></h1>
	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_CONTACTS ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-form">
		<?php wp_nonce_field( 'cornerstone_save_contact' ); ?>
		<input type="hidden" name="action" value="cornerstone_save_contact">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $contact['id']; ?>">
		<?php endif; ?>

		<table class="form-table">
			<tr>
				<th><label for="first_name"><?php esc_html_e( 'First Name', 'cornerstone-crm' ); ?></label></th>
				<td><input type="text" id="first_name" name="first_name" class="regular-text" value="<?php echo esc_attr( $contact['first_name'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="last_name"><?php esc_html_e( 'Last Name', 'cornerstone-crm' ); ?></label></th>
				<td><input type="text" id="last_name" name="last_name" class="regular-text" value="<?php echo esc_attr( $contact['last_name'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="phone"><?php esc_html_e( 'Phone', 'cornerstone-crm' ); ?></label></th>
				<td><input type="text" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr( $contact['phone'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="email"><?php esc_html_e( 'Email', 'cornerstone-crm' ); ?></label></th>
				<td><input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="role_tag"><?php esc_html_e( 'Role', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="role_tag" name="role_tag">
						<?php foreach ( Cornerstone_Validate::ROLE_TAGS as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $contact['role_tag'] ?? 'sphere', $option ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $option ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="pipeline_status"><?php esc_html_e( 'Pipeline Status', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="pipeline_status" name="pipeline_status">
						<?php foreach ( Cornerstone_Validate::PIPELINE_STATUSES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $contact['pipeline_status'] ?? 'new', $option ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $option ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="notes"><?php esc_html_e( 'Notes', 'cornerstone-crm' ); ?></label></th>
				<td><textarea id="notes" name="notes" rows="5" class="large-text"><?php echo esc_textarea( $contact['notes'] ?? '' ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Contact', 'cornerstone-crm' ) : __( 'Add Contact', 'cornerstone-crm' ) ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS ) ); ?>" class="button-link"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
	</form>
</div>

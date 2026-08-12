<?php
/**
 * Interaction create/edit form.
 *
 * @var array|null $interaction        Existing row, or null when creating.
 * @var array      $contacts           Contacts in scope, for the picker.
 * @var int        $default_contact_id Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$is_edit    = null !== $interaction;
$contact_id = $is_edit ? (int) $interaction['contact_id'] : $default_contact_id;
$occurred   = $is_edit ? substr( (string) $interaction['occurred_at'], 0, 10 ) : gmdate( 'Y-m-d' );
?>
<div class="wrap cornerstone-crm">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Interaction', 'cornerstone-crm' ) : esc_html__( 'Add Interaction', 'cornerstone-crm' ); ?></h1>
	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_INTERACTIONS ); ?>

	<?php if ( empty( $contacts ) ) : ?>
		<p><?php esc_html_e( 'Add a contact first before logging an interaction.', 'cornerstone-crm' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS . '&view=new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-form">
		<?php wp_nonce_field( 'cornerstone_save_interaction' ); ?>
		<input type="hidden" name="action" value="cornerstone_save_interaction">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $interaction['id']; ?>">
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
				<th><label for="type"><?php esc_html_e( 'Type', 'cornerstone-crm' ); ?></label></th>
				<td>
					<select id="type" name="type">
						<?php foreach ( Cornerstone_Validate::INTERACTION_TYPES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $interaction['type'] ?? 'call', $option ); ?>><?php echo esc_html( ucwords( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="occurred_at"><?php esc_html_e( 'Date', 'cornerstone-crm' ); ?></label></th>
				<td><input type="date" id="occurred_at" name="occurred_at" value="<?php echo esc_attr( $occurred ); ?>"></td>
			</tr>
			<tr>
				<th><label for="note"><?php esc_html_e( 'Note', 'cornerstone-crm' ); ?></label></th>
				<td><textarea id="note" name="note" rows="5" class="large-text"><?php echo esc_textarea( $interaction['note'] ?? '' ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Interaction', 'cornerstone-crm' ) : __( 'Add Interaction', 'cornerstone-crm' ) ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_INTERACTIONS ) ); ?>" class="button-link"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
	</form>
</div>

<?php
/**
 * Task create/edit form. This is the "manual creation of follow-up tasks
 * tied to a contact" screen from the spec.
 *
 * @var array|null $task                Existing row, or null when creating.
 * @var array      $contacts            Contacts in scope, for the picker.
 * @var int        $default_contact_id  Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$is_edit    = null !== $task;
$contact_id = $is_edit ? (int) $task['contact_id'] : $default_contact_id;
?>
<div class="wrap cornerstone-crm">
	<h1><?php echo $is_edit ? esc_html__( 'Edit Task', 'cornerstone-crm' ) : esc_html__( 'Add Task', 'cornerstone-crm' ); ?></h1>
	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_TASKS ); ?>

	<?php if ( empty( $contacts ) ) : ?>
		<p><?php esc_html_e( 'Add a contact first before creating a follow-up task.', 'cornerstone-crm' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS . '&view=new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		<?php return; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-form">
		<?php wp_nonce_field( 'cornerstone_save_task' ); ?>
		<input type="hidden" name="action" value="cornerstone_save_task">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo (int) $task['id']; ?>">
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
				<th><label for="description"><?php esc_html_e( 'Description', 'cornerstone-crm' ); ?></label></th>
				<td><textarea id="description" name="description" rows="3" class="large-text" required><?php echo esc_textarea( $task['description'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="due_date"><?php esc_html_e( 'Due Date', 'cornerstone-crm' ); ?></label></th>
				<td><input type="date" id="due_date" name="due_date" value="<?php echo esc_attr( $task['due_date'] ?? '' ); ?>"></td>
			</tr>
			<?php if ( $is_edit ) : ?>
			<tr>
				<th><label for="completed"><?php esc_html_e( 'Completed', 'cornerstone-crm' ); ?></label></th>
				<td><label><input type="checkbox" id="completed" name="completed" value="1" <?php checked( ! empty( $task['completed'] ) ); ?>> <?php esc_html_e( 'Mark as completed', 'cornerstone-crm' ); ?></label></td>
			</tr>
			<?php endif; ?>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Task', 'cornerstone-crm' ) : __( 'Add Task', 'cornerstone-crm' ) ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TASKS ) ); ?>" class="button-link"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
	</form>
</div>

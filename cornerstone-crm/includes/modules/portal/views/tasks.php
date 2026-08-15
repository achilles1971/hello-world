<?php
/**
 * Tasks section — list and form share this file, branching on $mode.
 *
 * List mode:
 * @var array      $items     Rows for the current page.
 * @var int        $total     Total matching rows.
 * @var int        $page      Current page number.
 * @var int|string $completed Active completed filter ('' = all, 0, or 1).
 * @var array      $contacts  Map of contact_id => display name for rows shown.
 *
 * Form mode:
 * @var array|null $task                Existing row, or null when creating.
 * @var array      $form_contacts       Contacts in scope, for the picker.
 * @var int        $default_contact_id  Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$router = 'Cornerstone_Portal_Router';
?>

<?php if ( 'form' === $mode ) :
	$is_edit    = null !== $task;
	$contact_id = $is_edit ? (int) $task['contact_id'] : $default_contact_id;
	?>
	<div class="cs-page-head">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Task', 'cornerstone-crm' ) : esc_html__( 'Add Task', 'cornerstone-crm' ); ?></h1>
	</div>

	<?php if ( empty( $form_contacts ) ) : ?>
		<div class="cs-card">
			<p><?php esc_html_e( 'Add a contact first before creating a follow-up task.', 'cornerstone-crm' ); ?></p>
			<a class="cs-btn cs-btn--primary" href="<?php echo esc_url( $router::url( 'contacts', 'new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		</div>
	<?php else : ?>
		<div class="cs-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cs-form">
				<?php wp_nonce_field( 'cornerstone_portal_save_task' ); ?>
				<input type="hidden" name="action" value="cornerstone_portal_save_task">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="id" value="<?php echo (int) $task['id']; ?>">
				<?php endif; ?>

				<div class="cs-field">
					<label for="contact_id"><?php esc_html_e( 'Contact', 'cornerstone-crm' ); ?></label>
					<select id="contact_id" name="contact_id" required>
						<option value=""><?php esc_html_e( '— Select —', 'cornerstone-crm' ); ?></option>
						<?php foreach ( $form_contacts as $c ) : ?>
							<option value="<?php echo (int) $c['id']; ?>" <?php selected( $contact_id, (int) $c['id'] ); ?>><?php echo esc_html( trim( $c['first_name'] . ' ' . $c['last_name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cs-field">
					<label for="description"><?php esc_html_e( 'Description', 'cornerstone-crm' ); ?></label>
					<textarea id="description" name="description" required><?php echo esc_textarea( $task['description'] ?? '' ); ?></textarea>
				</div>
				<div class="cs-field">
					<label for="due_date"><?php esc_html_e( 'Due Date', 'cornerstone-crm' ); ?></label>
					<input type="date" id="due_date" name="due_date" value="<?php echo esc_attr( $task['due_date'] ?? '' ); ?>">
				</div>
				<?php if ( $is_edit ) : ?>
					<div class="cs-field cs-field--checkbox">
						<input type="checkbox" id="completed" name="completed" value="1" <?php checked( ! empty( $task['completed'] ) ); ?>>
						<label for="completed"><?php esc_html_e( 'Mark as completed', 'cornerstone-crm' ); ?></label>
					</div>
				<?php endif; ?>

				<div class="cs-form__actions">
					<button type="submit" class="cs-btn cs-btn--primary"><?php echo $is_edit ? esc_html__( 'Update Task', 'cornerstone-crm' ) : esc_html__( 'Add Task', 'cornerstone-crm' ); ?></button>
					<a href="<?php echo esc_url( $router::url( 'tasks' ) ); ?>"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
				</div>
			</form>
		</div>
	<?php endif; ?>

<?php else : ?>

	<div class="cs-page-head">
		<h1><?php esc_html_e( 'Tasks', 'cornerstone-crm' ); ?></h1>
		<a href="<?php echo esc_url( $router::url( 'tasks', 'new' ) ); ?>" class="cs-btn cs-btn--primary"><?php esc_html_e( 'Add New Task', 'cornerstone-crm' ); ?></a>
	</div>

	<form method="get" class="cs-filters">
		<select name="completed" onchange="this.form.submit()">
			<option value="" <?php selected( $completed, '' ); ?>><?php esc_html_e( 'All tasks', 'cornerstone-crm' ); ?></option>
			<option value="0" <?php selected( $completed, 0 ); ?>><?php esc_html_e( 'Open', 'cornerstone-crm' ); ?></option>
			<option value="1" <?php selected( $completed, 1 ); ?>><?php esc_html_e( 'Completed', 'cornerstone-crm' ); ?></option>
		</select>
		<noscript><button type="submit" class="cs-btn cs-btn--secondary cs-btn--small"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button></noscript>
	</form>

	<div class="cs-card" style="padding:0">
		<div class="cs-table-wrap">
			<table class="cs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Contact', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Description', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Due Date', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Completed', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="5" class="cs-empty"><?php esc_html_e( 'No tasks yet.', 'cornerstone-crm' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></strong></td>
							<td><?php echo esc_html( wp_trim_words( $item['description'], 12 ) ); ?></td>
							<td class="cs-num"><?php echo $item['due_date'] ? esc_html( mysql2date( 'M j, Y', $item['due_date'] ) ) : ''; ?></td>
							<td>
								<?php if ( $item['completed'] ) : ?>
									<span class="cs-badge" style="background:var(--cs-success-soft);color:var(--cs-success)"><?php esc_html_e( 'Completed', 'cornerstone-crm' ); ?></span>
								<?php else : ?>
									<span class="cs-badge cs-badge--muted"><?php esc_html_e( 'Open', 'cornerstone-crm' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="cs-table__actions">
								<a href="<?php echo esc_url( $router::url( 'tasks', 'edit', (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this task?', 'cornerstone-crm' ) ); ?>');">
									<?php wp_nonce_field( 'cornerstone_portal_delete_task' ); ?>
									<input type="hidden" name="action" value="cornerstone_portal_delete_task">
									<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
									<button type="submit" class="cs-link-danger"><?php esc_html_e( 'Delete', 'cornerstone-crm' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php
	$pagination_args = '' !== $completed ? [ 'completed' => $completed ] : [];
	Cornerstone_Portal_Views::pagination( $page, $total, 20, 'tasks', $pagination_args );
	?>

<?php endif; ?>

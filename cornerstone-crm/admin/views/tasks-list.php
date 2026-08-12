<?php
/**
 * Tasks list view.
 *
 * @var array      $items     Rows for the current page.
 * @var int        $total     Total matching rows.
 * @var int        $page      Current page number.
 * @var int|string $completed Active completed filter ('' = all, 0, or 1).
 * @var array      $contacts  Map of contact_id => display name for rows shown.
 * @var string     $notice    Notice key to display, if any.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cornerstone-crm">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TASKS . '&view=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Task', 'cornerstone-crm' ); ?></a>
	<hr class="wp-header-end">

	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_TASKS ); ?>
	<?php Cornerstone_Admin::notice_html( $notice ?? '' ); ?>

	<form method="get" class="cornerstone-crm-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Cornerstone_Admin::SLUG_TASKS ); ?>">
		<label for="completed" class="screen-reader-text"><?php esc_html_e( 'Filter by completion', 'cornerstone-crm' ); ?></label>
		<select name="completed" id="completed">
			<option value="" <?php selected( $completed, '' ); ?>><?php esc_html_e( 'All tasks', 'cornerstone-crm' ); ?></option>
			<option value="0" <?php selected( $completed, 0 ); ?>><?php esc_html_e( 'Open', 'cornerstone-crm' ); ?></option>
			<option value="1" <?php selected( $completed, 1 ); ?>><?php esc_html_e( 'Completed', 'cornerstone-crm' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
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
				<tr><td colspan="5"><?php esc_html_e( 'No tasks yet.', 'cornerstone-crm' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $item['description'], 12 ) ); ?></td>
					<td><?php echo $item['due_date'] ? esc_html( mysql2date( 'M j, Y', $item['due_date'] ) ) : ''; ?></td>
					<td><?php echo $item['completed'] ? esc_html__( 'Yes', 'cornerstone-crm' ) : esc_html__( 'No', 'cornerstone-crm' ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TASKS . '&view=edit&id=' . (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
						|
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-inline-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this task?', 'cornerstone-crm' ) ); ?>');">
							<?php wp_nonce_field( 'cornerstone_delete_task' ); ?>
							<input type="hidden" name="action" value="cornerstone_delete_task">
							<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'cornerstone-crm' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php Cornerstone_Admin::pagination_html( $page, $total, 20, Cornerstone_Admin::SLUG_TASKS, '' !== $completed ? [ 'completed' => $completed ] : [] ); ?>
</div>

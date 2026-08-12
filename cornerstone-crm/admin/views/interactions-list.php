<?php
/**
 * Interactions list view.
 *
 * @var array  $items    Rows for the current page.
 * @var int    $total    Total matching rows.
 * @var int    $page     Current page number.
 * @var string $type     Active type filter.
 * @var array  $contacts Map of contact_id => display name for rows shown.
 * @var string $notice   Notice key to display, if any.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cornerstone-crm">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_INTERACTIONS . '&view=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Interaction', 'cornerstone-crm' ); ?></a>
	<hr class="wp-header-end">

	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_INTERACTIONS ); ?>
	<?php Cornerstone_Admin::notice_html( $notice ?? '' ); ?>

	<form method="get" class="cornerstone-crm-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Cornerstone_Admin::SLUG_INTERACTIONS ); ?>">
		<label for="type" class="screen-reader-text"><?php esc_html_e( 'Filter by type', 'cornerstone-crm' ); ?></label>
		<select name="type" id="type">
			<option value=""><?php esc_html_e( 'All types', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::INTERACTION_TYPES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $type, $option ); ?>><?php echo esc_html( ucwords( $option ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Contact', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Type', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Note', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Occurred', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No interactions yet.', 'cornerstone-crm' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></td>
					<td><?php echo esc_html( ucwords( $item['type'] ) ); ?></td>
					<td><?php echo esc_html( wp_trim_words( $item['note'], 12 ) ); ?></td>
					<td><?php echo esc_html( mysql2date( 'M j, Y', $item['occurred_at'] ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_INTERACTIONS . '&view=edit&id=' . (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
						|
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-inline-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this interaction?', 'cornerstone-crm' ) ); ?>');">
							<?php wp_nonce_field( 'cornerstone_delete_interaction' ); ?>
							<input type="hidden" name="action" value="cornerstone_delete_interaction">
							<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'cornerstone-crm' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php Cornerstone_Admin::pagination_html( $page, $total, 20, Cornerstone_Admin::SLUG_INTERACTIONS, $type ? [ 'type' => $type ] : [] ); ?>
</div>

<?php
/**
 * Contacts list view.
 *
 * @var array  $items          Rows for the current page.
 * @var int    $total          Total matching rows.
 * @var int    $page           Current page number.
 * @var string $status         Active pipeline_status filter.
 * @var string $notice         Notice key to display, if any.
 * @var bool   $can_manage_all Whether the current user sees every agent's contacts.
 * @var array  $owners         Map of user_id => owning agent's display name (broker view only).
 */

defined( 'ABSPATH' ) || exit;

$colspan = $can_manage_all ? 7 : 6;
?>
<div class="wrap cornerstone-crm">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS . '&view=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Contact', 'cornerstone-crm' ); ?></a>
	<hr class="wp-header-end">

	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_CONTACTS ); ?>
	<?php Cornerstone_Admin::notice_html( $notice ?? '' ); ?>

	<form method="get" class="cornerstone-crm-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Cornerstone_Admin::SLUG_CONTACTS ); ?>">
		<label for="pipeline_status" class="screen-reader-text"><?php esc_html_e( 'Filter by pipeline status', 'cornerstone-crm' ); ?></label>
		<select name="pipeline_status" id="pipeline_status">
			<option value=""><?php esc_html_e( 'All pipeline statuses', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::PIPELINE_STATUSES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $option ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'cornerstone-crm' ); ?></th>
				<?php if ( $can_manage_all ) : ?>
					<th><?php esc_html_e( 'Agent', 'cornerstone-crm' ); ?></th>
				<?php endif; ?>
				<th><?php esc_html_e( 'Phone', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Email', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Role', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Pipeline Status', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="<?php echo (int) $colspan; ?>"><?php esc_html_e( 'No contacts yet.', 'cornerstone-crm' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( trim( $item['first_name'] . ' ' . $item['last_name'] ) ); ?></td>
					<?php if ( $can_manage_all ) : ?>
						<td><?php echo esc_html( $owners[ (int) $item['user_id'] ] ?? '' ); ?></td>
					<?php endif; ?>
					<td><?php echo esc_html( $item['phone'] ); ?></td>
					<td><?php echo esc_html( $item['email'] ); ?></td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $item['role_tag'] ) ) ); ?></td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $item['pipeline_status'] ) ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_CONTACTS . '&view=edit&id=' . (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
						|
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TASKS . '&view=new&contact_id=' . (int) $item['id'] ) ); ?>"><?php esc_html_e( '+ Task', 'cornerstone-crm' ); ?></a>
						|
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-inline-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this contact?', 'cornerstone-crm' ) ); ?>');">
							<?php wp_nonce_field( 'cornerstone_delete_contact' ); ?>
							<input type="hidden" name="action" value="cornerstone_delete_contact">
							<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'cornerstone-crm' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php Cornerstone_Admin::pagination_html( $page, $total, 20, Cornerstone_Admin::SLUG_CONTACTS, $status ? [ 'pipeline_status' => $status ] : [] ); ?>
</div>

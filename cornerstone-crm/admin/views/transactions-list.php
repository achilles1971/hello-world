<?php
/**
 * Transactions list view.
 *
 * @var array  $items    Rows for the current page.
 * @var int    $total    Total matching rows.
 * @var int    $page     Current page number.
 * @var string $status   Active status filter.
 * @var array  $contacts Map of contact_id => display name for rows shown.
 * @var string $notice   Notice key to display, if any.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap cornerstone-crm">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TRANSACTIONS . '&view=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Transaction', 'cornerstone-crm' ); ?></a>
	<hr class="wp-header-end">

	<?php Cornerstone_Admin::nav_html( Cornerstone_Admin::SLUG_TRANSACTIONS ); ?>
	<?php Cornerstone_Admin::notice_html( $notice ?? '' ); ?>

	<form method="get" class="cornerstone-crm-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( Cornerstone_Admin::SLUG_TRANSACTIONS ); ?>">
		<label for="status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'cornerstone-crm' ); ?></label>
		<select name="status" id="status">
			<option value=""><?php esc_html_e( 'All statuses', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::TRANSACTION_STATUSES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( ucwords( $option ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Contact', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Property', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Type', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Price', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No transactions yet.', 'cornerstone-crm' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></td>
					<td><?php echo esc_html( $item['property_address'] ); ?></td>
					<td><?php echo esc_html( ucwords( $item['transaction_type'] ) ); ?></td>
					<td><?php echo esc_html( ucwords( $item['status'] ) ); ?></td>
					<td><?php echo null !== $item['price'] ? esc_html( '$' . number_format( (float) $item['price'], 2 ) ) : ''; ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Cornerstone_Admin::SLUG_TRANSACTIONS . '&view=edit&id=' . (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
						|
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-inline-delete" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this transaction?', 'cornerstone-crm' ) ); ?>');">
							<?php wp_nonce_field( 'cornerstone_delete_transaction' ); ?>
							<input type="hidden" name="action" value="cornerstone_delete_transaction">
							<input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'cornerstone-crm' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php Cornerstone_Admin::pagination_html( $page, $total, 20, Cornerstone_Admin::SLUG_TRANSACTIONS, $status ? [ 'status' => $status ] : [] ); ?>
</div>

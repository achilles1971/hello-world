<?php
/**
 * Transactions section — list and form share this file, branching on $mode.
 *
 * List mode:
 * @var array  $items    Rows for the current page.
 * @var int    $total    Total matching rows.
 * @var int    $page     Current page number.
 * @var string $status   Active status filter.
 * @var array  $contacts Map of contact_id => display name for rows shown.
 *
 * Form mode:
 * @var array|null $transaction        Existing row, or null when creating.
 * @var array      $form_contacts      Contacts in scope, for the picker.
 * @var int        $default_contact_id Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$router = 'Cornerstone_Portal_Router';
?>

<?php if ( 'form' === $mode ) :
	$is_edit    = null !== $transaction;
	$contact_id = $is_edit ? (int) $transaction['contact_id'] : $default_contact_id;

	$key_dates = [];
	if ( $is_edit && ! empty( $transaction['key_dates'] ) ) {
		$decoded   = json_decode( $transaction['key_dates'], true );
		$key_dates = is_array( $decoded ) ? $decoded : [];
	}
	?>
	<div class="cs-page-head">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Transaction', 'cornerstone-crm' ) : esc_html__( 'Add Transaction', 'cornerstone-crm' ); ?></h1>
	</div>

	<?php if ( empty( $form_contacts ) ) : ?>
		<div class="cs-card">
			<p><?php esc_html_e( 'Add a contact first before creating a transaction.', 'cornerstone-crm' ); ?></p>
			<a class="cs-btn cs-btn--primary" href="<?php echo esc_url( $router::url( 'contacts', 'new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		</div>
	<?php else : ?>
		<div class="cs-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cs-form">
				<?php wp_nonce_field( 'cornerstone_portal_save_transaction' ); ?>
				<input type="hidden" name="action" value="cornerstone_portal_save_transaction">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="id" value="<?php echo (int) $transaction['id']; ?>">
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
					<label for="property_address"><?php esc_html_e( 'Property Address', 'cornerstone-crm' ); ?></label>
					<input type="text" id="property_address" name="property_address" value="<?php echo esc_attr( $transaction['property_address'] ?? '' ); ?>" required>
				</div>
				<div class="cs-field">
					<label for="transaction_type"><?php esc_html_e( 'Transaction Type', 'cornerstone-crm' ); ?></label>
					<select id="transaction_type" name="transaction_type">
						<?php foreach ( Cornerstone_Validate::TRANSACTION_TYPES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $transaction['transaction_type'] ?? 'buy', $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cs-field">
					<label for="status"><?php esc_html_e( 'Status', 'cornerstone-crm' ); ?></label>
					<select id="status" name="status">
						<?php foreach ( Cornerstone_Validate::TRANSACTION_STATUSES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $transaction['status'] ?? 'active', $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cs-field">
					<label for="price"><?php esc_html_e( 'Price', 'cornerstone-crm' ); ?></label>
					<input type="number" step="0.01" min="0" id="price" name="price" value="<?php echo esc_attr( $transaction['price'] ?? '' ); ?>">
				</div>
				<div class="cs-field">
					<label><?php esc_html_e( 'Key Dates', 'cornerstone-crm' ); ?></label>
					<p class="description"><?php esc_html_e( 'Optional milestones, e.g. "inspection", "closing." Leave a row blank to skip it.', 'cornerstone-crm' ); ?></p>
					<?php
					$rows = $key_dates ?: [ '' => '' ];
					foreach ( $rows as $milestone => $date ) :
						?>
						<div class="cs-key-dates-row">
							<input type="text" name="key_date_labels[]" placeholder="<?php esc_attr_e( 'Milestone', 'cornerstone-crm' ); ?>" value="<?php echo esc_attr( is_string( $milestone ) ? $milestone : '' ); ?>">
							<input type="date" name="key_date_values[]" value="<?php echo esc_attr( $date ); ?>">
						</div>
					<?php endforeach; ?>
					<div class="cs-key-dates-row">
						<input type="text" name="key_date_labels[]" placeholder="<?php esc_attr_e( 'Milestone', 'cornerstone-crm' ); ?>">
						<input type="date" name="key_date_values[]">
					</div>
				</div>

				<div class="cs-form__actions">
					<button type="submit" class="cs-btn cs-btn--primary"><?php echo $is_edit ? esc_html__( 'Update Transaction', 'cornerstone-crm' ) : esc_html__( 'Add Transaction', 'cornerstone-crm' ); ?></button>
					<a href="<?php echo esc_url( $router::url( 'transactions' ) ); ?>"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
				</div>
			</form>
		</div>
	<?php endif; ?>

<?php else : ?>

	<div class="cs-page-head">
		<h1><?php esc_html_e( 'Transactions', 'cornerstone-crm' ); ?></h1>
		<a href="<?php echo esc_url( $router::url( 'transactions', 'new' ) ); ?>" class="cs-btn cs-btn--primary"><?php esc_html_e( 'Add New Transaction', 'cornerstone-crm' ); ?></a>
	</div>

	<form method="get" class="cs-filters">
		<select name="status" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( 'All statuses', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::TRANSACTION_STATUSES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="cs-btn cs-btn--secondary cs-btn--small"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button></noscript>
	</form>

	<div class="cs-card" style="padding:0">
		<div class="cs-table-wrap">
			<table class="cs-table">
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
						<tr><td colspan="6" class="cs-empty"><?php esc_html_e( 'No transactions yet.', 'cornerstone-crm' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></strong></td>
							<td><?php echo esc_html( $item['property_address'] ); ?></td>
							<td><span class="cs-badge"><?php echo esc_html( Cornerstone_Portal_Views::label( $item['transaction_type'] ) ); ?></span></td>
							<td><span class="cs-badge cs-badge--gold"><?php echo esc_html( Cornerstone_Portal_Views::label( $item['status'] ) ); ?></span></td>
							<td class="cs-num"><?php echo null !== $item['price'] ? esc_html( '$' . number_format( (float) $item['price'], 2 ) ) : ''; ?></td>
							<td class="cs-table__actions">
								<a href="<?php echo esc_url( $router::url( 'transactions', 'edit', (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this transaction?', 'cornerstone-crm' ) ); ?>');">
									<?php wp_nonce_field( 'cornerstone_portal_delete_transaction' ); ?>
									<input type="hidden" name="action" value="cornerstone_portal_delete_transaction">
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
	$pagination_args = $status ? [ 'status' => $status ] : [];
	Cornerstone_Portal_Views::pagination( $page, $total, 20, 'transactions', $pagination_args );
	?>

<?php endif; ?>

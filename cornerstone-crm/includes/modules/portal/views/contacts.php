<?php
/**
 * Contacts section — list and form share this file, branching on $mode.
 *
 * List mode:
 * @var array  $items          Rows for the current page.
 * @var int    $total          Total matching rows.
 * @var int    $page           Current page number.
 * @var string $status         Active pipeline_status filter.
 * @var int    $agent_id       Active agent filter (0 = all; broker view only).
 * @var array  $owners         Map of user_id => owning agent's display name (broker view only).
 * @var array  $agent_choices  Map of user_id => display name for the Agent filter (broker view only).
 *
 * Form mode:
 * @var array|null $contact    Existing contact row, or null when creating.
 *
 * Both:
 * @var bool $can_manage_all
 */

defined( 'ABSPATH' ) || exit;

$router = 'Cornerstone_Portal_Router';
?>

<?php if ( 'form' === $mode ) :
	$is_edit = null !== $contact;
	?>
	<div class="cs-page-head">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Contact', 'cornerstone-crm' ) : esc_html__( 'Add Contact', 'cornerstone-crm' ); ?></h1>
	</div>
	<div class="cs-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cs-form">
			<?php wp_nonce_field( 'cornerstone_portal_save_contact' ); ?>
			<input type="hidden" name="action" value="cornerstone_portal_save_contact">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="id" value="<?php echo (int) $contact['id']; ?>">
			<?php endif; ?>

			<div class="cs-field">
				<label for="first_name"><?php esc_html_e( 'First Name', 'cornerstone-crm' ); ?></label>
				<input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $contact['first_name'] ?? '' ); ?>">
			</div>
			<div class="cs-field">
				<label for="last_name"><?php esc_html_e( 'Last Name', 'cornerstone-crm' ); ?></label>
				<input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $contact['last_name'] ?? '' ); ?>">
			</div>
			<div class="cs-field">
				<label for="phone"><?php esc_html_e( 'Phone', 'cornerstone-crm' ); ?></label>
				<input type="text" id="phone" name="phone" value="<?php echo esc_attr( $contact['phone'] ?? '' ); ?>">
			</div>
			<div class="cs-field">
				<label for="email"><?php esc_html_e( 'Email', 'cornerstone-crm' ); ?></label>
				<input type="email" id="email" name="email" value="<?php echo esc_attr( $contact['email'] ?? '' ); ?>">
			</div>
			<div class="cs-field">
				<label for="role_tag"><?php esc_html_e( 'Role', 'cornerstone-crm' ); ?></label>
				<select id="role_tag" name="role_tag">
					<?php foreach ( Cornerstone_Validate::ROLE_TAGS as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $contact['role_tag'] ?? 'sphere', $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="cs-field">
				<label for="pipeline_status"><?php esc_html_e( 'Pipeline Status', 'cornerstone-crm' ); ?></label>
				<select id="pipeline_status" name="pipeline_status">
					<?php foreach ( Cornerstone_Validate::PIPELINE_STATUSES as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $contact['pipeline_status'] ?? 'new', $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="cs-field">
				<label for="notes"><?php esc_html_e( 'Notes', 'cornerstone-crm' ); ?></label>
				<textarea id="notes" name="notes"><?php echo esc_textarea( $contact['notes'] ?? '' ); ?></textarea>
			</div>

			<div class="cs-form__actions">
				<button type="submit" class="cs-btn cs-btn--primary"><?php echo $is_edit ? esc_html__( 'Update Contact', 'cornerstone-crm' ) : esc_html__( 'Add Contact', 'cornerstone-crm' ); ?></button>
				<a href="<?php echo esc_url( $router::url( 'contacts' ) ); ?>"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
			</div>
		</form>
	</div>

<?php else : ?>

	<div class="cs-page-head">
		<h1><?php esc_html_e( 'Contacts', 'cornerstone-crm' ); ?></h1>
		<a href="<?php echo esc_url( $router::url( 'contacts', 'new' ) ); ?>" class="cs-btn cs-btn--primary"><?php esc_html_e( 'Add New Contact', 'cornerstone-crm' ); ?></a>
	</div>

	<form method="get" class="cs-filters">
		<select name="pipeline_status" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( 'All pipeline statuses', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::PIPELINE_STATUSES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $status, $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $can_manage_all ) : ?>
			<select name="agent_id" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'All agents', 'cornerstone-crm' ); ?></option>
				<?php foreach ( $agent_choices as $choice_id => $choice_name ) : ?>
					<option value="<?php echo (int) $choice_id; ?>" <?php selected( $agent_id, $choice_id ); ?>><?php echo esc_html( $choice_name ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php endif; ?>
		<noscript><button type="submit" class="cs-btn cs-btn--secondary cs-btn--small"><?php esc_html_e( 'Filter', 'cornerstone-crm' ); ?></button></noscript>
	</form>

	<div class="cs-card" style="padding:0">
		<div class="cs-table-wrap">
			<table class="cs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'cornerstone-crm' ); ?></th>
						<?php if ( $can_manage_all ) : ?><th><?php esc_html_e( 'Agent', 'cornerstone-crm' ); ?></th><?php endif; ?>
						<th><?php esc_html_e( 'Phone', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Email', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Role', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Pipeline Status', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="<?php echo $can_manage_all ? 7 : 6; ?>" class="cs-empty"><?php esc_html_e( 'No contacts yet.', 'cornerstone-crm' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( trim( $item['first_name'] . ' ' . $item['last_name'] ) ); ?></strong></td>
							<?php if ( $can_manage_all ) : ?><td><?php echo esc_html( $owners[ (int) $item['user_id'] ] ?? '' ); ?></td><?php endif; ?>
							<td><?php echo esc_html( $item['phone'] ); ?></td>
							<td><?php echo esc_html( $item['email'] ); ?></td>
							<td><span class="cs-badge"><?php echo esc_html( Cornerstone_Portal_Views::label( $item['role_tag'] ) ); ?></span></td>
							<td><span class="cs-badge cs-badge--gold"><?php echo esc_html( Cornerstone_Portal_Views::label( $item['pipeline_status'] ) ); ?></span></td>
							<td class="cs-table__actions">
								<a href="<?php echo esc_url( $router::url( 'contacts', 'edit', (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
								<a href="<?php echo esc_url( add_query_arg( 'contact_id', (int) $item['id'], $router::url( 'tasks', 'new' ) ) ); ?>"><?php esc_html_e( '+ Task', 'cornerstone-crm' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this contact?', 'cornerstone-crm' ) ); ?>');">
									<?php wp_nonce_field( 'cornerstone_portal_delete_contact' ); ?>
									<input type="hidden" name="action" value="cornerstone_portal_delete_contact">
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
	$pagination_args = array_filter( [
		'pipeline_status' => $status,
		'agent_id'        => $agent_id ?: '',
	] );
	Cornerstone_Portal_Views::pagination( $page, $total, 20, 'contacts', $pagination_args );
	?>

<?php endif; ?>

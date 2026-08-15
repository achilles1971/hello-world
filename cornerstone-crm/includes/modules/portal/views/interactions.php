<?php
/**
 * Interactions section — list and form share this file, branching on $mode.
 *
 * List mode:
 * @var array  $items    Rows for the current page.
 * @var int    $total    Total matching rows.
 * @var int    $page     Current page number.
 * @var string $type     Active type filter.
 * @var array  $contacts Map of contact_id => display name for rows shown.
 *
 * Form mode:
 * @var array|null $interaction        Existing row, or null when creating.
 * @var array      $form_contacts      Contacts in scope, for the picker.
 * @var int        $default_contact_id Pre-selected contact_id when arriving from a contact's page.
 */

defined( 'ABSPATH' ) || exit;

$router = 'Cornerstone_Portal_Router';
?>

<?php if ( 'form' === $mode ) :
	$is_edit    = null !== $interaction;
	$contact_id = $is_edit ? (int) $interaction['contact_id'] : $default_contact_id;
	$occurred   = $is_edit ? substr( (string) $interaction['occurred_at'], 0, 10 ) : gmdate( 'Y-m-d' );
	?>
	<div class="cs-page-head">
		<h1><?php echo $is_edit ? esc_html__( 'Edit Interaction', 'cornerstone-crm' ) : esc_html__( 'Add Interaction', 'cornerstone-crm' ); ?></h1>
	</div>

	<?php if ( empty( $form_contacts ) ) : ?>
		<div class="cs-card">
			<p><?php esc_html_e( 'Add a contact first before logging an interaction.', 'cornerstone-crm' ); ?></p>
			<a class="cs-btn cs-btn--primary" href="<?php echo esc_url( $router::url( 'contacts', 'new' ) ); ?>"><?php esc_html_e( 'Add Contact', 'cornerstone-crm' ); ?></a>
		</div>
	<?php else : ?>
		<div class="cs-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cs-form">
				<?php wp_nonce_field( 'cornerstone_portal_save_interaction' ); ?>
				<input type="hidden" name="action" value="cornerstone_portal_save_interaction">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="id" value="<?php echo (int) $interaction['id']; ?>">
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
					<label for="type"><?php esc_html_e( 'Type', 'cornerstone-crm' ); ?></label>
					<select id="type" name="type">
						<?php foreach ( Cornerstone_Validate::INTERACTION_TYPES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $interaction['type'] ?? 'call', $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="cs-field">
					<label for="occurred_at"><?php esc_html_e( 'Date', 'cornerstone-crm' ); ?></label>
					<input type="date" id="occurred_at" name="occurred_at" value="<?php echo esc_attr( $occurred ); ?>">
				</div>
				<div class="cs-field">
					<label for="note"><?php esc_html_e( 'Note', 'cornerstone-crm' ); ?></label>
					<textarea id="note" name="note"><?php echo esc_textarea( $interaction['note'] ?? '' ); ?></textarea>
				</div>

				<div class="cs-form__actions">
					<button type="submit" class="cs-btn cs-btn--primary"><?php echo $is_edit ? esc_html__( 'Update Interaction', 'cornerstone-crm' ) : esc_html__( 'Add Interaction', 'cornerstone-crm' ); ?></button>
					<a href="<?php echo esc_url( $router::url( 'interactions' ) ); ?>"><?php esc_html_e( 'Cancel', 'cornerstone-crm' ); ?></a>
				</div>
			</form>
		</div>
	<?php endif; ?>

<?php else : ?>

	<div class="cs-page-head">
		<h1><?php esc_html_e( 'Interactions', 'cornerstone-crm' ); ?></h1>
		<a href="<?php echo esc_url( $router::url( 'interactions', 'new' ) ); ?>" class="cs-btn cs-btn--primary"><?php esc_html_e( 'Add New Interaction', 'cornerstone-crm' ); ?></a>
	</div>

	<form method="get" class="cs-filters">
		<select name="type" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( 'All types', 'cornerstone-crm' ); ?></option>
			<?php foreach ( Cornerstone_Validate::INTERACTION_TYPES as $option ) : ?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $type, $option ); ?>><?php echo esc_html( Cornerstone_Portal_Views::label( $option ) ); ?></option>
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
						<th><?php esc_html_e( 'Type', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Note', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Occurred', 'cornerstone-crm' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="5" class="cs-empty"><?php esc_html_e( 'No interactions yet.', 'cornerstone-crm' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $contacts[ (int) $item['contact_id'] ] ?? '' ); ?></strong></td>
							<td><span class="cs-badge"><?php echo esc_html( Cornerstone_Portal_Views::label( $item['type'] ) ); ?></span></td>
							<td><?php echo esc_html( wp_trim_words( $item['note'], 12 ) ); ?></td>
							<td class="cs-num"><?php echo esc_html( mysql2date( 'M j, Y', $item['occurred_at'] ) ); ?></td>
							<td class="cs-table__actions">
								<a href="<?php echo esc_url( $router::url( 'interactions', 'edit', (int) $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cornerstone-crm' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this interaction?', 'cornerstone-crm' ) ); ?>');">
									<?php wp_nonce_field( 'cornerstone_portal_delete_interaction' ); ?>
									<input type="hidden" name="action" value="cornerstone_portal_delete_interaction">
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
	$pagination_args = $type ? [ 'type' => $type ] : [];
	Cornerstone_Portal_Views::pagination( $page, $total, 20, 'interactions', $pagination_args );
	?>

<?php endif; ?>

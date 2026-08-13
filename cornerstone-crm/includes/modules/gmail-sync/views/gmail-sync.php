<?php
/**
 * Gmail Sync admin screen.
 *
 * @var bool  $can_manage_all  Whether the viewer is the broker/admin.
 * @var int   $current_user_id
 * @var array $rows            One entry per visible user: user_id, display_name,
 *                              connected, email, last_synced_at, status.
 */

defined( 'ABSPATH' ) || exit;

$notice           = sanitize_key( $_GET['gmail_notice'] ?? '' );
$crypto_ready      = Cornerstone_Gmail_Crypto::is_configured();
$settings_ready    = Cornerstone_Gmail_Settings::is_configured();
$connect_url_base  = wp_nonce_url( admin_url( 'admin-post.php?action=cornerstone_gmail_connect_start' ), 'cornerstone_gmail_connect_start' );
?>
<div class="wrap cornerstone-crm">
	<h1><?php esc_html_e( 'Cornerstone CRM', 'cornerstone-crm' ); ?></h1>
	<?php Cornerstone_Admin::nav_html( Cornerstone_Gmail_Admin::SLUG ); ?>

	<?php if ( $notice ) :
		$text  = Cornerstone_Gmail_Admin::notice_text( $notice );
		$class = in_array( $notice, [ 'sync_ok', 'connected', 'disconnected', 'settings_saved' ], true ) ? 'notice-success' : 'notice-warning';
		if ( $text ) :
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p><?php echo esc_html( $text ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( ! $crypto_ready ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Gmail sync is disabled.', 'cornerstone-crm' ); ?></strong>
				<?php esc_html_e( 'CORNERSTONE_CRM_ENCRYPTION_KEY is not defined in wp-config.php. This key encrypts stored Gmail credentials at rest and must be set before anything here will work.', 'cornerstone-crm' ); ?>
				<?php esc_html_e( 'See the setup guide in the plugin README for how to generate and add it.', 'cornerstone-crm' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( $can_manage_all ) : ?>
		<h2><?php esc_html_e( 'Google app settings', 'cornerstone-crm' ); ?></h2>
		<p class="description"><?php esc_html_e( 'One Google Cloud OAuth app, shared by every agent — each agent then connects their own mailbox to it below.', 'cornerstone-crm' ); ?></p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Redirect URI', 'cornerstone-crm' ); ?></th>
				<td>
					<input type="text" readonly class="regular-text" style="width:100%;max-width:520px" value="<?php echo esc_attr( Cornerstone_Gmail_OAuth::redirect_uri() ); ?>" onclick="this.select();">
					<p class="description"><?php esc_html_e( 'Add this exact URL as an Authorized redirect URI on the OAuth Client ID in Google Cloud Console.', 'cornerstone-crm' ); ?></p>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cornerstone-crm-form">
			<?php wp_nonce_field( 'cornerstone_gmail_save_settings' ); ?>
			<input type="hidden" name="action" value="cornerstone_gmail_save_settings">
			<table class="form-table">
				<tr>
					<th><label for="client_id"><?php esc_html_e( 'Client ID', 'cornerstone-crm' ); ?></label></th>
					<td><input type="text" id="client_id" name="client_id" class="regular-text" value="<?php echo esc_attr( Cornerstone_Gmail_Settings::client_id() ); ?>"></td>
				</tr>
				<tr>
					<th><label for="client_secret"><?php esc_html_e( 'Client Secret', 'cornerstone-crm' ); ?></label></th>
					<td>
						<input type="password" id="client_secret" name="client_secret" class="regular-text" autocomplete="off" placeholder="<?php echo $settings_ready ? esc_attr__( 'Saved — leave blank to keep it', 'cornerstone-crm' ) : ''; ?>">
						<p class="description"><?php esc_html_e( 'Stored encrypted. Never shown again after saving — leave this blank on future saves to keep the current secret.', 'cornerstone-crm' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Gmail Settings', 'cornerstone-crm' ) ); ?>
		</form>
		<hr>
	<?php elseif ( ! $settings_ready ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Gmail sync is not set up yet — ask your broker to enter the Google app credentials first.', 'cornerstone-crm' ); ?></p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Connections', 'cornerstone-crm' ); ?></h2>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Agent', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Gmail Account', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Last Synced', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Status', 'cornerstone-crm' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'cornerstone-crm' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) :
				$is_self = $row['user_id'] === $current_user_id;
				?>
				<tr>
					<td><?php echo esc_html( $row['display_name'] ); ?><?php echo $is_self ? ' ' . esc_html__( '(you)', 'cornerstone-crm' ) : ''; ?></td>
					<td><?php echo $row['connected'] ? esc_html( $row['email'] ) : esc_html__( 'Not connected', 'cornerstone-crm' ); ?></td>
					<td><?php echo $row['last_synced_at'] ? esc_html( human_time_diff( $row['last_synced_at'] ) . ' ' . __( 'ago', 'cornerstone-crm' ) ) : esc_html__( 'Never', 'cornerstone-crm' ); ?></td>
					<td><?php echo esc_html( $row['status'] ?: '—' ); ?></td>
					<td>
						<?php if ( $is_self ) : ?>
							<?php if ( $crypto_ready && $settings_ready ) : ?>
								<a href="<?php echo esc_url( $connect_url_base ); ?>" class="button button-small"><?php echo $row['connected'] ? esc_html__( 'Reconnect', 'cornerstone-crm' ) : esc_html__( 'Connect Gmail', 'cornerstone-crm' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php if ( $row['connected'] && ( $is_self || $can_manage_all ) ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'cornerstone_gmail_sync_now' ); ?>
								<input type="hidden" name="action" value="cornerstone_gmail_sync_now">
								<input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Sync Now', 'cornerstone-crm' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect this Gmail account?', 'cornerstone-crm' ) ); ?>');">
								<?php wp_nonce_field( 'cornerstone_gmail_disconnect' ); ?>
								<input type="hidden" name="action" value="cornerstone_gmail_disconnect">
								<input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
								<button type="submit" class="button-link-delete"><?php esc_html_e( 'Disconnect', 'cornerstone-crm' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="description">
		<?php esc_html_e( 'Only the account owner can connect their own Gmail — a broker can disconnect or trigger a sync for any agent, but never authorize on their behalf.', 'cornerstone-crm' ); ?>
		<?php esc_html_e( 'Only email metadata (recipient, subject, date) is ever read — message content is never accessed or stored.', 'cornerstone-crm' ); ?>
	</p>
</div>

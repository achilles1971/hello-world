<?php
/**
 * Admin UI for the Gmail sync module: a "Gmail Sync" tab alongside the
 * core CRM screens, with a broker-only settings section (Client ID/
 * Secret) and a per-agent connection status table.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Admin {

	public const SLUG = 'cornerstone-crm-gmail';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_filter( 'cornerstone_crm_admin_nav_tabs', [ __CLASS__, 'add_nav_tab' ] );

		add_action( 'admin_post_cornerstone_gmail_save_settings', [ __CLASS__, 'handle_save_settings' ] );
		add_action( 'admin_post_cornerstone_gmail_disconnect', [ __CLASS__, 'handle_disconnect' ] );
		add_action( 'admin_post_cornerstone_gmail_sync_now', [ __CLASS__, 'handle_sync_now' ] );
	}

	public static function add_nav_tab( array $tabs ): array {
		$tabs[ self::SLUG ] = __( 'Gmail Sync', 'cornerstone-crm' );
		return $tabs;
	}

	public static function register_menu(): void {
		add_submenu_page(
			Cornerstone_Admin::SLUG_CONTACTS,
			__( 'Gmail Sync', 'cornerstone-crm' ),
			__( 'Gmail Sync', 'cornerstone-crm' ),
			Cornerstone_Roles::CAP_ACCESS,
			self::SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	private static function require_access(): void {
		if ( ! Cornerstone_Roles::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to access the CRM.', 'cornerstone-crm' ), 403 );
		}
	}

	public static function render_page(): void {
		self::require_access();

		$can_manage_all = Cornerstone_Roles::can_manage_all();
		$current_user_id = get_current_user_id();

		$users = $can_manage_all ? Cornerstone_Roles::crm_users() : [ wp_get_current_user() ];

		$rows = [];
		foreach ( $users as $user ) {
			$rows[] = [
				'user_id'        => (int) $user->ID,
				'display_name'   => $user->display_name,
				'connected'      => Cornerstone_Gmail_Connection::is_connected( (int) $user->ID ),
				'email'          => Cornerstone_Gmail_Connection::connected_email( (int) $user->ID ),
				'last_synced_at' => Cornerstone_Gmail_Connection::last_synced_at( (int) $user->ID ),
				'status'         => Cornerstone_Gmail_Connection::last_sync_status( (int) $user->ID ),
			];
		}

		require CORNERSTONE_CRM_PATH . 'includes/modules/gmail-sync/views/gmail-sync.php';
	}

	// -----------------------------------------------------------------
	// Form handlers
	// -----------------------------------------------------------------

	public static function handle_save_settings(): void {
		self::require_access();
		if ( ! Cornerstone_Roles::can_manage_all() ) {
			wp_die( esc_html__( 'Only the broker can change Gmail sync settings.', 'cornerstone-crm' ), 403 );
		}
		check_admin_referer( 'cornerstone_gmail_save_settings' );

		$client_id     = sanitize_text_field( wp_unslash( $_POST['client_id'] ?? '' ) );
		$client_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ?? '' ) );

		$saved  = Cornerstone_Gmail_Settings::save( $client_id, $client_secret );
		$notice = $saved ? 'settings_saved' : 'encryption_not_configured';

		self::redirect_with_notice( $notice );
	}

	public static function handle_disconnect(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_gmail_disconnect' );

		$target_user_id = self::resolve_target_user( absint( $_POST['user_id'] ?? 0 ) );
		if ( null === $target_user_id ) {
			self::redirect_with_notice( 'forbidden' );
		}

		Cornerstone_Gmail_Connection::disconnect( $target_user_id );
		self::redirect_with_notice( 'disconnected' );
	}

	public static function handle_sync_now(): void {
		self::require_access();
		check_admin_referer( 'cornerstone_gmail_sync_now' );

		$target_user_id = self::resolve_target_user( absint( $_POST['user_id'] ?? 0 ) );
		if ( null === $target_user_id ) {
			self::redirect_with_notice( 'forbidden' );
		}

		$result = Cornerstone_Gmail_Sync::sync_for_user( $target_user_id );
		self::redirect_with_notice( is_wp_error( $result ) ? 'sync_failed' : 'sync_ok' );
	}

	/**
	 * A user may only act on their own connection unless they can manage
	 * all records (broker/admin), who may act on any agent's — e.g. to
	 * disconnect Gmail for an agent who's left the brokerage. Returns
	 * null if the request isn't allowed.
	 */
	private static function resolve_target_user( int $requested_user_id ): ?int {
		$current_user_id = get_current_user_id();
		if ( 0 === $requested_user_id || $requested_user_id === $current_user_id ) {
			return $current_user_id;
		}
		return Cornerstone_Roles::can_manage_all() ? $requested_user_id : null;
	}

	private static function redirect_with_notice( string $notice ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => self::SLUG, 'gmail_notice' => $notice ],
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Small fixed vocabulary of notice messages, same pattern as
	 * Cornerstone_Admin::notice_text() — only ever fed a sanitize_key()'d
	 * value read back from our own redirect URLs.
	 */
	public static function notice_text( string $key ): string {
		$messages = [
			'connected'                  => __( 'Gmail account connected.', 'cornerstone-crm' ),
			'disconnected'               => __( 'Gmail account disconnected.', 'cornerstone-crm' ),
			'denied'                     => __( 'Connection cancelled — Google reported that access was not granted.', 'cornerstone-crm' ),
			'state_mismatch'             => __( "Couldn't verify that request — please try connecting again.", 'cornerstone-crm' ),
			'no_code'                    => __( 'Google did not return an authorization code. Please try again.', 'cornerstone-crm' ),
			'exchange_failed'            => __( 'Could not complete the connection with Google. Please try again.', 'cornerstone-crm' ),
			'not_configured'             => __( 'Gmail sync is not set up yet — a broker needs to enter the Client ID and Secret below first.', 'cornerstone-crm' ),
			'encryption_not_configured'  => __( 'CORNERSTONE_CRM_ENCRYPTION_KEY is not set in wp-config.php. Add it before connecting or saving Gmail settings.', 'cornerstone-crm' ),
			'settings_saved'             => __( 'Gmail sync settings saved.', 'cornerstone-crm' ),
			'sync_ok'                    => __( 'Sync complete.', 'cornerstone-crm' ),
			'sync_failed'                => __( 'Sync failed — check the status column for details.', 'cornerstone-crm' ),
			'forbidden'                  => __( 'You do not have permission to do that.', 'cornerstone-crm' ),
		];
		return $messages[ $key ] ?? '';
	}
}

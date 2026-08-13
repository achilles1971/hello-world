<?php
/**
 * The OAuth dance itself: starting authorization and handling Google's
 * redirect back. Each agent authorizes their own mailbox — there is no
 * "connect on someone else's behalf" path here by design (see
 * Cornerstone_Gmail_Admin for the separate, broker-only disconnect).
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_OAuth {

	private const STATE_TRANSIENT_PREFIX = 'cornerstone_gmail_oauth_state_';
	private const STATE_TTL              = 10 * MINUTE_IN_SECONDS;

	public static function init(): void {
		add_action( 'admin_post_cornerstone_gmail_connect_start', [ __CLASS__, 'handle_connect_start' ] );
		add_action( 'admin_post_cornerstone_gmail_oauth_callback', [ __CLASS__, 'handle_callback' ] );
	}

	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=cornerstone_gmail_oauth_callback' );
	}

	/**
	 * Step 1: the agent clicks "Connect Gmail." Generates a one-time,
	 * per-user state token, stashes it server-side with a short expiry,
	 * and sends the browser to Google's consent screen.
	 */
	public static function handle_connect_start(): void {
		if ( ! Cornerstone_Roles::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'cornerstone-crm' ), 403 );
		}
		check_admin_referer( 'cornerstone_gmail_connect_start' );

		if ( ! Cornerstone_Gmail_Crypto::is_configured() ) {
			self::redirect_with_notice( 'encryption_not_configured' );
		}
		if ( ! Cornerstone_Gmail_Settings::is_configured() ) {
			self::redirect_with_notice( 'not_configured' );
		}

		$user_id = get_current_user_id();
		$state   = wp_generate_password( 32, false );
		set_transient( self::STATE_TRANSIENT_PREFIX . $user_id, $state, self::STATE_TTL );

		$url = Cornerstone_Gmail_Client::authorize_url(
			Cornerstone_Gmail_Settings::client_id(),
			self::redirect_uri(),
			$state
		);

		wp_redirect( $url ); // phpcs-ignore -- external OAuth provider URL, not a local redirect.
		exit;
	}

	/**
	 * Step 2: Google sends the browser back here with either an
	 * authorization code or an error. Every branch validates that the
	 * still-logged-in user matches whoever started this specific flow,
	 * via the transient keyed to their user ID — a mismatched or expired
	 * state (wrong user, replay, tampered redirect) is rejected outright.
	 */
	public static function handle_callback(): void {
		if ( ! Cornerstone_Roles::can_access() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'cornerstone-crm' ), 403 );
		}

		$user_id        = get_current_user_id();
		$transient_key  = self::STATE_TRANSIENT_PREFIX . $user_id;
		$expected_state = get_transient( $transient_key );
		delete_transient( $transient_key ); // Single-use regardless of outcome.

		$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		if ( ! $expected_state || ! hash_equals( (string) $expected_state, $state ) ) {
			self::redirect_with_notice( 'state_mismatch' );
		}

		if ( ! empty( $_GET['error'] ) ) {
			// The agent declined consent, or Google returned some other
			// error — not a security event, just "didn't connect."
			self::redirect_with_notice( 'denied' );
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		if ( '' === $code ) {
			self::redirect_with_notice( 'no_code' );
		}

		$tokens = Cornerstone_Gmail_Client::exchange_code(
			Cornerstone_Gmail_Settings::client_id(),
			Cornerstone_Gmail_Settings::client_secret(),
			$code,
			self::redirect_uri()
		);
		if ( is_wp_error( $tokens ) ) {
			self::redirect_with_notice( 'exchange_failed' );
		}

		$profile = Cornerstone_Gmail_Client::get_profile( $tokens['access_token'] );
		$email   = is_wp_error( $profile ) ? '' : (string) ( $profile['emailAddress'] ?? '' );

		$saved = Cornerstone_Gmail_Connection::save( $user_id, $email, $tokens['refresh_token'] );
		self::redirect_with_notice( $saved ? 'connected' : 'encryption_not_configured' );
	}

	private static function redirect_with_notice( string $notice ): void {
		wp_safe_redirect( add_query_arg(
			[ 'page' => Cornerstone_Gmail_Admin::SLUG, 'gmail_notice' => $notice ],
			admin_url( 'admin.php' )
		) );
		exit;
	}
}

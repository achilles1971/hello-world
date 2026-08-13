<?php
/**
 * Scheduling: a custom 20-minute WP-Cron interval that syncs every
 * connected agent in turn. One agent's failure (revoked token, API
 * error) doesn't stop the others from syncing.
 *
 * WP-Cron only fires on incoming site traffic by default, which is
 * unreliable on a low-traffic site. See the module's setup notes for
 * pointing a real server cron at wp-cron.php.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Cron {

	public const HOOK = 'cornerstone_gmail_cron_sync';

	private const INTERVAL_SLUG = 'cornerstone_gmail_20min';

	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'register_interval' ] );
		add_action( self::HOOK, [ __CLASS__, 'run' ] );
		self::maybe_schedule();
	}

	public static function register_interval( array $schedules ): array {
		$schedules[ self::INTERVAL_SLUG ] = [
			'interval' => 20 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 20 minutes (Cornerstone Gmail sync)', 'cornerstone-crm' ),
		];
		return $schedules;
	}

	/**
	 * Idempotent: only schedules if nothing is already queued, so this
	 * is safe to call on every request (it's hooked from module init,
	 * which runs on every page load) without creating duplicate events.
	 */
	private static function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), self::INTERVAL_SLUG, self::HOOK );
		}
	}

	public static function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public static function run(): void {
		if ( ! Cornerstone_Gmail_Settings::is_configured() ) {
			return;
		}

		foreach ( Cornerstone_Gmail_Connection::connected_user_ids() as $user_id ) {
			Cornerstone_Gmail_Sync::sync_for_user( $user_id );
		}
	}
}

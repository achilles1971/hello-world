<?php
/**
 * Per-agent Gmail connection state, stored in WordPress usermeta — each
 * user connects at most one Gmail account, so this rides on WP's native
 * per-user storage rather than a new custom table. Storage only; talking
 * to Google is Cornerstone_Gmail_Client's job.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Connection {

	private const META_EMAIL          = 'cornerstone_gmail_email';
	private const META_REFRESH_ENC    = 'cornerstone_gmail_refresh_token_enc';
	private const META_LAST_SYNCED_AT = 'cornerstone_gmail_last_synced_at';
	private const META_LAST_STATUS    = 'cornerstone_gmail_last_sync_status';
	private const META_PROCESSED_IDS  = 'cornerstone_gmail_processed_ids';

	private const MAX_PROCESSED_IDS = 300;

	public static function is_connected( int $user_id ): bool {
		return '' !== (string) get_user_meta( $user_id, self::META_REFRESH_ENC, true );
	}

	public static function connected_email( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::META_EMAIL, true );
	}

	/**
	 * Stores a new connection. Returns false without storing anything if
	 * encryption isn't configured — see Cornerstone_Gmail_Crypto.
	 */
	public static function save( int $user_id, string $email, string $refresh_token ): bool {
		$encrypted = Cornerstone_Gmail_Crypto::encrypt( $refresh_token );
		if ( null === $encrypted ) {
			return false;
		}

		update_user_meta( $user_id, self::META_EMAIL, $email );
		update_user_meta( $user_id, self::META_REFRESH_ENC, $encrypted );
		update_user_meta( $user_id, self::META_LAST_STATUS, 'connected' );
		delete_user_meta( $user_id, self::META_LAST_SYNCED_AT );
		delete_user_meta( $user_id, self::META_PROCESSED_IDS );

		return true;
	}

	public static function disconnect( int $user_id ): void {
		delete_user_meta( $user_id, self::META_EMAIL );
		delete_user_meta( $user_id, self::META_REFRESH_ENC );
		delete_user_meta( $user_id, self::META_LAST_SYNCED_AT );
		delete_user_meta( $user_id, self::META_LAST_STATUS );
		delete_user_meta( $user_id, self::META_PROCESSED_IDS );
	}

	/**
	 * Decrypted refresh token, or null if not connected or undecryptable.
	 */
	public static function refresh_token( int $user_id ): ?string {
		$encrypted = (string) get_user_meta( $user_id, self::META_REFRESH_ENC, true );
		if ( '' === $encrypted ) {
			return null;
		}
		return Cornerstone_Gmail_Crypto::decrypt( $encrypted );
	}

	public static function last_synced_at( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::META_LAST_SYNCED_AT, true );
	}

	public static function set_last_synced_at( int $user_id, int $timestamp ): void {
		update_user_meta( $user_id, self::META_LAST_SYNCED_AT, $timestamp );
	}

	public static function last_sync_status( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::META_LAST_STATUS, true );
	}

	public static function set_last_sync_status( int $user_id, string $status ): void {
		update_user_meta( $user_id, self::META_LAST_STATUS, sanitize_text_field( $status ) );
	}

	/**
	 * IDs of the most recently processed Gmail messages for this agent,
	 * used to skip a message we already logged if it reappears in an
	 * overlapping sync window.
	 */
	public static function processed_ids( int $user_id ): array {
		$ids = get_user_meta( $user_id, self::META_PROCESSED_IDS, true );
		return is_array( $ids ) ? $ids : [];
	}

	public static function remember_processed_id( int $user_id, string $message_id ): void {
		$ids   = self::processed_ids( $user_id );
		$ids[] = $message_id;
		if ( count( $ids ) > self::MAX_PROCESSED_IDS ) {
			$ids = array_slice( $ids, -self::MAX_PROCESSED_IDS );
		}
		update_user_meta( $user_id, self::META_PROCESSED_IDS, $ids );
	}

	/**
	 * User IDs of every agent with a stored connection, for the cron
	 * sync loop.
	 */
	public static function connected_user_ids(): array {
		$users = get_users( [
			'meta_key'     => self::META_REFRESH_ENC,
			'meta_compare' => 'EXISTS',
			'fields'       => 'ID',
		] );
		return array_map( 'intval', $users );
	}
}

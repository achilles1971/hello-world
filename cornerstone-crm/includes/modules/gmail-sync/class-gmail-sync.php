<?php
/**
 * The actual sync: for one connected agent, ask Gmail what's new in
 * Sent, match each recipient against that agent's own contacts, and log
 * a matched send as an interaction. Never touches another agent's
 * contacts — every match is scoped to the mailbox owner's user_id.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Sync {

	/** How far back to look on an agent's very first sync. */
	private const FIRST_SYNC_LOOKBACK = DAY_IN_SECONDS;

	/** Re-check a small window before the last sync point, in case a
	 *  message arrived after list_sent_message_ids() ran but before this
	 *  agent's last successful sync was recorded. */
	private const OVERLAP_BUFFER = 10 * MINUTE_IN_SECONDS;

	/**
	 * Syncs one agent's Sent mail. Safe to call from cron or an
	 * on-demand "Sync now" click — same code path either way.
	 *
	 * @return array{checked: int, matched: int}|WP_Error
	 */
	public static function sync_for_user( int $user_id ): array|WP_Error {
		if ( ! Cornerstone_Gmail_Connection::is_connected( $user_id ) ) {
			return new WP_Error( 'cornerstone_gmail_not_connected', __( 'This agent has not connected a Gmail account.', 'cornerstone-crm' ) );
		}

		$refresh_token = Cornerstone_Gmail_Connection::refresh_token( $user_id );
		if ( null === $refresh_token ) {
			Cornerstone_Gmail_Connection::set_last_sync_status( $user_id, __( 'Could not decrypt the stored connection. Reconnect Gmail.', 'cornerstone-crm' ) );
			return new WP_Error( 'cornerstone_gmail_decrypt_failed', __( 'Could not decrypt the stored refresh token.', 'cornerstone-crm' ) );
		}

		$access = Cornerstone_Gmail_Client::refresh_access_token(
			Cornerstone_Gmail_Settings::client_id(),
			Cornerstone_Gmail_Settings::client_secret(),
			$refresh_token
		);
		if ( is_wp_error( $access ) ) {
			Cornerstone_Gmail_Connection::set_last_sync_status( $user_id, $access->get_error_message() );
			return $access;
		}

		$last_synced_at = Cornerstone_Gmail_Connection::last_synced_at( $user_id );
		$after_ts        = $last_synced_at > 0
			? $last_synced_at - self::OVERLAP_BUFFER
			: time() - self::FIRST_SYNC_LOOKBACK;

		$message_ids = Cornerstone_Gmail_Client::list_sent_message_ids( $access['access_token'], $after_ts );
		if ( is_wp_error( $message_ids ) ) {
			Cornerstone_Gmail_Connection::set_last_sync_status( $user_id, $message_ids->get_error_message() );
			return $message_ids;
		}

		$sync_started_at = time();
		$already_seen    = array_flip( Cornerstone_Gmail_Connection::processed_ids( $user_id ) );

		$checked = 0;
		$matched = 0;

		foreach ( $message_ids as $message_id ) {
			if ( isset( $already_seen[ $message_id ] ) ) {
				continue;
			}
			++$checked;

			$metadata = Cornerstone_Gmail_Client::get_message_metadata( $access['access_token'], $message_id );
			if ( ! is_wp_error( $metadata ) ) {
				$matched += self::process_message( $user_id, $metadata );
			}

			// Marked processed either way — a message we couldn't fetch
			// this run isn't worth retrying indefinitely.
			Cornerstone_Gmail_Connection::remember_processed_id( $user_id, $message_id );
		}

		Cornerstone_Gmail_Connection::set_last_synced_at( $user_id, $sync_started_at );
		Cornerstone_Gmail_Connection::set_last_sync_status( $user_id, 'ok' );

		return [
			'checked' => $checked,
			'matched' => $matched,
		];
	}

	/**
	 * Matches one message's recipients against this agent's contacts and
	 * logs an interaction per match. Returns how many contacts matched.
	 */
	private static function process_message( int $user_id, array $metadata ): int {
		$headers = [];
		foreach ( (array) ( $metadata['payload']['headers'] ?? [] ) as $header ) {
			if ( isset( $header['name'], $header['value'] ) ) {
				$headers[ strtolower( (string) $header['name'] ) ] = (string) $header['value'];
			}
		}

		$recipients = array_unique( array_merge(
			self::extract_emails( $headers['to'] ?? '' ),
			self::extract_emails( $headers['cc'] ?? '' )
		) );
		if ( empty( $recipients ) ) {
			return 0;
		}

		$subject     = sanitize_text_field( $headers['subject'] ?? '' );
		$occurred_at = self::internal_date_to_mysql( $metadata['internalDate'] ?? '' );
		$note        = '[Gmail sync] ' . ( '' !== $subject ? sprintf(
			/* translators: %s: email subject line */
			__( 'Sent: %s', 'cornerstone-crm' ),
			$subject
		) : __( 'Sent (no subject)', 'cornerstone-crm' ) );

		$matched = 0;
		foreach ( $recipients as $email ) {
			$contact = Cornerstone_Contacts::find_by_email( $email, $user_id );
			if ( null === $contact ) {
				continue;
			}

			$result = Cornerstone_Interactions::create(
				[
					'contact_id'  => $contact['id'],
					'type'        => 'email',
					'note'        => $note,
					'occurred_at' => $occurred_at,
				],
				$user_id,
				false
			);

			if ( ! is_wp_error( $result ) ) {
				++$matched;
			}
		}

		return $matched;
	}

	/**
	 * Pulls bare email addresses out of a raw To/Cc header value like
	 * '"Jane Doe" <jane@example.com>, other@example.com'.
	 */
	private static function extract_emails( string $header_value ): array {
		if ( '' === $header_value ) {
			return [];
		}
		preg_match_all( '/[^\s<>,"]+@[^\s<>,"]+\.[^\s<>,"]+/', $header_value, $matches );

		$emails = [];
		foreach ( $matches[0] as $candidate ) {
			$clean = Cornerstone_Validate::email( $candidate );
			if ( '' !== $clean ) {
				$emails[] = $clean;
			}
		}
		return array_unique( $emails );
	}

	/**
	 * Gmail's internalDate is milliseconds-since-epoch as a string. Falls
	 * back to "now" (site time) if it's missing or unparseable rather
	 * than failing the whole message.
	 */
	private static function internal_date_to_mysql( string $internal_date ): string {
		$ms = (int) $internal_date;
		if ( $ms <= 0 ) {
			return current_time( 'mysql' );
		}
		return gmdate( 'Y-m-d H:i:s', (int) ( $ms / 1000 ) );
	}
}

<?php
/**
 * Site-wide Google OAuth app settings (Client ID + Client Secret) — one
 * app registration shared by every agent, who each then authorize their
 * own mailbox against it. Broker-only to configure; the Client Secret is
 * encrypted at rest via Cornerstone_Gmail_Crypto, same as each agent's
 * refresh token.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Settings {

	private const OPTION_KEY = 'cornerstone_gmail_settings';

	/**
	 * @return array{client_id: string, client_secret_enc: string}
	 */
	private static function raw(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		return is_array( $stored ) ? $stored : [];
	}

	public static function client_id(): string {
		return (string) ( self::raw()['client_id'] ?? '' );
	}

	/**
	 * Decrypts and returns the Client Secret, or '' if unset/undecryptable
	 * (e.g. CORNERSTONE_CRM_ENCRYPTION_KEY changed or was never set).
	 */
	public static function client_secret(): string {
		$encrypted = (string) ( self::raw()['client_secret_enc'] ?? '' );
		if ( '' === $encrypted ) {
			return '';
		}
		return Cornerstone_Gmail_Crypto::decrypt( $encrypted ) ?? '';
	}

	public static function is_configured(): bool {
		return '' !== self::client_id() && '' !== self::client_secret();
	}

	/**
	 * Stores a new Client ID/Secret. Returns false (and stores nothing)
	 * if encryption isn't configured — callers must not silently persist
	 * a plaintext secret.
	 */
	public static function save( string $client_id, string $client_secret ): bool {
		if ( '' === $client_secret ) {
			// Keep the existing secret if the settings form was
			// resubmitted without retyping it (see the admin view: the
			// secret field is never pre-filled with its real value).
			$encrypted = self::raw()['client_secret_enc'] ?? '';
		} else {
			$encrypted = Cornerstone_Gmail_Crypto::encrypt( $client_secret );
			if ( null === $encrypted ) {
				return false;
			}
		}

		update_option(
			self::OPTION_KEY,
			[
				'client_id'         => $client_id,
				'client_secret_enc' => $encrypted,
			],
			false
		);

		return true;
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}
}

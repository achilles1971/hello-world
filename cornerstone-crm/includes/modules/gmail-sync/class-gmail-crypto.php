<?php
/**
 * Symmetric encryption for secrets this module has to store at rest: the
 * Google OAuth Client Secret and each agent's refresh token. A refresh
 * token is a standing credential that keeps working until revoked — if
 * the database ever leaked (a backup exposure, an unrelated vulnerable
 * plugin), plaintext refresh tokens would hand out ongoing read access to
 * every connected agent's Sent mail. Encrypting them means a DB leak
 * alone isn't enough; the attacker also needs CORNERSTONE_CRM_ENCRYPTION_KEY,
 * which lives only in wp-config.php.
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305), bundled with PHP since
 * 7.2 — no external dependency. Deliberately fails closed: if the key
 * constant isn't defined, or is malformed, every encrypt/decrypt call
 * returns null rather than silently falling back to plaintext.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Gmail_Crypto {

	/**
	 * Derives the raw 32-byte secretbox key from the wp-config.php
	 * constant, or returns null if it isn't set up correctly.
	 */
	private static function key(): ?string {
		if ( ! defined( 'CORNERSTONE_CRM_ENCRYPTION_KEY' ) || ! is_string( CORNERSTONE_CRM_ENCRYPTION_KEY ) || '' === CORNERSTONE_CRM_ENCRYPTION_KEY ) {
			return null;
		}

		$key = base64_decode( CORNERSTONE_CRM_ENCRYPTION_KEY, true );
		if ( false === $key || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen( $key ) ) {
			return null;
		}

		return $key;
	}

	/**
	 * Whether the site is set up to encrypt Gmail secrets at all. Callers
	 * (the settings/connection screens) check this before offering to
	 * store anything, so a misconfigured site fails at "you can't connect
	 * Gmail yet" rather than silently storing a secret in the clear.
	 */
	public static function is_configured(): bool {
		return null !== self::key();
	}

	/**
	 * Encrypts $plaintext, returning a self-contained base64 string
	 * (nonce + ciphertext), or null if encryption isn't configured.
	 */
	public static function encrypt( string $plaintext ): ?string {
		$key = self::key();
		if ( null === $key ) {
			return null;
		}

		$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Reverses encrypt(). Returns null on any failure — bad key, tampered
	 * ciphertext, or an empty/malformed value — rather than throwing, so
	 * callers can treat "can't decrypt" the same as "not connected."
	 */
	public static function decrypt( string $encoded ): ?string {
		$key = self::key();
		if ( null === $key || '' === $encoded ) {
			return null;
		}

		$raw = base64_decode( $encoded, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$nonce      = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$ciphertext = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		return false === $plaintext ? null : $plaintext;
	}
}

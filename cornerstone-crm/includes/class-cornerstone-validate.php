<?php
/**
 * Shared input validation/sanitization for every data-layer class.
 *
 * Centralizing the allowed-value whitelists keeps the dashboard filters
 * and the write paths guaranteed to agree, and gives data integrity: a
 * pipeline_status typo can never silently create a new, unfilterable
 * bucket.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Validate {

	public const ROLE_TAGS = [ 'buyer', 'seller', 'past_client', 'sphere', 'referral_source' ];

	public const PIPELINE_STATUSES = [ 'new', 'contacted', 'nurturing', 'active', 'under_contract', 'closed', 'lost' ];

	public const INTERACTION_TYPES = [ 'call', 'text', 'email', 'other' ];

	public const TRANSACTION_TYPES = [ 'buy', 'sell' ];

	public const TRANSACTION_STATUSES = [ 'active', 'pending', 'closed', 'cancelled' ];

	/**
	 * Returns $value if it is in $allowed, otherwise $fallback. Used for
	 * every enum-like column so a malformed request can never write a
	 * value the UI/filters don't know how to handle.
	 */
	public static function enum( string $value, array $allowed, string $fallback ): string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Sanitizes a phone number down to digits, spaces, and the handful of
	 * punctuation characters real phone numbers use.
	 */
	public static function phone( string $value ): string {
		$value = sanitize_text_field( $value );
		return substr( preg_replace( '/[^0-9 +().\-x]/', '', $value ) ?? '', 0, 30 );
	}

	/**
	 * Sanitizes and validates an email address. Returns '' if invalid so
	 * callers can decide whether an empty email is acceptable (it is —
	 * contacts are frequently entered with only a phone number).
	 */
	public static function email( string $value ): string {
		$value = sanitize_email( $value );
		return is_email( $value ) ? $value : '';
	}

	/**
	 * Validates a decimal price. Returns null for empty/invalid input
	 * (price is optional on a transaction until it's known).
	 */
	public static function price( mixed $value ): ?float {
		if ( '' === $value || null === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$price = (float) $value;
		return $price >= 0 ? round( $price, 2 ) : null;
	}

	/**
	 * Validates a Y-m-d date string, returns '' if invalid/empty.
	 */
	public static function date( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		$parsed = DateTime::createFromFormat( 'Y-m-d', $value );
		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
			return '';
		}
		return $value;
	}

	/**
	 * Validates the transaction key_dates payload: must decode to a JSON
	 * object (associative array) of milestone => date. Rejects anything
	 * else so the column never stores unparseable data. Returns a JSON
	 * string ready for storage, or '{}' if the input was empty/invalid.
	 */
	public static function key_dates_json( mixed $value ): string {
		if ( is_array( $value ) ) {
			$decoded = $value;
		} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( ! is_array( $decoded ) || json_last_error() !== JSON_ERROR_NONE ) {
				return '{}';
			}
		} else {
			return '{}';
		}

		$clean = [];
		foreach ( $decoded as $milestone => $date ) {
			if ( ! is_string( $milestone ) ) {
				continue;
			}
			$milestone = sanitize_key( $milestone );
			$date      = self::date( is_string( $date ) ? $date : '' );
			if ( '' === $milestone || '' === $date ) {
				continue;
			}
			$clean[ $milestone ] = $date;
		}

		return wp_json_encode( $clean ) ?: '{}';
	}
}

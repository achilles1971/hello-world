<?php
/**
 * Table-name helpers shared by every data-layer and REST class.
 *
 * Centralizing this avoids typos across files and gives us one place to
 * change the table prefix if it ever needs to.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_DB {

	/**
	 * Returns the fully-prefixed contacts table name.
	 */
	public static function contacts(): string {
		global $wpdb;
		return $wpdb->prefix . 'cornerstone_contacts';
	}

	/**
	 * Returns the fully-prefixed interactions table name.
	 */
	public static function interactions(): string {
		global $wpdb;
		return $wpdb->prefix . 'cornerstone_interactions';
	}

	/**
	 * Returns the fully-prefixed transactions table name.
	 */
	public static function transactions(): string {
		global $wpdb;
		return $wpdb->prefix . 'cornerstone_transactions';
	}

	/**
	 * Returns the fully-prefixed tasks table name.
	 */
	public static function tasks(): string {
		global $wpdb;
		return $wpdb->prefix . 'cornerstone_tasks';
	}
}

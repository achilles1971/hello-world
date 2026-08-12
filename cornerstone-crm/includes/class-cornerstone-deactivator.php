<?php
/**
 * Handles deactivation. Deliberately does nothing to the custom tables or
 * roles — deactivating a plugin must never be destructive. Permanent
 * cleanup (only if the site owner explicitly deletes the plugin through
 * wp-admin) lives in uninstall.php instead.
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

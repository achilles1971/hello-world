<?php
/**
 * Handles activation: creates the four custom tables via dbDelta() and
 * registers the plugin's roles/capabilities.
 *
 * No foreign key constraints are declared. dbDelta() does not reliably
 * parse or manage FOREIGN KEY clauses (this is documented WordPress core
 * behavior, not an oversight) — constraints added this way are silently
 * dropped or re-attempted on every dbDelta() call. Referential integrity
 * (contact_id must point to an existing, accessible contact) is instead
 * enforced in the data-layer classes before every insert/update. See
 * includes/class-cornerstone-contacts.php::exists_for_scope().
 */

defined( 'ABSPATH' ) || exit;

final class Cornerstone_Activator {

	private const DB_VERSION_OPTION = 'cornerstone_crm_db_version';

	public static function activate(): void {
		self::create_tables();
		Cornerstone_Roles::register();
		update_option( self::DB_VERSION_OPTION, CORNERSTONE_CRM_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Runs on every page load (cheap: one option read) and re-applies the
	 * schema only when the stored DB version is behind the plugin's. This
	 * is what lets future versions add tables/columns via dbDelta() without
	 * requiring the site owner to deactivate/reactivate, and without ever
	 * touching existing rows — dbDelta() only adds/alters, it never drops
	 * data.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_OPTION, '' );
		if ( $installed === CORNERSTONE_CRM_DB_VERSION ) {
			return;
		}

		self::create_tables();
		Cornerstone_Roles::register();
		update_option( self::DB_VERSION_OPTION, CORNERSTONE_CRM_DB_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$contacts = Cornerstone_DB::contacts();
		$sql_contacts = "CREATE TABLE {$contacts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			first_name VARCHAR(100) NOT NULL DEFAULT '',
			last_name VARCHAR(100) NOT NULL DEFAULT '',
			phone VARCHAR(30) NOT NULL DEFAULT '',
			email VARCHAR(191) NOT NULL DEFAULT '',
			role_tag VARCHAR(30) NOT NULL DEFAULT 'sphere',
			pipeline_status VARCHAR(30) NOT NULL DEFAULT 'new',
			notes LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY pipeline_status (pipeline_status),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		$interactions = Cornerstone_DB::interactions();
		$sql_interactions = "CREATE TABLE {$interactions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contact_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'other',
			note LONGTEXT NULL,
			occurred_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			created_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY user_id (user_id),
			KEY type (type),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		$transactions = Cornerstone_DB::transactions();
		$sql_transactions = "CREATE TABLE {$transactions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contact_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			property_address VARCHAR(255) NOT NULL DEFAULT '',
			transaction_type VARCHAR(10) NOT NULL DEFAULT 'buy',
			status VARCHAR(30) NOT NULL DEFAULT 'active',
			price DECIMAL(12,2) NULL DEFAULT NULL,
			key_dates LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		$tasks = Cornerstone_DB::tasks();
		$sql_tasks = "CREATE TABLE {$tasks} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contact_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			description LONGTEXT NULL,
			due_date DATE NULL DEFAULT NULL,
			completed TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-02 00:00:00',
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY user_id (user_id),
			KEY completed (completed),
			KEY deleted_at (deleted_at)
		) {$charset_collate};";

		// dbDelta() is picky about formatting (two spaces before PRIMARY KEY,
		// one column per line) but handles create-vs-alter detection for us,
		// which is exactly what maybe_upgrade() relies on for future schema
		// changes.
		dbDelta( $sql_contacts );
		dbDelta( $sql_interactions );
		dbDelta( $sql_transactions );
		dbDelta( $sql_tasks );
	}
}

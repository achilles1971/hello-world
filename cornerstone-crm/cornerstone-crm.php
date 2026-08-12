<?php
/**
 * Plugin Name:       Cornerstone 2.0 CRM
 * Plugin URI:        https://aaronatkinsonrealty.com
 * Description:       Minimalist, secure broker CRM. Custom database tables for contacts, interactions, transactions, and tasks, scoped by WordPress user with a broker/agent role system.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Aaron Atkinson Realty
 * Author URI:        https://aaronatkinsonrealty.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cornerstone-crm
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define( 'CORNERSTONE_CRM_VERSION', '2.0.0' );
// Bump this whenever the DB schema changes; class-activator.php compares it
// against the stored option and re-runs dbDelta() so upgrades never require
// a fresh activation and never touch existing rows.
define( 'CORNERSTONE_CRM_DB_VERSION', '1.0.0' );
define( 'CORNERSTONE_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'CORNERSTONE_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'CORNERSTONE_CRM_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// Dependencies
// ---------------------------------------------------------------------------

require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-db.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-roles.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-activator.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-deactivator.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-validate.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-contacts.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-interactions.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-transactions.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-tasks.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-rest-controller.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-rest-contacts.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-rest-interactions.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-rest-transactions.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-rest-tasks.php';
require_once CORNERSTONE_CRM_PATH . 'includes/class-cornerstone-admin.php';
require_once CORNERSTONE_CRM_PATH . 'includes/extensions.php';

// ---------------------------------------------------------------------------
// Activation / Deactivation
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, [ 'Cornerstone_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Cornerstone_Deactivator', 'deactivate' ] );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

/**
 * Boots the plugin. Runs on plugins_loaded so all WordPress user/role/
 * capability APIs are guaranteed available.
 */
function cornerstone_crm_init(): void {
	// Keep the schema in sync for sites where the plugin was activated
	// before a version bump introduced new tables/columns.
	Cornerstone_Activator::maybe_upgrade();

	Cornerstone_Admin::init();

	add_action( 'rest_api_init', [ 'Cornerstone_REST_Contacts', 'register_routes' ] );
	add_action( 'rest_api_init', [ 'Cornerstone_REST_Interactions', 'register_routes' ] );
	add_action( 'rest_api_init', [ 'Cornerstone_REST_Transactions', 'register_routes' ] );
	add_action( 'rest_api_init', [ 'Cornerstone_REST_Tasks', 'register_routes' ] );

	// See includes/extensions.php for the isolated "add functionality" hook.
	do_action( 'cornerstone_crm_loaded' );
}
add_action( 'plugins_loaded', 'cornerstone_crm_init' );

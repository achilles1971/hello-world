<?php
/**
 * Runs only when the plugin is deleted from wp-admin (Plugins → Delete),
 * never on deactivation.
 *
 * Deliberately conservative: broker CRM data (contacts, interactions,
 * transactions, tasks) is real business data, and there is no
 * confirmation step between clicking "Delete" on a plugin and this file
 * executing. We remove the plugin's own options and custom roles, but we
 * do NOT drop the custom tables — a broker who deletes the plugin by
 * mistake, or to reinstall a newer version, should not lose their CRM
 * data. If a full data wipe is ever wanted, do it deliberately via a
 * direct database action, not as a side effect of plugin deletion.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cornerstone_crm_db_version' );

remove_role( 'cornerstone_broker' );
remove_role( 'cornerstone_agent' );

$administrator = get_role( 'administrator' );
if ( $administrator ) {
	$administrator->remove_cap( 'cornerstone_crm_access' );
	$administrator->remove_cap( 'cornerstone_crm_manage_all' );
}

// Custom tables (wp_cornerstone_contacts, wp_cornerstone_interactions,
// wp_cornerstone_transactions, wp_cornerstone_tasks) are intentionally
// left in place. See the note above.

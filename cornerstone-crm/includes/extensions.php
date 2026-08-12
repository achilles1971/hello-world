<?php
/**
 * =============================================================================
 * FUTURE EXPANSION EXTENSION POINT — read this before adding any new feature.
 * =============================================================================
 *
 * This file is intentionally the ONLY place in the plugin that defines the
 * "add functionality later" extension point. It registers no behavior of
 * its own — it exists purely so future modules (automated reminders, email/
 * SMS sending, reporting dashboards, AI features, or anything else
 * explicitly deferred from this version) have one clean, documented place
 * to plug into, without ever needing to modify core CRM files.
 *
 * HOW TO USE THIS LATER:
 *
 *   1. Build the new feature as its own file (or its own mini-plugin), e.g.
 *      includes/modules/class-cornerstone-reminders.php.
 *
 *   2. Hook it in via the 'cornerstone_crm_register_modules' action fired
 *      below. Example:
 *
 *          add_action( 'cornerstone_crm_register_modules', function () {
 *              require_once CORNERSTONE_CRM_PATH . 'includes/modules/class-cornerstone-reminders.php';
 *              Cornerstone_Reminders::init();
 *          } );
 *
 *   3. That's it. The module now boots alongside the core plugin, with
 *      access to the same data-layer classes (Cornerstone_Contacts,
 *      Cornerstone_Interactions, Cornerstone_Transactions, Cornerstone_Tasks),
 *      the same role/capability system (Cornerstone_Roles), and the same
 *      REST namespace convention (Cornerstone_REST_Controller) — without
 *      touching cornerstone-crm.php or any existing class.
 *
 * No feature is implemented here. This is a clean extension point only.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires once, after the core plugin has finished booting, so any code
 * hooked to it can safely assume every core class and the REST/admin
 * registration is already in place.
 *
 * Intentionally fired from cornerstone_crm_init() in cornerstone-crm.php
 * via `do_action( 'cornerstone_crm_loaded' )`, one level up from this
 * function — this file only defines the listener that re-broadcasts it
 * under the more explicit 'cornerstone_crm_register_modules' name, so the
 * extension contract documented above has a single, stable action name
 * even if the internal boot sequence changes later.
 */
function cornerstone_crm_register_modules_relay(): void {
	/**
	 * Fires when future "add functionality" modules should register
	 * themselves. No core behavior is attached to this hook — it is a
	 * placeholder only.
	 */
	do_action( 'cornerstone_crm_register_modules' );
}
add_action( 'cornerstone_crm_loaded', 'cornerstone_crm_register_modules_relay' );

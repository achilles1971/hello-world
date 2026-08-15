<?php
/**
 * Branded Portal module bootstrap. Registered via the
 * cornerstone_crm_register_modules extension hook (see
 * includes/extensions.php and cornerstone-crm.php) — same pattern as
 * the Gmail Sync module.
 *
 * Serves the CRM at /crm/... as its own branded page, completely
 * outside wp-admin and the site theme: no WordPress admin chrome, no
 * theme CSS, no admin bar. It's a second presentation of the exact same
 * data — every read and write here goes through the same
 * Cornerstone_Contacts/Interactions/Transactions/Tasks classes and the
 * same Cornerstone_Roles scoping as the wp-admin screens. The wp-admin
 * UI is untouched and still works; this is additive.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-portal-router.php';
require_once __DIR__ . '/class-portal-views.php';
require_once __DIR__ . '/class-portal-controller.php';
require_once __DIR__ . '/class-portal-forms.php';

final class Cornerstone_Portal_Module {

	public static function init(): void {
		Cornerstone_Portal_Router::init();
		Cornerstone_Portal_Forms::init();
		add_action( 'cornerstone_crm_admin_nav_after', [ 'Cornerstone_Portal_Views', 'print_admin_nav_link' ] );
	}
}

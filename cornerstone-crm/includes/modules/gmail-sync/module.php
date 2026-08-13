<?php
/**
 * Gmail Sync module bootstrap. Registered via the
 * cornerstone_crm_register_modules extension hook (see
 * includes/extensions.php and cornerstone-crm.php) — this file is only
 * ever loaded from that hook, never required directly by core files, to
 * prove out the extension point the core plugin promises.
 *
 * Captures a sent-email interaction automatically: each agent connects
 * their own Gmail account (OAuth2, metadata-only scope), and a periodic
 * sync matches new Sent-mail recipients against that agent's own
 * contacts, logging a matched send as an Interaction row. See
 * class-gmail-sync.php for the matching logic and class-gmail-crypto.php
 * for how credentials are protected at rest.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-gmail-crypto.php';
require_once __DIR__ . '/class-gmail-settings.php';
require_once __DIR__ . '/class-gmail-connection.php';
require_once __DIR__ . '/class-gmail-client.php';
require_once __DIR__ . '/class-gmail-oauth.php';
require_once __DIR__ . '/class-gmail-sync.php';
require_once __DIR__ . '/class-gmail-cron.php';
require_once __DIR__ . '/class-gmail-admin.php';

final class Cornerstone_Gmail_Module {

	public static function init(): void {
		Cornerstone_Gmail_OAuth::init();
		Cornerstone_Gmail_Cron::init();
		Cornerstone_Gmail_Admin::init();
	}
}

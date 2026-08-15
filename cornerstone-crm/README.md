# Cornerstone 2.0 CRM

Minimalist, secure broker CRM for a WordPress site. Custom database
tables for contacts, interactions, transactions, and follow-up tasks,
scoped by WordPress user with a broker/agent role system built on native
WordPress roles and capabilities.

See `SUMMARY.md` for what this version does and doesn't do, and
`tests/manual-test-plan.md` for the pre-activation test checklist.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+ / MariaDB equivalent

## Installation (staging first)

1. Copy the `cornerstone-crm/` directory into `wp-content/plugins/`.
2. Activate **Cornerstone 2.0 CRM** from Plugins → Installed Plugins.
   Activation creates the four custom tables (via `dbDelta()`) and the
   `cornerstone_broker` / `cornerstone_agent` roles, and grants the CRM
   capabilities to the Administrator role.
3. Go to the new **Cornerstone CRM** menu in the left admin sidebar.
4. Work through `tests/manual-test-plan.md` before considering this
   ready for production.

## Uninstalling

Deactivating the plugin does nothing destructive. Deleting it through
Plugins → Delete removes the plugin's own option and the two custom
roles/capabilities, but deliberately leaves the four data tables in
place — see the comment at the top of `uninstall.php`.

## Adding features later

Don't add new features by editing the core classes. Hook into
`cornerstone_crm_register_modules` — see the docblock at the top of
`includes/extensions.php` for the pattern, and
`includes/modules/gmail-sync/module.php` for a real example.

## Optional modules

- **Gmail Sync** — captures a sent email as an Interaction automatically.
  Not active until a broker completes the setup in
  `includes/modules/gmail-sync/SETUP.md` (a Google Cloud OAuth app and a
  wp-config.php encryption key are both required before it does anything).
- **Portal** — a standalone branded page at `/crm/`, styled to the
  brokerage's own colors and type instead of default WordPress admin
  chrome. Active as soon as the plugin loads; no setup required. See
  "The branded portal" below.

## The branded portal

`https://yoursite.com/crm/` is a second, standalone presentation of the
same CRM — same data, same accounts, same permissions — with no
WordPress admin bar, no wp-admin menu, no theme header/footer. It
renders its own `<!DOCTYPE html>` and loads only its own stylesheet, so
nothing in the theme or another plugin can visually collide with it.

- Visiting `/crm/` while logged out redirects to the normal WordPress
  login screen and bounces back afterward — there's no separate login
  system to secure.
- Every read is scoped exactly like the wp-admin screens
  (`cornerstone_crm_manage_all` = see everyone, otherwise only your own),
  and every write goes through the same data-layer classes, so the two
  presentations can never disagree about who can see or edit what.
- The wp-admin **Cornerstone CRM** menu still works as before — the
  portal is additive, not a replacement. Each links to the other (an
  "Open the branded portal ↗" link in wp-admin; a "wp-admin ↗" link in
  the portal header, shown to brokers only, since that's currently where
  Gmail Sync's settings live).
- Uses a rewrite rule (`/crm/...`), not just a query string, so it reads
  as a real URL. If it 404s right after first deploying this version,
  visit **Settings → Permalinks** and click **Save Changes** once to
  force a rewrite flush — this should self-heal within one page load on
  its own, but that's the manual fallback.

## File structure

```
cornerstone-crm/
├── cornerstone-crm.php              Main plugin file, bootstrap
├── uninstall.php                    Conservative cleanup on delete
├── includes/
│   ├── class-cornerstone-db.php             Table name helpers
│   ├── class-cornerstone-roles.php          Roles/capabilities
│   ├── class-cornerstone-activator.php      dbDelta schema, versioned upgrades
│   ├── class-cornerstone-deactivator.php    No-op-safe deactivation
│   ├── class-cornerstone-validate.php       Shared sanitize/validate helpers
│   ├── class-cornerstone-contacts.php       Contacts data layer
│   ├── class-cornerstone-interactions.php   Interactions data layer
│   ├── class-cornerstone-transactions.php   Transactions data layer
│   ├── class-cornerstone-tasks.php          Tasks data layer
│   ├── class-cornerstone-rest-controller.php   Shared REST auth/nonce/cap checks
│   ├── class-cornerstone-rest-contacts.php
│   ├── class-cornerstone-rest-interactions.php
│   ├── class-cornerstone-rest-transactions.php
│   ├── class-cornerstone-rest-tasks.php
│   ├── class-cornerstone-admin.php          Admin menu + form handlers
│   ├── extensions.php                       Future-module extension point
│   └── modules/
│       └── gmail-sync/              Optional: auto-capture sent email as an Interaction
│           ├── module.php                   Bootstrap, registered via the extension hook
│           ├── class-gmail-crypto.php        Encrypts secrets at rest (libsodium)
│           ├── class-gmail-settings.php      Site-wide OAuth Client ID/Secret
│           ├── class-gmail-connection.php    Per-agent connection storage
│           ├── class-gmail-client.php        Google OAuth + Gmail API HTTP calls
│           ├── class-gmail-oauth.php         Authorize/callback flow
│           ├── class-gmail-sync.php          Matches sent mail to contacts, logs Interactions
│           ├── class-gmail-cron.php          20-minute scheduled sync
│           ├── class-gmail-admin.php         Settings + connection status screen
│           ├── views/gmail-sync.php
│           └── SETUP.md              Required external setup (Google Cloud, encryption key)
│       └── portal/                   Optional: standalone branded page at /crm/
│           ├── module.php                    Bootstrap, registered via the extension hook
│           ├── class-portal-router.php        Rewrite rules, auth gate, dispatch, url() helper
│           ├── class-portal-controller.php    Resolves section/view/id → data → view template
│           ├── class-portal-forms.php         admin-post handlers (save/delete), reuse core data layer
│           ├── class-portal-views.php         Document shell (header/nav/footer), notices, pagination
│           ├── views/                         contacts.php, interactions.php, transactions.php, tasks.php
│           └── assets/portal.css              Brand token system + component styles
├── admin/
│   ├── css/admin.css
│   └── views/                       Server-rendered list/form templates
└── tests/
    └── manual-test-plan.md
```

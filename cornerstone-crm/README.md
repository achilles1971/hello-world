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
`includes/extensions.php` for the pattern.

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
│   └── extensions.php                       Future-module extension point
├── admin/
│   ├── css/admin.css
│   └── views/                       Server-rendered list/form templates
└── tests/
    └── manual-test-plan.md
```

# Cornerstone 2.0 CRM — Build Summary for Aaron

**Status: built and self-reviewed, NOT activated anywhere. Waiting on your
review and explicit go-ahead before this touches the live site.**

## What was built

A self-contained WordPress plugin (`cornerstone-crm/`) — not a page
builder add-on — implementing a minimal broker CRM:

- **Four custom database tables**, created via `dbDelta()` on activation:
  `wp_cornerstone_contacts`, `wp_cornerstone_interactions`,
  `wp_cornerstone_transactions`, `wp_cornerstone_tasks`. No CRM data
  touches the default `wp_posts` table.
- **Multi-tenant from day one**: every table has a `user_id` column.
  Two custom WordPress roles — `cornerstone_broker` (sees everyone's
  records) and `cornerstone_agent` (sees only their own) — built on
  WordPress's native roles/capabilities API, not a separate auth system.
  Your existing Administrator account is granted broker-level access
  automatically on activation, so you don't need to change your own role
  to use it.
- **Full CRUD + soft delete** on all four tables, both through a classic
  server-rendered wp-admin UI (Cornerstone CRM menu, four tabs: Contacts,
  Interactions, Transactions, Tasks) and through a REST API
  (`/wp-json/cornerstone-crm/v1/...`) for future/API use.
- **Dashboard list views** filterable by pipeline status (contacts),
  interaction type, transaction status, and task completion.
- **Manual follow-up tasks tied to a contact** — a "+ Task" link on every
  contact row jumps straight to a pre-filled new-task form.
- **One isolated, clearly commented extension point**
  (`includes/extensions.php`) for registering future modules — reminders,
  email/SMS, reporting, AI — without touching any existing file. Nothing
  is implemented behind it; it's a clean hook only, as requested.

## Security posture

- Every REST route requires a logged-in user, a valid `wp_rest` nonce,
  and a custom `cornerstone_crm_access` capability — nothing in this
  plugin's REST namespace is reachable by an unauthenticated request.
- Every admin-post form handler re-checks the same capability plus a
  form-specific nonce (`check_admin_referer()`), independent of the menu
  registration's own capability gate.
- All reads/writes are scoped by `user_id` unless the acting user holds
  `cornerstone_crm_manage_all` (broker/administrator) — enforced in the
  data layer itself, not just the UI, so the REST API and the admin
  screens can't be tricked into different scoping behavior.
- All direct database queries use `$wpdb->prepare()`; no raw
  interpolation of request data into SQL anywhere.
- All inputs are sanitized/validated on save (enum whitelists for
  role_tag, pipeline_status, interaction type, transaction type/status;
  phone/email/date/price/JSON validators). All output in admin views is
  escaped with `esc_html()`/`esc_attr()`/`esc_url()`/`esc_textarea()`.
- Deletes are soft (a `deleted_at` timestamp), never a hard `DELETE`, per
  spec.

## What was tested

- **Static:** `php -l` syntax-checked every file in the plugin — all
  pass. Manual code review against a security checklist (custom tables
  only / nonce+capability checks / sanitize+escape / correct scoping) for
  every file.
- **Not yet run:** this environment has no live WordPress/MySQL instance,
  so no runtime testing (activation, actual dbDelta execution, live
  role-scoping behavior, live REST requests) has been performed. A
  detailed manual test plan covering CRUD, cross-role scoping, REST
  security (unauthenticated/no-nonce rejection), and the schema-upgrade
  path is in `tests/manual-test-plan.md` — please run it on a staging
  copy of the site before activating on production.

## Assumptions made

- You (the existing Administrator) are treated as the de facto "broker"
  for now; you don't need to be manually switched to the `cornerstone_broker`
  role for full access, though you can create/assign that role to
  yourself or others later if you'd rather separate "WordPress admin" from
  "CRM broker."
- `pipeline_status`, interaction `type`, and transaction `status` are
  fixed, small vocabularies (defined in `Cornerstone_Validate`) rather
  than free text, so the dashboard filters stay meaningful. If you want
  different or additional values, that's a one-line change per list.
- `key_dates` on transactions is a simple label→date map (e.g.
  "inspection" → a date), editable as repeatable rows in the form; no
  fixed milestone list is enforced.
- No foreign key constraints exist at the database level — WordPress's
  `dbDelta()` does not reliably manage `FOREIGN KEY` clauses, which is
  documented, expected behavior, not an oversight. Referential integrity
  (a task/interaction/transaction's `contact_id` must point to a real,
  accessible contact) is enforced in application code instead, before
  every insert.

## Post-launch additions

Built after the initial checkpoint above, on your live site once it was
already active — each is a small, isolated change, not a re-review of the
whole plugin:

- **Broker capability self-heal.** You reported seeing only your own
  contacts as admin, not every agent's — the `cornerstone_crm_manage_all`
  grant hadn't stuck (most likely a stale object-cache copy of the roles
  option at activation time). The plugin now checks for this on every
  wp-admin page load and re-grants it if missing, no deactivate/reactivate
  needed.
- **Agent column + filter on Contacts.** The broker view now shows which
  agent owns each contact, and can filter the list down to one agent.
  Both are broker-only — an agent's own list is already just their own.
- **Gmail Sync module** (`includes/modules/gmail-sync/`) — the first real
  use of the extension hook. Each agent connects their own Gmail account;
  a ~20-minute background sync matches new Sent-mail recipients against
  that agent's own contacts and logs a matched send as an Interaction.
  Deliberately scoped down from what "email integration" could mean:
  - Metadata only (recipient, subject, date) — never message content —
    via Google's narrower `gmail.metadata` OAuth scope.
  - Periodic polling, not real-time push — no public webhook endpoint,
    no Google Cloud Pub/Sub to secure and maintain.
  - Each agent's refresh token, and the shared Google Client Secret, are
    encrypted at rest (libsodium) with a key that lives only in
    `wp-config.php`, never the database. Every storage path fails closed
    — refuses to save or connect — if that key isn't configured.
  - Requires setup outside the plugin (a Google Cloud OAuth app, the
    encryption key, a real server cron) before it does anything — see
    `includes/modules/gmail-sync/SETUP.md`. Nothing here is wired up or
    reachable until a broker completes that setup.
  - Not yet tested against a live Gmail account/Google Cloud project in
    this environment, for the same reason as the rest of this plugin: no
    live WordPress instance or Google credentials available here. Test
    against a real connected account before relying on it.

## Known limitations / risks

- No automated PHPUnit suite ships yet — see the note in
  `tests/manual-test-plan.md`. The manual plan is a reasonable
  substitute for this pass, and a real test DB would let it become
  proper `WP_UnitTestCase` tests later.
- No UI/browser testing has been done — the admin screens are plain,
  server-rendered WordPress admin markup (`wp-list-table`, `form-table`)
  and *should* behave like any other wp-admin screen, but haven't been
  visually verified.
- As specified, this version deliberately does **not** include automated
  reminders, email/SMS sending, reporting dashboards, or AI features. The
  extension point exists for adding these later without touching core
  files, but nothing beyond the four CRUD tables + task creation is here.
- This has not been activated or deployed anywhere. Please review, run
  the manual test plan on staging, and confirm before it goes anywhere
  near production.

# Cornerstone CRM — Manual Test Plan

This plugin was developed outside of a running WordPress instance (no WP
core, database, or web server available in the build environment). What
*was* verified automatically:

- `php -l` (syntax lint) passes on every `.php` file in the plugin.
- Manual code review against the security checklist in `SUMMARY.md`.

What still needs to be verified on a real WordPress install (staging
site, never production first) is below. Run this on a staging copy of
aaronatkinsonrealty.com or a local WP environment before Aaron approves
activation on the live site.

## Setup

1. Copy the `cornerstone-crm/` folder into `wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Confirm four new tables exist with the site's table prefix, e.g.:
   ```
   wp db query "SHOW TABLES LIKE '%cornerstone%';"
   ```
   Expect: `wp_cornerstone_contacts`, `wp_cornerstone_interactions`,
   `wp_cornerstone_transactions`, `wp_cornerstone_tasks`.
4. Confirm the roles exist:
   ```
   wp role list | grep cornerstone
   ```
   Expect: `cornerstone_broker`, `cornerstone_agent`.
5. Confirm the site Administrator (Aaron's existing account) can already
   see the "Cornerstone CRM" menu in wp-admin without any role change —
   this is the broker-equivalent default for the current single-user
   setup.

## Create a second test user (for scoping tests)

```
wp user create agenttest agenttest@example.com --role=cornerstone_agent --user_pass=temporary-strong-pass
```

Log in as this user in a second/private browser window for the scoping
tests below.

## 1. Contacts CRUD

As Administrator (broker):

- [ ] Cornerstone CRM → Contacts → Add New Contact. Fill in first/last
      name, phone, email, role, pipeline status, notes. Save. Confirm it
      appears in the list.
- [ ] Edit the contact, change pipeline_status, save. Confirm the change
      persists.
- [ ] Filter the list by pipeline_status. Confirm only matching contacts
      show.
- [ ] Delete the contact. Confirm it disappears from the list.
- [ ] Confirm via `wp db query "SELECT id, deleted_at FROM wp_cornerstone_contacts;"`
      that the row still exists with `deleted_at` set (soft delete, not a
      hard delete).
- [ ] Try saving a contact with both first and last name blank — expect
      a rejection (data-layer validation), not a silent empty row.

## 2. Interactions CRUD

- [ ] Create a contact, then log an interaction (call/text/email/other)
      against it from Cornerstone CRM → Interactions → Add New.
- [ ] Filter the interactions list by type.
- [ ] Edit an interaction's note and date. Confirm it saves.
- [ ] Delete an interaction. Confirm it disappears from the list but the
      row remains in the DB with `deleted_at` set.
- [ ] Attempt to create an interaction against a contact_id that does not
      exist (e.g. via the REST API — see section 5) — expect a 400 error,
      not a saved row with a dangling contact_id.

## 3. Transactions CRUD

- [ ] Create a transaction against a contact: property address,
      buy/sell, status, price, and at least one key date (e.g.
      "inspection" → a date).
- [ ] Edit it, change status and price. Confirm it saves.
- [ ] Filter the transactions list by status.
- [ ] Delete it. Confirm soft delete (same as above).
- [ ] Inspect the `key_dates` column directly — confirm it stores valid
      JSON, e.g. `{"inspection":"2026-09-01"}`.

## 4. Tasks CRUD (manual follow-up tasks tied to a contact)

- [ ] From a contact's row in the Contacts list, click "+ Task". Confirm
      the contact is pre-selected on the new task form.
- [ ] Save a task with a description and due date.
- [ ] Edit the task, check "Mark as completed", save. Confirm it shows
      "Yes" under Completed in the list.
- [ ] Filter the tasks list by Open / Completed / All.
- [ ] Delete a task. Confirm soft delete.

## 5. Multi-tenant scoping (critical — test this thoroughly)

As the `agenttest` user created above:

- [ ] Log in, open Cornerstone CRM → Contacts. Confirm you see ZERO of
      the Administrator's contacts — only records you create yourself.
- [ ] Create a contact, interaction, transaction, and task as this agent.
      Confirm they're visible to this agent.
- [ ] Log back in as the Administrator (broker). Confirm you now see
      BOTH your own records AND the agent's records in every list.
- [ ] As the agent, try to load an edit URL for one of the
      Administrator's records directly, e.g.:
      `wp-admin/admin.php?page=cornerstone-crm&view=edit&id=<broker's contact id>`
      Expect: "Record not found" notice — not the broker's data.
- [ ] As the agent, try to POST a delete for one of the broker's records
      directly to `admin-post.php?action=cornerstone_delete_contact` with
      that contact's ID and a valid nonce for your own session. Expect:
      no rows affected (data-layer scoping blocks the WHERE clause from
      matching), and the broker's record is untouched afterward.

## 6. REST API security

Replace `<site>` with the test site URL. `<nonce>` is a `wp_rest` nonce —
grab it from `wp_localize_script`/`wp_create_nonce('wp_rest')` output or
`wp eval "echo wp_create_nonce('wp_rest');"` while impersonating a
logged-in session, or from the browser's REST requests in dev tools while
logged into wp-admin.

- [ ] **Unauthenticated request is rejected:**
      ```
      curl -i https://<site>/wp-json/cornerstone-crm/v1/contacts
      ```
      Expect: `401` (not logged in) — no contact data in the response
      body.
- [ ] **Logged-in request without a nonce is rejected:**
      Using a browser session cookie but no `X-WP-Nonce` header:
      ```
      curl -i --cookie "<wp cookies>" https://<site>/wp-json/cornerstone-crm/v1/contacts
      ```
      Expect: `403`.
- [ ] **Logged-in request with a valid nonce succeeds and is scoped:**
      ```
      curl -i --cookie "<wp cookies>" -H "X-WP-Nonce: <nonce>" \
        https://<site>/wp-json/cornerstone-crm/v1/contacts
      ```
      As the agent user, expect only that agent's contacts back.
- [ ] **Create via REST enforces required fields:**
      POST `/contacts` with an empty JSON body `{}` — expect `400`, not a
      blank contact row.
- [ ] **Create an interaction/transaction/task against a contact_id
      outside your scope via REST** — expect `400`
      (`cornerstone_invalid_contact`), confirming the application-layer
      referential-integrity check (in place of a DB foreign key
      constraint) is doing its job.
- [ ] Confirm none of the four tables, and none of the
      `/wp-json/cornerstone-crm/v1/*` routes, are reachable by a fully
      logged-out request other than the `401` rejection above (i.e., no
      route was accidentally registered without a `permission_callback`).

## 7. Upgrade path (schema evolution)

- [ ] With the plugin active and data present, bump
      `CORNERSTONE_CRM_DB_VERSION` in `cornerstone-crm.php` by a patch
      version and add a harmless new nullable column to one `CREATE TABLE`
      statement in `class-cornerstone-activator.php` (e.g. a `source`
      VARCHAR column on contacts). Reload any wp-admin page.
- [ ] Confirm the new column appears (`DESCRIBE wp_cornerstone_contacts;`)
      and all existing rows are untouched — this proves the
      version-gated `maybe_upgrade()` path works without requiring
      deactivate/reactivate and without data loss.
- [ ] Revert the test change afterward.

## Known gaps in this pass

- No automated PHPUnit test suite ships with this version — WordPress's
  PHPUnit test scaffolding requires a configured MySQL test database,
  which wasn't available in the build environment. The manual plan above
  covers the same ground; converting it to `WP_UnitTestCase` tests is a
  reasonable follow-up once the plugin is on a real WP install.
- Browser/UI testing (does the admin screen render correctly in wp-admin,
  cross-browser, on mobile) was not performed — this was built and
  reviewed as code only.

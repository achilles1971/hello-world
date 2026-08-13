# Gmail Sync — Setup Guide

Automatically logs a sent email as an Interaction on the matching contact.
Each agent connects their own Gmail account; a background sync checks for
new Sent mail every ~20 minutes and matches recipients against that
agent's own contacts by email address. Only metadata is ever read —
recipient, subject line, and date — never the message body.

This needs a few things set up outside the plugin before anyone can
connect an account. All of it is a one-time setup for the broker; agents
only do the last step for themselves.

## 1. Generate the encryption key (do this first)

The plugin encrypts the Google Client Secret and every agent's refresh
token before storing them. The encryption key itself lives only in
`wp-config.php`, never in the database — so a database leak alone can't
expose these credentials.

Generate a 32-byte key and base64-encode it:

```bash
openssl rand -base64 32
```

Add the result to `wp-config.php`, above the line that says
`/* That's all, stop editing! */`:

```php
define( 'CORNERSTONE_CRM_ENCRYPTION_KEY', 'paste-the-generated-value-here' );
```

Until this is set, the Gmail Sync screen will show a warning and refuse
to save settings or connect any account — it fails closed rather than
falling back to storing anything unencrypted.

## 2. Create the Google Cloud OAuth app

You'll need a Google account with access to your Workspace's Google Cloud
Console (console.cloud.google.com). This is a one-time setup for the
whole brokerage — every agent authorizes against this same app.

1. Create a new Google Cloud project (or reuse an existing one for the
   brokerage).
2. **APIs & Services → Library** — search for and enable the **Gmail
   API**.
3. **APIs & Services → OAuth consent screen**:
   - User Type: **Internal** (this is only possible because your agents
     are on the aaronatkinsonrealty.com Workspace domain — it skips
     Google's external-app verification review entirely).
   - Fill in the app name, support email, etc.
   - Add the scope `https://www.googleapis.com/auth/gmail.metadata`.
4. **APIs & Services → Credentials → Create Credentials → OAuth client
   ID**:
   - Application type: **Web application**.
   - Authorized redirect URIs: add the exact URL shown at the top of
     **Cornerstone CRM → Gmail Sync** in wp-admin (it's
     `https://aaronatkinsonrealty.com/wp-admin/admin-post.php?action=cornerstone_gmail_oauth_callback`
     unless your admin URL differs).
5. Copy the generated **Client ID** and **Client Secret**.

## 3. Enter the credentials in the CRM

As the broker, go to **Cornerstone CRM → Gmail Sync** and paste the
Client ID and Client Secret into the settings form at the top, then
save. The secret is encrypted immediately and never displayed again —
leave that field blank on future saves to keep it unchanged.

## 4. Each agent connects their own Gmail

Each agent (including you, if you want your own sent mail captured too)
goes to **Cornerstone CRM → Gmail Sync** and clicks **Connect Gmail**,
then approves access on Google's consent screen. The connection row
shows the connected address, last sync time, and status.

A **Sync Now** button is available for testing without waiting for the
next scheduled run.

## 5. Set up a real cron trigger

WordPress's built-in cron (WP-Cron) only fires when someone visits the
site, which is unreliable on a low-traffic site — the sync could sit
for hours without a visitor. Point a real server cron at `wp-cron.php`
instead:

In SiteGround **Site Tools → Devs → Cron Jobs**, add a job that runs
every 15 minutes:

```
*/15 * * * * curl -s https://aaronatkinsonrealty.com/wp-cron.php >/dev/null 2>&1
```

(Optional, once that's confirmed working) add
`define( 'DISABLE_WP_CRON', true );` to `wp-config.php` so WordPress
stops also trying to trigger cron on page loads — the real server cron
is more reliable and this avoids doing the work twice.

## What gets logged

A matched sent email becomes an Interaction: type `email`, note
`[Gmail sync] Sent: <subject line>`, dated to when the email was
actually sent. It's tagged so you can tell it apart from a manually
logged call/text/email in the Interactions list.

- Matching is by **exact email address**, scoped to that agent's own
  contacts only — an agent's sync can never create an interaction on
  another agent's contact.
- An email to an address that isn't in that agent's contacts is simply
  skipped — nothing is logged for it.
- A brand-new connection looks back 24 hours on its first sync, then
  only checks what's new since the last run.

## Known limitations

- Expect up to a ~20–30 minute delay between sending an email and seeing
  it logged, by design (see the earlier discussion on periodic vs.
  real-time capture).
- If an agent revokes access from their Google Account settings instead
  of disconnecting in the CRM, the next sync will fail with an
  authentication error shown in the Status column — they'll need to
  reconnect from the CRM side.
- Uninstalling the plugin does not currently clean up the stored Google
  app settings or per-agent connections/usermeta — only the four core
  CRM tables and roles follow the documented conservative uninstall
  behavior. This is a known gap, not a data-loss risk (the encrypted
  values are inert without the wp-config.php key).

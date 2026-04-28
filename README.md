# Nawasara Cloudflare

Cloudflare management dashboard for the Nawasara superapp framework — DNS records, zones, firewall rules, page rules, SSL settings, cache, and analytics, all read from a local DB snapshot for speed and mutated through queue jobs for auditability.

## Features

- **Zone management** — list domains with SSL mode, security level, plan, name servers, and per-zone settings
- **DNS records** — full CRUD across A, AAAA, CNAME, MX, TXT, NS, and SRV types, with comment + tags preserved on update
- **Firewall rules** — CRUD WAF custom rules per zone
- **Page rules** — CRUD URL-pattern rules
- **Analytics** — request volume, bandwidth, threats blocked, and unique visitors with per-OPD rollup
- **Cache purge** — purge entire cache or specific URLs
- **Under Attack mode** — toggle the security level when a zone is under DDoS pressure
- **Sync info bar** on every page showing the last successful sync time, pending mutation count, and a link to the audit log

The package follows the DB-cache + queue pattern from `nawasara/sync`: reads come from local snapshot tables (paginated, fast); writes dispatch queue jobs that update Cloudflare and the snapshot via content-hash conflict detection.

## Installation

```bash
composer require nawasara/cloudflare
php artisan migrate
php artisan db:seed --class="Nawasara\Cloudflare\Database\Seeders\PermissionSeeder" --force
```

The package is auto-discovered by Laravel — no manual provider registration required.

## Cloudflare API Token Setup

### 1. Sign in to the Cloudflare dashboard

Go to [dash.cloudflare.com](https://dash.cloudflare.com) and sign in to the account that manages your domains.

### 2. Open the API Tokens page

Click your **profile icon** (top right) → **My Profile** → **API Tokens** tab.

### 3. Create a custom token

Click **Create Token** → choose **Create Custom Token** at the bottom of the templates list.

### 4. Configure permissions

Name the token (e.g. `Nawasara Dashboard`) and add the following permissions depending on which features you plan to use:

| Permission | Access | Used for |
|---|---|---|
| Zone → Zone | Read | List zones, zone detail |
| Zone → Zone Settings | Edit | SSL mode, security level, Under Attack mode |
| Zone → DNS | Edit | CRUD DNS records |
| Zone → Firewall Services | Edit | CRUD firewall rules |
| Zone → Analytics | Read | Dashboard analytics |
| Zone → Cache Purge | Purge | Cache purge (all / per URL) |

> *Edit* permission already implies *Read*, so there's no need to add Read separately for DNS, Firewall, or Zone Settings.

### 5. Zone Resources

Choose the scope:

- **All zones** — the token can access every domain in the account
- **Specific zone** — restrict to one or a few domains (recommended if you only manage a subset)

### 6. (Optional) IP Address Filtering

If your Nawasara server has a static IP, add **Client IP Address Filtering** → **Is in** → enter the IP. This protects against the token being used from anywhere else if it leaks.

### 7. (Optional) Token lifetime

Set **Start Date** and **End Date** for a temporary token. Leave blank for a permanent one.

### 8. Create the token

Click **Continue to summary**, review, then **Create Token**.

**Copy the displayed token immediately** — Cloudflare only shows it once.

### 9. Note the Account ID

Find your Account ID:

- Open any zone's **Overview** page
- The Account ID is in the right sidebar under **API**

Or read it from any dashboard URL: `dash.cloudflare.com/{ACCOUNT_ID}/...`

## Storing credentials in Vault

1. Open Nawasara → `/nawasara-vault`
2. Choose the **Cloudflare** group
3. Fill in:
   - **API Token** — paste the token from step 8
   - **Account ID** — from step 9
4. Save

The package picks up credentials from Vault automatically.

## Verification

Open **Cloudflare → Zones** in the sidebar. Your domain list should appear after the first sync.

If it doesn't, check:

- The token is pasted exactly (no leading or trailing whitespace)
- Account ID matches the destination account
- Token permissions include every feature you want to use
- Zone Resources includes the domains you intend to manage

## Permissions

| Permission | Description |
|---|---|
| `cloudflare.zone.view` | View zone list |
| `cloudflare.dns.view` | View DNS records |
| `cloudflare.dns.create` | Create a DNS record |
| `cloudflare.dns.edit` | Edit a DNS record |
| `cloudflare.dns.delete` | Delete a DNS record |
| `cloudflare.waf.view` | View firewall rules |
| `cloudflare.waf.create` | Create a firewall rule |
| `cloudflare.waf.edit` | Edit a firewall rule |
| `cloudflare.waf.delete` | Delete a firewall rule |
| `cloudflare.ssl.view` | View SSL status |
| `cloudflare.ssl.manage` | Change SSL mode |
| `cloudflare.analytics.view` | View analytics |
| `cloudflare.cache.purge` | Purge cache |
| `cloudflare.ddos.view` | View security level |
| `cloudflare.ddos.manage` | Change security level / Under Attack mode |

All permissions are auto-assigned to the `developer` role by the seeder.

## Author

**Pringgo J. Saputro** &lt;odyinggo@gmail.com&gt;

## License

MIT

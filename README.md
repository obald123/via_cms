# VIA Foundation CMS

Headless Drupal 10 backend for the VIA Foundation React site. Drupal owns all
editorial content and receives Partner enquiries; the React SPA (deployed to
Vercel) reads from it at runtime.

- Local: <http://via-cms.local> (XAMPP vhost)
- Admin: `/user/login`
- Frontend repo: `Premium Nonprofit Website Design`

## The API the frontend consumes

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v1/site-content` | GET | Every collection the site renders, in one payload. Cached and tagged `node_list`, so saving any node invalidates it immediately. |
| `/api/v1/contact` | POST | Partner enquiry form. Stores a `partner_enquiry` webform submission. Honeypot + 5-per-hour-per-IP flood limit. |
| `/jsonapi` | GET | Core JSON:API, for ad-hoc queries. Read-only. |

Both custom endpoints live in `web/modules/custom/via_api`.

CORS allow-list is in `web/sites/default/services.yml` — add the production and
preview frontend origins there before going live.

## Content model

12 content types, one per collection the frontend used to hardcode:

`project`, `story`, `news`, `service`, `team_member`, `partner`, `testimonial`,
`hero_stat`, `impact_card`, `country`, `funding_allocation`, `yearly_progress`.

Three things worth knowing:

- **Ordering** is by `field_weight`, then node id. Every type has it.
- **Icons** are stored as lucide-react component *names* (`field_icon`), matched
  to real components by `src/app/api/icons.ts` in the frontend. Adding an icon
  means adding it to the allowed values here *and* to that registry.
- **`country` feeds two visualisations**: the Africa map markers (lat/lon) and
  the country bar chart (`field_hectares_k`, shown when `field_show_in_chart`
  is ticked). One place to edit, both charts update.

## Scripts

```bash
# Rebuild the content model (idempotent — safe to re-run)
vendor/bin/drush php:script scripts/build-content-model.php

# Import seed/content.json into nodes (idempotent — matches on slug/title)
vendor/bin/drush php:script scripts/seed-content.php
```

`seed/content.json` is generated from the frontend repo:

```bash
node scripts/export-content.mjs
```

## Deploying to cPanel

1. **Subdomain** — create `cms.via-foundation.org` with its document root set to
   this project's `web/` directory. Issue SSL via AutoSSL.
2. **PHP** — cPanel → MultiPHP Manager, select PHP 8.2 or 8.3 for the subdomain.
   In MultiPHP INI Editor enable `gd`, `pdo_mysql`, `mbstring`, `curl`, `zip`,
   `intl`, `opcache`; set `memory_limit` to at least 256M.
3. **Database** — create a MySQL database and user in cPanel, grant ALL.
4. **Code** — with SSH: `git clone` then `composer install --no-dev
   --optimize-autoloader`. Without SSH: run that locally and upload the whole
   tree (including `vendor/`) over SFTP.
5. **settings.php** — set the production database credentials, a fresh
   `$settings['hash_salt']`, `$settings['trusted_host_patterns'] =
   ['^cms\.via-foundation\.org$']`, `$settings['config_sync_directory'] =
   '../config/sync'`, and point `$settings['file_private_path']` at a directory
   **outside** the web root.
6. **Config** — `drush cim` to import the content model, then run the seeder or
   enter content by hand.
7. **Cron** — add a cPanel cron job running `drush cron` hourly.
8. **CORS** — add the Vercel production and preview origins to
   `web/sites/default/services.yml`.
9. **Frontend** — set `VITE_DRUPAL_API_URL=https://cms.via-foundation.org` in the
   Vercel project's environment variables and redeploy.

## Local development notes

- Apache vhost and the `via-cms.local` hosts entry are configured on the dev
  machine; both `httpd-vhosts.conf` and `hosts` needed elevation to edit.
- `web/sites/default/services.yml` is local-only (not in `default.services.yml`
  form) and carries the CORS settings — remember it does not travel via
  `drush cex`/`cim`, so it must be set on each environment.

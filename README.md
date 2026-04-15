# careconnect_hcp

Local dev environment for **hcp.carepharma.com.au** — a healthcare-professionals LMS built on WordPress.

## Quickstart

A fresh `git clone` gives you the **code we own** (themes we edit, `tbst-custom-report`, docker config). It does **not** give you a runnable site — licensed third-party plugins, bundled `vendor/` dirs, and uploaded media are not tracked in git and must be pulled from Azure Blob.

### Prereqs

- Docker Desktop (WSL2 backend recommended — avoid the Windows bind-mount perf cliff)
- Azure CLI, logged in to the Panwar Health tenant:
  ```bash
  az login --tenant cf8d8b82-d1e1-44f7-a41a-941cfa7a82b1
  ```

### One-time: pull non-git artifacts from Blob

```bash
# DB seed (~74 MB) — runs automatically on first `docker compose up`
az storage blob download \
  --account-name stcareconnect --container-name seed-sql \
  --name 01-seed-2026-04-14.sql \
  --file db/init/01-seed.sql --auth-mode key

# Site essentials (~500 MB): third-party plugins, bundled vendor/ dirs, uploads/
az storage blob download \
  --account-name stcareconnect --container-name backups \
  --name site-essentials-2026-04-15.tar.gz \
  --file /tmp/site-essentials.tar.gz --auth-mode key
tar xzf /tmp/site-essentials.tar.gz   # extracts into ./site/
```

The tarball explicitly **excludes** WP core, default themes, and bundled plugins (akismet, hello-dolly) — the WordPress Docker image auto-populates those on first boot. It also excludes `tbst-pma/`, `.bak/.bk` files, and cache directories for security/hygiene reasons.

### Boot

```bash
cp .env.example .env          # edit if you want different salts, port, or passwords

docker compose up -d          # WP image fills in core (wp-admin/, wp-includes/, ...) on first run

# First-time only: rewrite URLs to localhost and deactivate dev-unfriendly plugins
docker compose run --rm wpcli wp search-replace \
  'https://hcp.carepharma.com.au' 'http://localhost:8080' \
  --all-tables --skip-columns=guid
docker compose run --rm wpcli wp plugin deactivate \
  wp-rocket wordfence wp-mail-smtp-pro imagify object-sync-for-salesforce
docker compose run --rm wpcli wp cache flush
```

Open http://localhost:8080.

### Refreshing `site-essentials`

When third-party plugins are updated on prod, or uploads grow meaningfully, refresh the tarball:

```bash
# From a machine that has a full, working site/ (prod mirror or full provider zip extract):
tar czf site-essentials-$(date +%Y-%m-%d).tar.gz \
  --exclude='site/wp-admin' --exclude='site/wp-includes' \
  --exclude='site/wp-*.php' --exclude='site/xmlrpc.php' \
  --exclude='site/index.php' --exclude='site/readme.html' \
  --exclude='site/license.txt' --exclude='site/robots.txt' \
  --exclude='site/wp-content/themes/twentytwenty*' \
  --exclude='site/wp-content/plugins/akismet' \
  --exclude='site/wp-content/plugins/hello.php' \
  --exclude='site/wp-content/languages' \
  --exclude='site/wp-content/{cache,wpo-cache,upgrade,ai1wm-backups,webtoffee_export,wp-rocket-config,backup-db,backups,blogs.dir,wflogs}' \
  --exclude='site/wp-content/debug.log' --exclude='site/wp-content/error_log' \
  --exclude='site/wp-content/themes/wp-spinnr-child/tbst-pma' \
  --exclude='*.bak' --exclude='*.bk' --exclude='*-bk' --exclude='*.orig' --exclude='*~' \
  site/

az storage blob upload \
  --account-name stcareconnect --container-name backups \
  --name site-essentials-$(date +%Y-%m-%d).tar.gz \
  --file site-essentials-$(date +%Y-%m-%d).tar.gz --auth-mode key
```

Then update the `--name` above to point at the new tarball.

## Admin login

Admin accounts were seeded from prod. Pick an existing admin and reset the password for local use:

```bash
docker compose run --rm wpcli wp user list --role=administrator
# Then, for the user you want to log in as:
docker compose run --rm wpcli wp user update <id> --user_pass=admin
```

Admin accounts in the current seed (as of 2026-04-14): `devtbst` (id 10), `tbstDev` (id 1), `Jodi Paul-Epstein` (id 17828), `Panwar-education` (id 23731), plus a few support/integration logins.

## Everything else

- [`CLAUDE.md`](./CLAUDE.md) — stack details, hard rules, directory layout, common operations.
- [`docs/DEPLOY.md`](./docs/DEPLOY.md) — **read before pushing anything to prod.**
- [`.deploy-exclude`](./.deploy-exclude) — machine-readable list of paths that must never reach prod.
- [`docs/AZURE_STAGING.md`](./docs/AZURE_STAGING.md) — staging architecture (not built yet).

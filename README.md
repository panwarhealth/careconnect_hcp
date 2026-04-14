# careconnect_hcp

Local dev environment for **hcp.carepharma.com.au** — a healthcare-professionals LMS built on WordPress.

## Quickstart

```bash
# 1. Put the SQL seed in place (one-time, file is not in git).
#    From Azure Blob (preferred — the source of truth):
az login --tenant cf8d8b82-d1e1-44f7-a41a-941cfa7a82b1
az storage blob download \
  --account-name stcareconnect --container-name seed-sql \
  --name 01-seed-2026-04-14.sql \
  --file db/init/01-seed.sql --auth-mode login
# Or, if you still have the original provider zip locally:
# cp /path/to/database_2026-04-14.sql db/init/01-seed.sql

# 2. Set up local env
cp .env.example .env
# (edit .env if you want different salts, port, or passwords)

# 3. Boot
docker compose up -d

# 4. First-time only: rewrite URLs to localhost and disable plugins that fight dev
docker compose run --rm wpcli wp search-replace \
  'https://hcp.carepharma.com.au' 'http://localhost:8080' \
  --all-tables --skip-columns=guid
docker compose run --rm wpcli wp plugin deactivate \
  wp-rocket wordfence wp-mail-smtp-pro imagify object-sync-for-salesforce
docker compose run --rm wpcli wp cache flush

# 5. Open http://localhost:8080
```

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

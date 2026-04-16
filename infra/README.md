# Staging infra scripts

Spin up / tear down the ephemeral Azure staging environment for hcp.carepharma.com.au.

## Prereqs

- Local `.env` file with `AHPRA_PIEWS_USER=` and `AHPRA_PIEWS_PASSWORD=` set (staging reads them from there — they never get committed)
- `az login --scope https://management.core.windows.net//.default`
- Tools installed: `az`, `openssl`, `mysql` client, `zip`, `curl`, `sshpass`

## TL;DR

```bash
./infra/staging-up.sh      # ~15 min, creates everything, seeds DB, resets admin passwords
./infra/staging-stop.sh    # pause compute between test sessions (~$1/day idle)
./infra/staging-start.sh   # resume (~2 min)
./infra/staging-down.sh    # delete all resources, $0 cost
./infra/staging-seed.sh    # re-seed DB from local Docker (or a dump path you pass)
```

## What spins up

| Resource | SKU | Approx cost running |
|---|---|---|
| MySQL Flexible Server `hcp-staging-db` | B1ms Burstable, 20GB | ~$15/mo |
| App Service Plan `hcp-staging-plan` | B1 Linux | ~$13/mo |
| Web App `hcp-staging-app` | PHP 8.3 | (included in plan) |

Total ~$28/mo running, ~$1/day stopped, $0 torn down.

## Cost strategy

- **Active development (hitting staging 1-2× a week):** leave running
- **Between iterations:** `staging-stop.sh` → resume in 2 min
- **Dormant for a month+:** `staging-down.sh` → re-create when needed

## Files in this dir

- `staging-env.sh` — shared config (resource names, location, admin password)
- `staging-up.sh` — end-to-end provision
- `staging-seed.sh` — (re)seed DB from local dump + sanitize
- `staging-stop.sh` / `staging-start.sh` — pause/resume compute
- `staging-down.sh` — full teardown
- `staging-default.conf` — nginx config so WordPress permalinks work on Linux PHP App Service (default image doesn't read `.htaccess`)
- `staging-startup.sh` — startup script that installs the nginx config
- `staging-sanitize.sql` — PII scrub + URL normalisation + admin password reset
- `.staging-secrets` — generated at spin-up, gitignored. Holds MySQL admin password.

## What the seed does

1. Dumps local Docker MySQL → sanitises via `staging-sanitize.sql`:
   - Replaces all user emails with `user<id>@staging.local`
   - Blanks WP Rocket / Wordfence / Salesforce / SMTP API keys
   - Resets every user password to an unmatchable hash
   - **Resets Rob-Panwar and Panwar-education to `staging123`** so you can log in
   - Points `home` / `siteurl` at the staging domain
   - Drops the `rewrite_rules` option so WP regenerates permalinks
2. Imports to Azure MySQL
3. Reports any leftover `localhost:8080` references inside serialized option values (finish via Better Search Replace plugin in wp-admin)

## Login credentials after spin-up

- **Staging URL:** `https://hcp-staging-app.azurewebsites.net`
- **Rob-Panwar / staging123**
- **Panwar-education / staging123**

## Known gotchas

- Azure Linux PHP App Service runs nginx, not Apache — `.htaccess` is ignored. The `staging-default.conf` + startup script replaces the default nginx server block with WP-friendly `try_files … /index.php?$args`.
- After importing a dump, the `rewrite_rules` option caches stale permalinks. Sanitize SQL deletes this; WP regenerates on first request.
- URLs inside serialized option values (widgets, plugin settings) can't be fixed by a plain `UPDATE`. Use Better Search Replace plugin for those after seed.
- MySQL admin password is random per spin-up and lives in `.staging-secrets`. Don't commit.
- `az` CLI tokens expire on their own clock (often after a short window). If a script fails with `AccountUnusable`, re-run `az login --scope https://management.core.windows.net//.default`.

# CLAUDE.md — project guide for future Claude sessions

This file teaches Claude (me, in future sessions) and any new dev how to work in this repo without re-learning the context.

## What this is

An **inherited WordPress site** for **hcp.carepharma.com.au** — a healthcare-professionals LMS (courses, quizzes, certificates) with gated content. Taken over from the previous hosting provider in April 2026.

**It is not:**
- A greenfield project — conventions, plugin choices, and theme structure were dictated by the prior team.
- A single-page app, a headless CMS, or JAMstack. It's a classic server-rendered WordPress monolith.

## Production stack (mirror exactly in local dev)

| Component | Version | Source |
|---|---|---|
| PHP | 8.3.30 | live host phpMyAdmin footer |
| MySQL | 8.0.41 Community | live host phpMyAdmin |
| Apache | 2.4.66 (Debian) | live host phpMyAdmin |
| WordPress | whatever ships in the zip — don't upgrade without explicit instruction | zip contents |
| Active theme | `wp-spinnr-child` (child of `wp-spinnr`) | DB options |
| DB table prefix | `tbstwp_` | zip's `wp-config.php` |

**Versions are pinned for a reason** — plugin/PHP drift is the usual cause of "works locally, breaks in prod." Always verify with:

```bash
docker compose exec wordpress php -v
docker compose exec db mysql --version
```

## Hard rules (in priority order)

1. **SOLID / KISS / YAGNI are applied BEFORE writing code, not after.** Propose structure, get alignment, then build. Never retrofit principles. (See memory: `feedback_solid_upfront.md`.)
2. **Never commit secrets.** `.env` is gitignored, `.env.example` is the template. Salts, DB passwords, FTP creds, Salesforce tokens — none of them in git, ever.
3. **Never deploy `wp-config.php`, `.htaccess`, WP core, or third-party plugins to prod.** See [`docs/DEPLOY.md`](./docs/DEPLOY.md) for the allow-list and [`.deploy-exclude`](./.deploy-exclude) for the machine-readable deny-list. The repo's `wp-config.php` is a **dev-only** artifact — prod's original is untouched on the live server.
4. **Staging-first for non-trivial changes.** Claude (me) owns the Azure staging lifecycle — spin up, deploy, smoke-test, tear down. See `docs/AZURE_STAGING.md`.
5. **Inherited plugins are frozen.** Don't "modernise" LearnDash / Formidable / RCP / WP Rocket unless explicitly asked. Deprecation notices from old plugins are noise, not tasks.
6. **🚩 Env-var refactors of external API credentials MUST go through staging before prod.** Secret refactors look innocuous but the failure mode is silent on prod: a missing `putenv()` in `wp-config.php` means `getenv()` returns `false`, the API call fails, and a user-facing flow breaks quietly. See `docs/DEPLOY.md` → "High-risk pending deploys" for the queue of refactors awaiting staging validation.

## Directory layout

```
F:\Github\careconnect_hcp\
├── .env                   # gitignored, real local values
├── .env.example           # committed, placeholders + docs
├── .gitignore             # WP-specific, ruthless about what enters git
├── .dockerignore
├── docker-compose.yml     # 3 services: wordpress, db, wpcli (tools profile)
├── README.md              # quickstart — 60 seconds cold to hot
├── CLAUDE.md              # this file
├── db/
│   ├── init/
│   │   └── 01-seed.sql    # gitignored, 74 MB, auto-runs on first boot
│   └── README.md          # re-seed + fresh-dump instructions
├── docs/
│   ├── DEPLOY.md          # manual FTP allow-list + deny-list for prod
│   └── AZURE_STAGING.md   # staging architecture sketch (not built yet)
└── site/                  # the webroot, bind-mounted into /var/www/html
    ├── wp-config.php      # env-driven, no secrets inline
    ├── .htaccess          # prod-compatible, localhost bypasses HTTPS redirect
    ├── wp-content/
    │   ├── themes/wp-spinnr-child/   # <- YOUR code lives here
    │   ├── plugins/tbst-custom-report/ # <- YOUR custom plugin
    │   ├── plugins/... (all third-party, gitignored)
    │   ├── uploads/       # gitignored, 416 MB of media
    │   └── ...
    └── EDM/               # email campaign HTML templates (committed, ~44K)
```

## Architecture — why 3 services, not more

Single-Responsibility for infra:

- `wordpress` — PHP 8.3 + Apache (the exact prod stack). Bind-mounts `./site` so host edits are instant.
- `db` — MySQL 8.0. Seeds itself from `./db/init/*.sql` on first boot via the image's `docker-entrypoint-initdb.d` hook.
- `wpcli` — **not** a running service. Started on-demand via `docker compose run --rm wpcli wp <cmd>` thanks to the `tools` profile. Keeps `docker compose up` clean.

**Deliberately NOT included** (YAGNI):
- phpMyAdmin — use `docker compose exec db mysql` or a GUI like DBeaver connecting to an exposed port (commented out in compose).
- MailHog — plugins that send mail (WP Mail SMTP Pro) are deactivated locally.
- Redis / object cache — WP Rocket and Imagify are deactivated locally.
- xdebug — add only when actually debugging, not as prophylaxis.
- Nginx reverse proxy — Apache matches prod, no drift.

## Common operations

### Start / stop

```bash
docker compose up -d         # start
docker compose down          # stop (keeps DB data)
docker compose down -v       # stop + nuke DB (re-runs the seed on next up)
docker compose logs -f wordpress  # tail Apache+PHP logs
```

### WP-CLI (use this, not the admin UI, for anything automatable)

```bash
docker compose run --rm wpcli wp <command>
```

Examples:

```bash
# Reset admin password to something you'll remember (LOCAL ONLY)
docker compose run --rm wpcli wp user update <user-id> --user_pass=admin

# Search-replace after you change WORDPRESS_PORT in .env
docker compose run --rm wpcli wp search-replace 'http://localhost:8080' 'http://localhost:9000' --all-tables --skip-columns=guid

# List active plugins
docker compose run --rm wpcli wp plugin list --status=active

# Flush caches
docker compose run --rm wpcli wp cache flush
```

### Exploring the LMS locally (bypassing the AHPRA gate)

On prod, access to gated content requires registering through a Formidable form that validates against AHPRA's PIEWS API. Locally, two shortcuts:

1. **Log in as any admin** (yours, or Panwar-education). LearnDash and Restrict Content Pro both grant admins access to all courses without membership checks. Admin view ≠ real HCP view though (you see edit buttons, skip quiz gates, see drafts).
2. **Log in as a real pre-verified HCP** by resetting their local password. Their `user_login` IS their AHPRA number. 1,316 such users exist in the seeded DB. Example:
   ```bash
   docker compose run --rm wpcli wp user update 25011 --user_pass='lms-explore'
   # then log in as MED0001552054 / lms-explore
   ```
   Users with `MED`/`NMW`/`PHA` logins + `tbstwp_rcp_memberships.status='active'` (level 22) have full LMS access.

Both are local-only — the seeded DB has real user PII, but nothing leaves your machine. Don't screenshot/share course enrollments or user details without redacting.

### Fresh DB from prod

1. Export via phpMyAdmin (or `mysqldump` if you get SSH access) → save as `db/init/01-seed.sql`, overwriting the old one.
2. `docker compose down -v && docker compose up -d`
3. `docker compose run --rm wpcli wp search-replace 'https://hcp.carepharma.com.au' 'http://localhost:8080' --all-tables --skip-columns=guid`
4. `docker compose run --rm wpcli wp plugin deactivate wp-rocket wordfence wp-mail-smtp-pro imagify object-sync-for-salesforce`
5. `docker compose run --rm wpcli wp cache flush`

### Taking a fresh local dump (e.g. for staging seed)

```bash
docker compose exec -T db \
  mysqldump --no-tablespaces -u root -p"${MYSQL_ROOT_PASSWORD}" hcp_care \
  > db/dumps/$(date +%Y-%m-%d-%H%M).sql
```

`db/dumps/` is gitignored.

## Known gotchas

- **Windows bind-mount is slow.** First-request page loads can take 15–25s because PHP reads dozens of files through 9P. Subsequent requests are faster once OPcache warms. Moving the repo into the WSL2 filesystem fixes this but is a big migration — defer until it actually blocks work.
- **RCP deprecation notices in WP-CLI output.** `Restrict Content Pro` uses dynamic properties and triggers PHP 8.x deprecation warnings on every WP-CLI call. Harmless noise — filter with `2>/dev/null` when it gets annoying.
- **Paid plugins.** LearnDash, Formidable Pro (+ 10 addons), WP Rocket, WP Mail SMTP Pro, Imagify, ACF Pro, Restrict Content Pro. Licences live with the client. If a plugin activates an "update available" nag, **do not click update** — updates need to go through licence validation on the client's account.
- **Plugin deactivation is local-only.** `wp plugin deactivate` writes to the DB, which is local. The list of plugins to deactivate after re-seeding is above.
- **Salesforce, SMTP, and Wordfence are all deactivated locally** — they're intentional. Don't reactivate without a reason.
- **AHPRA validation handler has dead code on line ~798 of `functions.php`.** The AJAX handler calls `wp_send_json($ahpra_data, 422)` unconditionally BEFORE checking the result, so everything below (including `set_ahpra_cookie()`) is unreachable. Also: `set_ahpra_cookie()` is called but never defined anywhere in the codebase — it would be a fatal error if reached. The frontend JS appears to work around this by parsing `success` out of the 422 response body. **This matters for staging validation** — don't assume the code works just because the prod site works; it's held together by JS shim, not by the PHP flow reading cleanly. When validating the AHPRA refactor on staging, watch the **browser network tab** for the 422 + success combo, not just the rendered page.
- **Many existing users have a corrupted `tbstwp_capabilities` entry** — a bogus 2-char key `)}` alongside their real role, plus often **two rows** of `tbstwp_capabilities` for the same user where WP expects one. Unknown cause (plugin bug, migration artifact, partial injection). Impact on WP: zero (unknown cap names are silently ignored). But it's a data-cleanup opportunity during any user-audit conversation, and a possible "tell" if you're ever investigating weird permission behaviour.
- **`wp-spinnr-child/tbst-pma/` — removed from prod 2026-04-14.** Was phpMyAdmin 5.2.3 dropped into the child theme by TBST Digital (prior agency — same prefix as `tbstwp_` tables and `tbst-custom-report` plugin). Public at `https://hcp.carepharma.com.au/wp-content/themes/wp-spinnr-child/tbst-pma/` until manually deleted via FTP. **Don't re-introduce.** The directory is gitignored so it can't sneak back via a theme sync. For DB admin going forward: Plesk's built-in phpMyAdmin, or SSH tunnel + DBeaver.

## Site notables

- **LMS:** LearnDash (`sfwd-lms`) — courses, quizzes, topics. Test data lives in `tbstwp_posts` with post types like `sfwd-courses`, `sfwd-quiz`, `sfwd-topic`.
- **Membership:** Restrict Content Pro — user tiers, content gates. Config in `tbstwp_rcp_*` tables.
- **Forms:** Formidable Pro + Views + PDFs + Chat + Mailchimp + Salesforce + Registration. Heavy form surface area.
- **Reporting:** `tbst-custom-report` — custom plugin we own. This is where your PHP work will typically live.
- **Email templates:** `site/EDM/*.html` — campaign HTML referenced by transactional emails.

## Memory

Persistent context for this project lives in `~/.claude/projects/F--Github-careconnect-hcp/memory/`. Key entries:

- `feedback_solid_upfront.md` — the hard rule about architecture-before-code.
- `project_live_stack.md` — exact PHP/MySQL/Apache versions.
- `project_azure_tenant.md` — Panwar Health tenant, client-billed, Blob for non-git artifacts.
- `project_hcp_site.md` — inherited-site context.
- `user_role.md` — communication/environment preferences.

Update these when facts change.

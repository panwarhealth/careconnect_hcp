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
7. **🚩 Plugin DB migrations MUST be run after every prod deploy.** Any change that touches DB state (page content, postmeta, options) lives as a file in `site/wp-content/plugins/<plugin>/migrations/`. After syncing files to prod, open **wp-admin → Tools → HCP Migrations** and click "Run pending migrations". Idempotent (safe to re-run). Forgetting this step means prod files reference shortcodes/options the DB doesn't have yet, and the frontend breaks silently.

## Directory layout

**Canonical path: `~/projects/careconnect_hcp` inside WSL2 Ubuntu** (migrated 2026-04-15 from `F:\Github\careconnect_hcp` — WSL ext4 bind-mount gives ~280ms warm page loads vs 15–25s on NTFS/9P). Edit here, run Docker here, commit here. The F:\ path is a short-lived fallback and should be deleted once WSL is validated. VS Code connects via the WSL extension (`code .` from WSL terminal).

```
~/projects/careconnect_hcp/
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

### DB migrations (plugin-scoped)

`hcp-mca-review-workflow` (and any future plugin that ships DB changes) owns a `migrations/` folder. Each file is `YYYY-MM-DD-slug.php` returning an array with `description` and an idempotent `up` callable. The runner tracks applied migrations in the `hcp_mca_migrations_run` WP option.

Three ways to trigger pending migrations:
- **wp-admin → Tools → HCP Migrations** — single-click runner, shows pending + applied. Primary path for prod.
- **Plugin activation** — runs automatically on (re)activation. Useful for fresh installs.
- **Explicit function call** — `hcp_mca_migrations_run_pending()` via `wp eval` for scripted flows.

After any `git pull` / FTP sync to prod, the first step is to run pending migrations. Safe to re-run at any time.

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

- **Windows bind-mount was slow (resolved 2026-04-15 by WSL migration).** On NTFS via 9P, page loads were 15–25s. After migrating to `~/projects/careconnect_hcp` inside WSL ext4, warm page loads are ~280ms. If you're ever on a new dev machine and see the slow behavior, it's because the repo is on Windows NTFS — move it to WSL.
- **Fresh `git clone` is NOT a runnable site.** Licensed third-party plugins (LearnDash, Formidable Pro, RCP, WP Rocket, ACF Pro, etc.), bundled `vendor/` dirs inside plugins/themes (e.g. `wp-spinnr/inc/plugins/jwt/includes/vendor/autoload.php`), and `wp-content/uploads/` (~416 MB) are all gitignored. Without them, WP fatal-errors before rendering. The restore flow is documented in `README.md` → pull `site-essentials-YYYY-MM-DD.tar.gz` from `stcareconnect/backups` container and extract into `./site/`. WP core and default themes/plugins are NOT in the tarball — the Docker image populates those on first boot.
- **Refresh `site-essentials` when prod plugins update** (or uploads grow meaningfully). See README for the `tar czf` + `az storage blob upload` recipe. Cadence is probably monthly; confirm with prod reality rather than the calendar.
- **On WSL, `which az` → Windows `az.exe` (via interop).** API-only commands work, but `az storage blob upload/download --file /tmp/foo` fails with `[WinError 2] file not found` because Windows can't see `/tmp/`. Stage the artifact at `/mnt/f/Github/…` first (Windows sees it as `F:\Github\…`) and upload from there. Or install native az in WSL: `curl -sL https://aka.ms/InstallAzureCLIDeb | sudo bash`. For storage ops prefer `--auth-mode key` (auto-pulls account key via ARM token) over `--auth-mode login` (needs separate storage-scope token).
- **A fresh WSL clone needs two git-config tweaks** (per-clone, not global) or you'll see 300 phantom "modified" files after any file-system operation:
  - `git config core.fileMode false` — Docker containers and chmod alter exec bits; git should ignore that on this repo.
  - `git config core.autocrlf input` — file copies from `/mnt/f/` carry CRLF; this converts to LF on write-to-index so diffs stay clean.
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

## Mini Clinical Audit — completion flow (source of truth)

The MCA is the CPD course that Maria (Panwar Health CPD rep) reviews and reports to RACGP. Because the completion/cert/email pipeline is NOT obvious from the code alone and has several half-wired pieces, this is the definitive reference.

**Course structure**
- Course `111793` "Mini Clinical Audit" (post_type `sfwd-courses`) — has `_ld_certificate=96129` attached, so cert auto-issues on course-complete.
- Lesson `112353` "Complete Mini Clinical Audit" (post_type `sfwd-lessons`) — content is literally just the shortcode `[formidable id=161]`.
  - **Form 161 "Retrospective analysis Form"** is the actual 5-step audit (397 fields, page breaks at field_order 79/387/537/605 = 5 pages: Step 1A/1B/2A/2B/3). Name is misleading — it's the whole audit, not just the retro portion.
  - Form 177 "Repeater" is the sub-form for Step 2A's per-patient records (Patient 1/2/3).
- Quiz `116865` "Activity evaluation" (post_type `sfwd-quiz`) — a Formidable form dressed as a LearnDash quiz. Embeds **Form 209 "Activity evaluation"**.

**How a user actually gets a cert (Maria's manual flow — what works today)**
1. User completes **form 161** (audit) — submits all 5 pages, answers stored in `tbstwp_frm_items` + `tbstwp_frm_item_metas`.
2. User completes **form 209** (activity evaluation) — stored the same way.
3. **Submitting both forms does NOT auto-complete the course.** Of 13 users who completed both forms historically, 8 still have no cert. Completion requires manual admin action.
4. **Maria reviews** the audit submission via **wp-admin → Formidable → Entries → form 161**. (She used to navigate via a `/approve-user/?uid=X` link in an admin email to the user profile + "login as user" to see the frontend render — that still works but is slower than Formidable Entries.)
5. **Maria manually ticks the completion checkbox in the LearnDash section of the user's wp-admin profile** (Users → Edit user → LearnDash Profile → mark lesson 112353 complete). This fires `learndash_process_mark_complete` internally.
6. **LearnDash auto-completes the course** (all steps now done), writes `course_completed_111793` usermeta, issues cert 96129.
7. **LearnDash Notifications add-on fires** notification 112561 "Congratulations! Anal Fissure Management Mini Clinical Audit complete" to the user (not admin — recipient=`user`).

**Historical completion stats (from 2026-04-14 seed, for context)**
- 23 form-161 submissions. 5 course completions — all internal TBST/Panwar accounts (Alice, Cat, Kenneth #11/#14, TBST Digital) from UAT.
- **Zero real HCPs have been issued a cert yet.** 18 real HCPs submitted the audit; none have been approved.

**Broken/half-wired pieces (don't rely on these working — they don't)**
- **`functions.php:2722-2731` — `submitted_form_mark_completed_quiz()` has a typo.** Third branch checks `$form_id==209` again (duplicate of branch 2) instead of `==161`. Dead code. If fixed to `==161`, it would auto-mark lesson 112353 complete on audit submission — but that bypasses Maria's review and isn't what we want anyway.
- **Formidable email action 116321 "alert admin"** supposed to email `education@panwarhealth.com.au` on audit submission with subject "completed audit" and an `/approve-user/?uid=X` link. Only 2 of 23 submissions have actually triggered this email. **Every Formidable action in the DB has `frm_form_id` meta = NULL**, which strongly suggests the action↔form binding is broken/stored somewhere else. Worth an hour of digging when we have staging.
- **`/approve-user/?uid=X` URL handler** is in `functions.php:3141` but doesn't actually approve — it just redirects admin to the user's wp-admin profile page. Convenience shortcut only.
- **Copy fixes** Maria marked up in her 2026-04-15 PDF review (strings like "Your audit has been submitted for review..." and renaming "Update" → "Resubmit") — some already exist in `functions.php:3236-3285` as filter hooks for forms 161/209 but the surrounding state logic isn't quite right. Separate work item.

**What we're building next (unconfirmed, pending Maria sign-off)**
Wrap Maria's 5-click manual approval into a single "Approve & Issue Cert" button on the Formidable entry view for form 161. Button calls `learndash_process_mark_complete($entry->user_id, 112353, false, 111793)` — same effect as her checkbox, one click instead of ~5. Writes approval audit-trail meta on the frm_item. Lives in `tbst-custom-report` plugin, not the theme.

## Memory

Persistent context for this project lives in `~/.claude/projects/F--Github-careconnect-hcp/memory/`. Key entries:

- `feedback_solid_upfront.md` — the hard rule about architecture-before-code.
- `project_live_stack.md` — exact PHP/MySQL/Apache versions.
- `project_azure_tenant.md` — Panwar Health tenant, client-billed, Blob for non-git artifacts.
- `project_hcp_site.md` — inherited-site context.
- `user_role.md` — communication/environment preferences.

Update these when facts change.

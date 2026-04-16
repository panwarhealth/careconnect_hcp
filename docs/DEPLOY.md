# Deploying changes to production

> **Prod deploy is manual FTP today.** No GitHub Actions, no CI/CD — just WinSCP/FileZilla and a disciplined allow-list. GitHub Actions is reserved for later, once the staging environment exists and the workflow is proven.

## The one hard rule

**Only deploy files whose changes originated in this repo.**

That's it. If you didn't edit it, don't push it. WordPress core, third-party plugins, and the prod `wp-config.php` have been live for years — leave them alone. They update themselves or get updated via the admin UI.

The deny-list is codified in [`.deploy-exclude`](../.deploy-exclude) at the repo root — rsync/gitignore syntax, one pattern per line. It's not wired into any tool today (manual FTP), but it's the canonical reference and drops straight into `rsync --exclude-from=.deploy-exclude` or a GitHub Actions `exclude:` block when we get there.

## Allow-list: push ONLY these paths

| Path in repo | Path on prod (Plesk) | When |
|---|---|---|
| `site/wp-content/themes/wp-spinnr-child/` | `httpdocs/wp-content/themes/wp-spinnr-child/` | Every theme change |
| `site/wp-content/themes/wp-spinnr/` | `httpdocs/wp-content/themes/wp-spinnr/` | Only if the parent theme was edited (rare — prefer child) |
| `site/wp-content/plugins/tbst-custom-report/` | `httpdocs/wp-content/plugins/tbst-custom-report/` | Every custom plugin change |

## Deny-list: these NEVER get pushed

- `site/wp-config.php` — prod has its own with real DB creds on port 9306. Overwriting will break the site instantly.
- `site/.htaccess` — prod's may have Plesk-added rules (security headers, rewrites) that aren't in the repo.
- `site/wp-admin/`, `site/wp-includes/`, `site/index.php`, `site/wp-*.php`, `site/xmlrpc.php`, `site/readme.html`, `site/license.txt` — WordPress core. Let prod update itself via auto-update.
- `site/wp-content/plugins/*` **except** `tbst-custom-report/` — third-party plugins are frozen on prod.
- `site/wp-content/uploads/`, `site/wp-content/cache/`, `site/wp-content/upgrade/` — runtime data.
- Anything under `.git/`, `.env`, `docs/`, `db/`, `docker-compose.yml`, `.gitignore`, `.dockerignore` — tooling, never belongs in the webroot.

## Staging-first (mandatory for non-trivial changes)

Before touching prod:

1. Spin up the Azure staging environment (see `docs/AZURE_STAGING.md` — Claude owns this).
2. Deploy your change to staging first, using the same allow-list.
3. Smoke-test: login, click through the LMS course flow, submit a Formidable form, check a Restrict Content Pro gate.
4. Only then, repeat the deploy against prod.

**Trivial** = a tiny CSS tweak, a typo fix in a theme template. Anything touching PHP logic, plugin hooks, or DB schema is non-trivial.

## WinSCP / FileZilla setup

Connect to the Plesk host with the FTP creds. Configure:

- **Local root:** `F:\Github\careconnect_hcp\site\wp-content\themes\wp-spinnr-child\`
- **Remote root:** `/httpdocs/wp-content/themes/wp-spinnr-child/`
- **Transfer mode:** binary (WinSCP default)
- **Preserve timestamps:** on
- **Skip existing files with identical size/timestamp:** on (faster for re-syncs)

Save these as a named session per-deploy-target (`hcp-prod-theme`, `hcp-prod-plugin`) so you can't accidentally drag into the wrong remote directory.

## Before you hit "upload"

Mental checklist:

- [ ] My diff only touches files in the allow-list above?
- [ ] I've tested this on local at http://localhost:8080 and it works?
- [ ] Staging has run this change and nothing regressed?
- [ ] The prod site is currently healthy (check homepage before deploy so you know your change is the cause if something breaks)?
- [ ] I have a rollback plan? (For FTP: the overwritten files. Take a copy before uploading, or push the previous version from git if it's clean.)
- [ ] **Do I have any pending DB migrations?** Check `site/wp-content/plugins/hcp-mca-review-workflow/migrations/` (and any other plugin's `migrations/` dir) for files added since the last deploy. If yes → step below is required.

## After you upload — run migrations

If the deploy included changes to any `migrations/` folder, log into wp-admin on prod and:

1. **Tools → HCP Migrations**
2. Review the "Pending" list — every file shipped should appear here.
3. Click **Run pending migrations**.
4. Confirm all entries moved to "Applied" with green `OK` status.

The runner is idempotent — re-running is safe. But skipping it means prod files reference shortcodes / options the DB doesn't have, which breaks the frontend silently.

## 🚩 High-risk pending deploys (DO NOT ship without staging validation)

Every entry here represents a change that lives in `main` but **must not be FTP'd to prod** until it has been validated on the Azure staging environment (`docs/AZURE_STAGING.md`). These changes typically involve environmental dependencies (env vars, external APIs, DB schema) where a silent misconfig on prod is worse than a loud error on staging.

### 1. AHPRA PIEWS credential refactor — `site/wp-content/themes/wp-spinnr-child/functions.php`

**Status: DEPLOYED 2026-04-16.** `wp-config.php` on prod has the `putenv()` lines, refactored `functions.php` live, homepage + `getenv()` verified green post-deploy. `functions.php-bk` / `footer.bak` / `header.bak` deleted from prod (cred leak plugged). Credential rotation still pending — old password must be rotated with AHPRA because it was publicly served via `functions.php-bk` for months.

**What changed:** `check_if_aphra_exists_soap()` and `check_if_aphra_exists_curl()` now read creds via `getenv('AHPRA_PIEWS_USER')` / `getenv('AHPRA_PIEWS_PASSWORD')` instead of hardcoded literals. This is the gatekeeping path for **LMS access** — if it breaks on prod, new user registration stops working and existing users can't log in via AHPRA.

**Deploy prerequisites (in order):**

1. **Staging first.** Spin up Azure staging (`docs/AZURE_STAGING.md`), set `AHPRA_PIEWS_*` env vars in App Service configuration, deploy the refactored `functions.php`, run the AHPRA lookup end-to-end with a real AHPRA number against AHPRA's **test** endpoint (`ws2test.ahpra.gov.au` — uncomment line 839 of functions.php for staging only; prod keeps `ws2.ahpra.gov.au`). **Critical verification:** watch the browser network tab, not just the rendered form. The handler returns HTTP 422 even on success (known dead-code bug — see `CLAUDE.md` → known gotchas). Success = response body contains `"success":true` inside the 422. If the body shows `Invalid Professional Number!` or similar, it's a real auth/validation failure. Don't trust the page render alone.
2. **Rotate AHPRA creds with AHPRA** before prod deploy — the pre-refactor password (redacted; see the original provider zip or ask the PM for context) was publicly leaked via `functions.php-bk` for months, so it must be treated as burned. PM has been notified.
3. **Prod `wp-config.php` edit via FTP first** (before uploading functions.php):
   ```php
   putenv('AHPRA_PIEWS_USER=<new user from AHPRA>');
   putenv('AHPRA_PIEWS_PASSWORD=<new password from AHPRA>');
   ```
4. **Verify prod homepage still loads** (catches syntax errors in the wp-config edit).
5. **Upload refactored `functions.php`** via FTP.
6. **Smoke-test on prod:** attempt AHPRA registration with a known-good AHPRA number. Verify the response looks the same as staging's.
7. **Delete `functions.php-bk`, `footer.bak`, `header.bak` from prod** via FTP — they're a source-code leak of the old creds.

**Failure mode if shipped without prereq 3:** `getenv()` returns `false` → SOAP auth fails → every AHPRA registration attempt on prod returns an error → LMS sign-up goes down. Recovery: re-upload the pre-refactor `functions.php` (preserved in git history) or fix wp-config.php.

**Estimated time to execute safely:** 45 min, mostly waiting for staging boot + AHPRA credential rotation turnaround. Absolutely not a "quick FTP push."

---

## When this process outgrows itself

Signs you need to graduate to GitHub Actions:

- More than one person deploying.
- Deploys happening multiple times per week.
- "I forgot which files I touched" becoming a real risk.
- Multiple customer-facing environments (blue/green, per-tenant).

At that point the Actions workflow will use `SamKirkland/FTP-Deploy-Action` with the same allow-list encoded as `include:` patterns, and FTP creds in GitHub Actions secrets. Not today.

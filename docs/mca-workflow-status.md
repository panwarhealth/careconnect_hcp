# MCA review workflow — status & next steps

**Read this first when resuming work on the Mini Clinical Audit (course 111793) review/approval flow.**

Living doc. Update at the end of every session so the next one starts with full context.

Last updated: 2026-04-16

---

## TL;DR — where we are right now

**Shipped to prod 2026-04-16.** Plugin deployed, 4 migrations applied, wp-config.php has AHPRA env vars, cred-leak `.bak`/`-bk` files deleted from theme. Staging environment built on Azure and validated the full flow before prod deploy. See "What's on prod" below for the exact shipping manifest.

**2026-04-16 correction:** the "6 stuck HCPs" premise was wrong. DB re-check shows all 6 "stuck" form-161 entries are `is_draft=1` (abandoned audits, not finalised). Zero real HCPs are JS-gate blocked. The real backlog is **Maria's approval queue: 9 users finalised both forms and are waiting for manual approval** — but only 1 is clearly a real HCP (Deepani lokuratna, MED0001541414). Ian Petersen (`3338155476`, no AHPRA prefix — could be tester or botched registration) is ambiguous. Maria Duran is Maria Panwar herself (internal). The other 6 are TBST/Panwar internal. Task #17 (Approved checkbox UX) is still the highest-value build but user-impact is smaller than first thought. Task #15 cancelled.

**Today's wins:**
- Course-complete guard prevents cert from auto-firing without Maria's approval
- Form 161 submit now reliably marks lesson 112353 complete (server-side, no JS)
- Removed the prior dev's flaky `.trigger('click')` JS hack from form 161 confirmation
- Maria gets one (and only one) "ready for review" email when both forms are in
- TBST recipients (`ben@`, `jobs@tbstdigital.com.au`) scrubbed from all active email configs

**Today's discoveries (not yet fixed):**
- A) Progress bar weights audit (5 steps, 397 fields) the same as eval (small survey) → stuck at 50% looks bad
- B) Form 209 submit doesn't actually mark quiz 116865 complete — `learndash_process_mark_complete` on a quiz needs a `learndash_pro_quiz_statistic` row first
- C) Approve UX should be a NEW checkbox on user profile labelled "Submission Approved", separate from the auto-ticked lesson/quiz boxes

---

## Reference docs (read these before deep work)

- **CLAUDE.md** — project guide, hard rules (#7 added today: run migrations after every prod deploy)
- **docs/DEPLOY.md** — prod deploy checklist (now includes "Run pending migrations" step)
- **docs/maria-pdf-review-2026-04-15.md** — 19-item checklist of Maria's PDF annotations + findings A/B/C
- **site/wp-content/plugins/hcp-mca-review-workflow/** — the new plugin where all this work lives

---

## What's on prod (deployed 2026-04-16)

| Layer | What |
|---|---|
| wp-config.php | Two `putenv()` lines for `AHPRA_PIEWS_USER` / `AHPRA_PIEWS_PASSWORD`. Hardcoded creds removed from theme. |
| Theme | `functions.php` — lesson-complete typo fix, quiz force-complete on eval submit, conditional redirect to eval after audit submit (with fallback button). |
| New plugin | `hcp-mca-review-workflow` — state machine, shortcodes, completion-guard, approval checkbox UI, form-lock, notifications, migrations runner, admin migrations page. |
| DB migrations applied | `2026-04-15-activity-homepage-buttons`, `2026-04-15-strip-action-116305-js-hack`, `2026-04-15-scrub-tbst-emails`, `2026-04-16-disable-action-116321` (all applied 2026-04-16 15:16:49 by Rob-Panwar). |
| Security cleanup | `functions.php-bk`, `footer.bak`, `header.bak` deleted from prod theme dir (credential leak plugged). |

## What's still open

| # | Task | Status | Notes |
|---|---|---|---|
| 16 | Fix quiz 116865 not marking complete on form 209 submit | **DONE 2026-04-16** | Fixed via `$force=true` on `learndash_process_mark_complete` in `functions.php`. |
| 17 | "Submission Approved" checkbox on user profile | **DONE 2026-04-16** | Shipped to prod. Triggers cert via guard-release path. |
| 18 | Decide on 5 already-auto-certed users (Alice + 4 internal) | pending Maria | Need her call: leave alone vs revoke for review. |
| 19 | Work through Maria's 19 PDF items 1-by-1 | in progress | Item 1 implicit-verified via test. Items 2-19 pending Maria's final copy for each. Use `maria-pdf-review-2026-04-15.md` as the working list. |
| 20 | Build Azure staging environment | **DONE 2026-04-16** | Live at `hcp-staging-app.azurewebsites.net`. Teardown/restart via `az mysql flexible-server stop/start` + `az webapp stop/start` to save cost between sessions. |
| 21 | Deploy to prod + run pending migrations | **DONE 2026-04-16** | All 4 migrations applied, plugin active, functions.php + wp-config.php updated. |
| 22 | AHPRA username sanitisation on registration form | deferred | Discovered via Van Vu (`MED 0001173547` — space) and Ian Petersen (`3338155476` — no prefix). Add regex `^[A-Z]{3}\d{10}$` validation + inline hint on registration field (client-side) AND `trim()`/`strtoupper()` + regex gate in AJAX handler near `functions.php:798` (server-side). Handler has dead-code / missing `set_ahpra_cookie()` issue — staging-validate before prod. |
| 23 | Rotate AHPRA PIEWS creds | pending client | Old password leaked via `functions.php-bk` publicly for months; burned. Rotation scheduled for next week per Rob. After rotation: update `putenv()` in prod `wp-config.php`. |
| 24 | Decide on Formidable form 161 final-page copy | pending Maria | 4 copy options proposed for the "Once you have completed all sections..." HTML field (frm_field id 12465). Will be shipped as a DB migration once Maria picks. |
| 25 | Maria's form 161 PDF items 2-19 | pending Maria | Volume copy/UX tweaks from `maria-pdf-review-2026-04-15.md`. Work through as a batch once she's reviewed. |
| 26 | AHPRA API audit: observability + verify it's actually gating access + leverage on custom dashboard | TODO | **Observability gap:** SOAP/curl calls to `ws2.ahpra.gov.au` currently log nothing — can't tell after the fact whether a call happened, passed, or failed. Add one-line request/response logging (non-PII) to both `check_if_aphra_exists_soap` (~`functions.php:828`) and `check_if_aphra_exists_curl` (~`functions.php:896`). **Behavioural question:** Rob suspects the API may not actually be enforcing anything — worth tracing the full registration flow to confirm gating logic (AJAX on field-blur + submit-time check at ~`functions.php:1065` have different code paths; handler returns HTTP 422 even on success per known dead-code bug). **Future reuse:** AHPRA response payload includes practitioner details (name, profession, registration status) that could pre-fill audit form fields or auto-populate provider info on the custom admin dashboard, reducing typing burden for HCPs. |
| 27 | Custom admin dashboard for user field edits (RACGP number, etc.) | TODO | Current pain: editing a user's RACGP number requires opening the Formidable registration form (form 81 / form 129), which re-prompts for the user's PASSWORD — nonsensical UX when an admin is doing the edit. Build a simple admin screen under a Panwar Health menu that lets admins edit: RACGP number, AHPRA number (after sanitisation from #22), first/last name, email — all writing directly to `tbstwp_usermeta` + the relevant `frm_item_metas`, no password required. Related to #22 (AHPRA sanitisation) and #26 (AHPRA lookup could auto-populate these fields on lookup). |
| 28 | Manually update incorrect RACGP numbers in DB | TODO — this session | Stopgap until #27 ships. Rob will supply (user_login → correct RACGP number) pairs; updates go into `tbstwp_usermeta.meta_key='racgp_number'` (or wherever it's actually stored — verify first) AND the corresponding `frm_item_metas` rows for field 4273 on form 129. |

---

## Architectural anchors (don't re-derive these)

- **State machine:** `hcp_mca_get_state($user_id)` returns A / A2 / B / D — see plugin's `includes/state.php`
- **Approval storage:** usermeta keyed by `hcp_mca_approved_audit_entry_id` so resubmits invalidate prior approvals
- **Course-complete guard:** hooks `learndash_before_course_completed` priority 1, then `learndash_course_completed` priority 1, then `remove_all_actions` to silence cert-email + LearnDash Notifications listeners
- **Notification dedup:** usermeta `hcp_mca_review_email_sent_for_entry_id` = audit entry id when sent
- **Migrations system:** plugin/`migrations/YYYY-MM-DD-slug.php` returns `[description, up]`; runner tracks applied via `hcp_mca_migrations_run` option; admin page at Tools → HCP Migrations
- **Two LearnDash courses, easy to confuse:**
  - 95553 = **Online Learning Module** (Module 1) — auto-completes, no Maria
  - 111793 = **Mini Clinical Audit** (Module 2) — Maria approves, triggers cert

---

## How to resume tomorrow

1. Read this file.
2. Read the reference docs above (or skim — they cover specifics this file just summarises).
3. Pick a task from the queue. Task #17 is the highest-value next build. #19 is the volume work.
4. Re-create TaskList from the table above.
5. Carry on.

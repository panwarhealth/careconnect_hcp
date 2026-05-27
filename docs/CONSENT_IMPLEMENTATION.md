# Consent Remediation — Implementation Brief

Scoped 2026-05-19. Quote approved and sent. 52 hours across 6 deliverables.

**Background:** Only 19 of 1,366 HCPs have valid Spam Act opt-in. All marketing sends paused. This project fixes that.

---

## Scope and hours

| # | Deliverable | Hrs |
|---|---|---|
| 1 | Legal brief (done) | 20 |
| 2 | T&C + Privacy Policy pages on site | 2 |
| 3 | Consent pop-up + Account Settings page | 12 |
| 4 | Canonical registration form + T&C checkbox | 4 |
| 5 | T&C update email | 6 |
| 6 | Unbranded consent landing page | 8 |
| **Total** | | **52** |

Item 3 must be built before Item 6 — sequential dependency.

---

## Pop-up manager — cross-project architecture

A second quote (scoped 2026-05-19) adds a **CPD course driver pop-up** — a promotional modal to drive traffic to the Mini Clinical Audit (course 111793). The site will have two pop-ups that can fire for the same user. A pop-up manager coordinates them.

**Priority rules:**
1. Consent modal eligible (no row in `hcp_consent_log`) → show consent modal, skip CPD pop-up
2. CPD pop-up eligible (logged in + `hcp_popup_cpd_driver_shown` user meta not set) → show it
3. Neither eligible → show nothing

**No queuing needed.** "Park for next login" is automatic — consent modal fires, CPD pop-up is skipped. Next page load consent is resolved, CPD pop-up becomes eligible.

**CPD show-once:** `hcp_popup_cpd_driver_shown` user meta key with timestamp. Set on display, not on CTA click.

**When building Item 3:** don't wire the consent modal directly into `wp_footer` in isolation. Build the pop-up manager at the same time so future pop-ups slot in cleanly.

---

## Item 3 — Consent capture modal + Account Settings

### What it does
A blocking modal that fires on next login (or any page load) for any logged-in user with no row in `hcp_consent_log`. Single yes/no marketing consent decision. Once they respond, the modal never fires again.

### Decisions locked
- **Marketing consent only.** CPD/educational is operational — no consent needed, not in scope.
- **Blanket re-consent.** No grandfathering of the 47 users with old Form 129 consent flags. Everyone goes fresh.
- **Fires on every page load** for logged-in users with no audit log row. Simpler than hooking into the login event, same effect.
- **Not dismissible** without clicking Yes or No. Full blocking overlay, no ESC, no click-outside.
- **Button layout:** "Yes, keep me updated" on right, "No thanks" on left.
- **27 previously declined users** — no pre-seeding. They see the modal once, click No, a row is written. Done.

### Audit table: `hcp_consent_log`
Lives in `tbst-custom-report` plugin. Migration pattern same as MCA migrations.

| Column | Value |
|---|---|
| user_id | WP user ID |
| timestamp | datetime of response |
| ip_address | server-side, not JS |
| method | `in-portal-modal` / `account-settings` / `registration` |
| response | `opted-in` / `opted-out` / `tc-accepted` |
| tc_version | value of WP option `hcp_tc_version` at time of response |

`hcp_tc_version` WP option is set at go-live and updated whenever T&Cs change.

### Account Settings page
Custom wp-admin menu page (not buried in profile). Accessible via admin bar. Shows current preference, when it was set, and a button to change. Calls the same AJAX save endpoint as the modal with `method = account-settings`.

---

## Item 4 — Canonical registration form

### DB findings (verified 2026-05-19)
- Only 2 forms currently published: **Form 2** (main site) and **Form 129** (CPD gate).
- Both are lean — AHPRA, RACGP (optional), name, email, password. No address or specialty fields.
- Address/specialty on 1,370 existing users came from the old RCP form (now inactive) or sample order forms — not from current reg forms.
- AHPRA API returns name, gender, qualification, profession only. No address data.
- **Form 129 bug:** `return $errors` on line 1080 of `functions.php` inside `valdidate_aphra_register()` bypasses AHPRA validation entirely. Anyone can register through the CPD gate with a fake AHPRA number. This form is being retired so fix is moot.
- `pw_rcp_save_user_fields_on_register` is commented out at line 1859 — dead code, ignore it.

### What we're building
- Form 2 becomes the one canonical registration form, used everywhere.
- Add one mandatory T&C + Privacy Policy acceptance checkbox. Register button disabled until ticked (JS).
- On submission: write a row to `hcp_consent_log` (method = `registration`, response = `tc-accepted`).
- Marketing consent is NOT captured at registration — modal handles it on first login.
- Replace Form 129 shortcode with Form 2 shortcode wherever Form 129 is currently embedded.
- Clean up dead Form 129 branches from `frm_validate_entry` and `frm_after_create_entry` in `functions.php`.
- Remove CPD consent + marketing consent checkboxes that exist on Form 129 (form is retired).

---

## Item 5 — T&C update email

One-time transactional email to all eligible existing users. Deliberately unbranded — keeps it out of marketing territory under the Spam Act. Excludes the 27 users who have actively declined contact.

- HTML template in EDM style but minimal/plain
- Admin-triggered batch send from tbst-custom-report admin page
- CTA button links to `/consent-preferences` (the landing page from Item 6)
- Must be tested on staging — WP Mail SMTP Pro is deactivated locally

---

## Item 6 — Unbranded consent landing page

### URL
Generic — `/consent-preferences`. All users in the email batch get the same link. No tokenized URLs.

### Page template
New `page-blank.php` in child theme — no header, nav, or footer. `page-withoutnav.php` already exists but still pulls full header/footer, not suitable.

### Pop-up suppression
Add this page's slug to the exclusion array in `enqueue_login_popup_assets()` in `functions.php`. Same pattern as the existing login/register page exclusions.

### Three states

| State | What the page shows |
|---|---|
| Logged in, no consent record | Inline consent form (same yes/no content as modal, not rendered as a modal) |
| Logged in, consent already recorded | Inline confirmation — "preference already saved" + link to portal |
| Logged out | Inline login form component (reuses existing AJAX login markup) |

WP session cookie check via `is_user_logged_in()` — many HCPs clicking the email CTA will already have an active session and land straight on the consent form without seeing the login step.

After saving consent on this page: inline confirmation message + link back to portal. No redirect.

### Registration path
Not needed on this page. New users who need to register go through the normal reg flow → first login → modal fires. All paths converge:

- **New user** → reg form (T&C checkbox) → first login → modal
- **Existing user via email** → consent page → logged in or logs in inline → inline consent form
- **Existing user logs in normally** → modal fires if no audit record

---

## Sample order forms — noted, not in scope

Form 4 (Order Samples, 438 entries) and Form 55 (Order Samples, 1,009 entries) both capture address, phone, specialty, and practice name and have a `user_id` field. This is likely the source of address/specialty data in user meta for existing HCPs. Pre-existing data flow — nothing to touch in this project.

Note: all Formidable form actions in the DB have `frm_form_id = NULL` due to a broken action-to-form binding. Known issue — does not affect this project but relevant if you're ever digging into Formidable action behaviour.

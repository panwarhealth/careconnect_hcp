# CAPH0150 — MCA pop-up (home page)

Supersedes `CPD_DRIVER_POPUP.md` (CAPH scope from May 2026). Two rules changed: the
copy now promotes the whole activity rather than the audit alone, and the pop-up
returns on every new visit instead of showing once ever.

## Purpose

Drive HCPs who have not touched the MCA to the Rectogesic educational activity
(online learning module 95553 + mini clinical audit 111793).

## Copy (client v1.1, 2026-08-06)

```
Header:    GPs: EARN UP TO 8.5 CPD HOURS

Body:      Live Now: A free, RACGP-accredited, two-part educational activity to
           improve the identification and management of anal fissures in general
           practice:

           <<icon>> Online Learning Module + <<icon>> Mini Clinical Audit

           Progress at your own pace and maximise hours across all CPD categories

           <<RACGP accreditation logo with hours>>

CTA:       SEE FULL ACTIVITY

Footnote:  This educational activity is provided by Panwar Health, who are an
           authorised Provider of education under the RACGP CPD program, and
           sponsored by Care Pharmaceuticals.
```

Note this copy names the condition, unlike the May draft which was brand-only.

## Who sees it

Show only to users with no trace anywhere in the MCA flow. Any one of these is a
"leave them alone" signal:

- LearnDash activity against course 95553 or 111793
- A Formidable form 161 entry (audit submitted, possibly awaiting review)
- `course_completed_111793` user meta

Checked in that order, cheapest first. A user who fails the test is never eligible
again, so the result can be cached in user meta rather than re-queried per page load.

Never fires on the MCA pages themselves.

## When it shows

Every new visit. Dismissing hides it for the current visit only; the next visit
shows it again. Tracking is a session cookie, not user meta, so there is no
permanent "shown" flag to reset when the campaign is re-run.

## Pop-up manager

The pop-up does not wire itself into `wp_footer`. A manager owns that hook:

1. Each pop-up registers an eligibility callback, a priority, and a renderer.
2. On `wp_footer` the manager picks the highest-priority eligible pop-up.
3. It renders that one, and nothing else.

Today only this pop-up is registered, so it always wins. The consent modal, when
it is built, registers at a higher priority and this one stands down automatically
with no change on its side. Registration carries a blocking flag: the consent modal
blocks the page (no dismiss, no ESC), this one does not.

## Assets still needed from the client

- RACGP accreditation logo with hours
- The two `<<icon>>` marks for module and audit

The only image in the supplied document is the Panwar Health logo from the header.

## Open questions

- CTA target. Course 111793 is `/courses/mini-clinical-audit/`, but "SEE FULL
  ACTIVITY" reads like the activity homepage (111281) which covers both parts.
- Logged-out visitors. Eligibility cannot be evaluated without a login, so either
  they all see it or none do.
- Home page only, as the document title implies, or site-wide.
- What counts as a "new visit": browser session, or a fixed idle window such as the
  30 minutes GA4 uses.

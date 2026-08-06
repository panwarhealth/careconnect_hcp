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

Logged-out visitors never see it. Eligibility cannot be evaluated without a login,
and a promotional modal in front of a stranger is the wrong first impression.

## Where it shows

Site-wide, not home page only. The home page carries 4.6 landing sessions a day
against 34.2 site-wide (GA4, 90 days to 2026-08-05), so a home-page-only pop-up
would reach one or two eligible people a day. The same 90 days show no dominant
entry point: home page 12% of landings, the MCA landing page another 12%, and
`/register` 14%, with 47% unattributed.

Everywhere on the front end, whatever the visitor's entry point: home page, blog
index and articles, individual video pages, resources, the Tools & Videos hub,
brand and product pages, campaign landing pages, general pages. The manager hooks
`wp_footer`, so this is the default rather than a page list to maintain.

Only two exclusions:

- Any MCA page: activity homepage, courses 95553 and 111793, their lessons and
  quizzes, and the audit form. No point promoting the page they are already on.
- `wp-admin`

Client decision, taken with the trade-offs on the table: a modal will sometimes
land over a campaign landing page that traffic was paid to reach, and over a video
someone has just started. The dwell delay below is what keeps that tolerable.

## When it shows

Every new visit. Dismissing hides it for the current visit only; the next visit
shows it again. Tracking is a session cookie, not user meta, so there is no
permanent "shown" flag to reset when the campaign is re-run.

Never on first paint. Fire after a short dwell (3 seconds) or once the reader has
scrolled a quarter of the page, whichever comes first, so the page has a chance to
be read before the modal lands.

## Pop-up manager

The pop-up does not wire itself into `wp_footer`. A manager owns that hook:

1. Each pop-up registers an eligibility callback, a priority, and a renderer.
2. On `wp_footer` the manager picks the highest-priority eligible pop-up.
3. It renders that one, and nothing else.

Today only this pop-up is registered, so it always wins. The consent modal, when
it is built, registers at a higher priority and this one stands down automatically
with no change on its side. Registration carries a blocking flag: the consent modal
blocks the page (no dismiss, no ESC), this one does not.

## CTA target

The activity homepage, which covers both parts, not the audit course on its own.

The activity homepage has two URLs and the site redirects by login state: members
land on `/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/`
and logged-out visitors are sent to `/anal-fissures-breaking-the-cycle-and-the-stigma-landing/`.
The pop-up is logged-in only, so link the member URL and let the redirect handle
any edge case.

## Assets

The RACGP logo already exists in uploads from December 2025, no client supply
needed: `/wp-content/uploads/2025/12/RACGP-logo-for-Total-Activity.png` (768x512
and 150x150 variants, plus .webp). The set also holds Learning-Module-Only and
Clinical-Audit-Only versions; this pop-up promotes both parts, so Total Activity is
the right one. Confirm it carries the 8.5 hours figure the copy quotes, as the set
was produced when the activity was split.

Still outstanding: the two `<<icon>>` marks for module and audit. The only image in
the supplied document is the Panwar Health logo from its header.

## Open questions

- What counts as a "new visit": browser session, or a fixed idle window such as the
  30 minutes GA4 uses.

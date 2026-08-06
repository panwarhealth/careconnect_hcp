# CAPH0150 — MCA pop-up

Supersedes `CPD_DRIVER_POPUP.md` (scoped May 2026). Three rules changed since that
brief: the copy promotes the whole activity rather than the audit alone, the pop-up
returns on every new visit instead of showing once ever, and it shows to logged-out
visitors as well as members.

## Purpose

Drive people to the Rectogesic educational activity (online learning module 95553 +
mini clinical audit 111793) without pestering anyone already doing it.

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

This copy names the condition, unlike the May draft which was brand-only.

## Who sees it

**Logged-in users:** only those with no trace anywhere in the MCA flow. Any one of
these is a "leave them alone" signal:

- LearnDash activity against course 95553 or 111793
- A Formidable form 161 entry (audit submitted, possibly awaiting review)
- `course_completed_111793` user meta

Checked in that order, cheapest first. Someone who fails the test is never eligible
again, so the result can be cached in user meta rather than re-queried per page load.

**Logged-out visitors:** always eligible. There is no way to tell whether a stranger
has already done the activity, so some will see it who have. Accepted trade-off: the
eDM traffic this is meant to capture arrives logged-out, particularly on campaign
pages that are ungated for the duration of a send.

## Where it shows

Content pages only, wherever the visitor entered from. That covers the home page,
blog index and articles, individual video pages, the Tools & Videos hub, resources,
brand and product pages, and campaign landing pages such as `/clinical-bites/`.

Not on operational or functional pages, where someone is mid-task rather than
reading:

- Registration and login
- Account and profile pages
- Order samples and any other multi-page Formidable flow
- Contact and search results
- `wp-admin`
- Any MCA page: the activity homepage, courses 95553 and 111793, their lessons and
  quizzes, and the audit form. No point promoting the page they are on.

Sizing, from GA4 over the 90 days to 2026-08-05: the site runs 34.2 sessions a day
against 4.6 landing on the home page, and no single entry point dominates (home page
12% of landings, MCA landing page 12%, `/register` 14%, 47% unattributed). Site-wide
rather than home-page-only is the difference between reaching most visitors and
reaching one or two a day.

## When it shows

Every new visit. Dismissing hides it for that visit only; the next visit shows it
again. Tracking is a session cookie, so there is no permanent "shown" flag to clear
when the campaign is re-run.

Never on first paint. Fire after a 3 second dwell or once the reader passes a
quarter of the page, whichever comes first.

## Architecture

A new `hcp-popups` plugin, not theme code, so the manager outlives this campaign.

**Manager.** Owns the `wp_footer` hook. Each pop-up registers an ID, a priority, an
eligibility callback, a renderer, and a blocking flag. On each page load the manager
walks the queue in priority order, takes the first eligible pop-up, renders it, and
stops. One pop-up per page load, never two.

**Queue.** Priority ordering is what makes deferral automatic. The consent modal,
when built, registers above this one and blocks the page (no dismiss, no ESC); this
one stands down and becomes eligible again on a later load, with no change to its own
code. Nothing needs to be re-queued or parked.

## Analytics

Two independent records, because GA4 alone cannot be trusted for a count.

**Server-side**, in a plugin table. One row per impression: pop-up ID, user ID (null
when logged out), session ID, page path, timestamp, and the outcome once known
(dismissed or CTA clicked). This is the answer to "how many times did it appear,
where, and when", queryable directly and unaffected by ad blockers or consent tools.

**GA4 events**, for funnel analysis alongside the rest of the site:

| Event | Fires on |
|---|---|
| `popup_shown` | render |
| `popup_dismissed` | dismiss or close |
| `popup_cta_click` | CTA |

Each carries `popup_id` and `page_path`. Register `popup_id` as a custom dimension in
GA4 before launch, or the events land but cannot be broken down by pop-up later.

Expect the server-side count to exceed the GA4 count. The gap is blocked or dropped
beacons, and it is normal.

## CTA target

The activity homepage, which covers both parts, not the audit course alone.

That page has two URLs and the site redirects by login state: members land on
`/anal-fissures-breaking-the-cycle-and-the-stigma-completion-activity-homepage/`,
logged-out visitors are sent to `/anal-fissures-breaking-the-cycle-and-the-stigma-landing/`.
Link the member URL and let the redirect sort out the rest.

## Assets

The RACGP logo already exists in uploads from December 2025, no client supply needed:
`/wp-content/uploads/2025/12/RACGP-logo-for-Total-Activity.png` (768x512 and 150x150
variants, plus .webp). The set also holds Learning-Module-Only and Clinical-Audit-Only
versions; this pop-up promotes both parts, so Total Activity is the right one. Confirm
it carries the 8.5 hours figure the copy quotes, as the set was produced when the
activity was split.

Still outstanding: the two `<<icon>>` marks for module and audit. The only image in the
supplied document is the Panwar Health logo from its header.

## Open questions

- What counts as a "new visit": browser session, or a fixed idle window such as the 30
  minutes GA4 uses.

# Care Connect measurement plan (CAPH0118 Analytics)

What the site measures, where each number lives, and how to answer the usual report questions. Code: `site/wp-content/plugins/hcp-analytics/`.

## Principles

- **GA4 stays anonymous.** No user ID, no name, email or AHPRA number goes to Google or Microsoft. GA4 only knows whether a visitor is logged in and their profession code (MED, NMW, PHA ...). This matches the published privacy policy.
- **Per-person answers come from our own database**, never from GA4: the engagement table and sign-up source (below).
- **No Google Tag Manager.** Everything is in code, versioned and tested before it goes live.
- **Legacy events keep their names and firing rules**, so quarterly series continue unbroken.

## Switch

One option controls everything: `wp option update hcp_analytics_mode off|preview|live`.

| Mode | Who gets the new tracking | Use |
|---|---|---|
| `off` | Nobody (site behaves as before; other plugins still send their own events) | Default after install, rollback |
| `preview` | Administrators, and any browser that opened a page with `?hcp_analytics_preview=1` (`=0` turns it off). GA4 events carry `debug_mode` so they show in DebugView. They still count in normal reports: while the old GTM snippet is on the page a previewer's page views count twice and a test registration counts as a sign-up, so note the test dates. | Testing on production |
| `live` | Everyone | Normal running |

## GA4 (property 306115293, `G-ZM1QH0ZTGW`)

### Context sent with every event (gtag `set`)

| Parameter | Values | Custom dimension |
|---|---|---|
| `logged_in` | `hcp`, `staff`, `no` | Logged in |
| `profession` | AHPRA profession code from the stored `ahpra_id` (MED, NMW, PHA, OPT ...), `unknown`, `none` | Profession |
| `page_type` | `home`, `article`, `resource`, `video`, `course`, `brand`, `page`, `pdf_redirect`, `listing`, `search`, `not_found`, `other` | Page type |
| `content_section` | `home`, `articles`, `resources`, `videos`, `cpd`, `samples`, `tools`, `account`, `brands`, `info`, `search`, `other` | Content section |
| `therapy_area` | first `therapy_area` term slug of the page, `none` | Therapy area |
| `brand` | first `brand` term slug of the page, `none` | Brand |

Page sections for WordPress pages are mapped by path in `hcp_analytics_page_sections()` (`includes/context.php`). New pages land in `other` until added there.

Staff accounts send `logged_in = staff`: filter them out of HCP reporting with `logged_in != staff`.

### Events

| Event | Fires | Params | Notes |
|---|---|---|---|
| `page_view` | every page | context | from gtag config |
| `allPages` | every page | `full_url` | legacy, same rule as GTM |
| `15 Second Page View` | once per page after 15 s | | legacy, used by `article_depth.py` |
| `login` | page load where the URL contains `welcome` | | legacy, used for the logged-in proxy |
| `Form_submissions` | any form submit on a URL containing `register` | `page`, `Form` | legacy, used by `qr_codes.py`. `Form` is now the form's id/name (GTM sent the element). |
| `conversion_event_signup` | a registration that succeeds (Formidable's `frmBeforeFormRedirect` / `frmFormComplete`) | | **rule changed at go-live.** GTM fired it on submits Formidable's AJAX had not cancelled (39 vs 110 `Form_submissions` in the 90 days to 17 Nov 2026), which cannot be reproduced. Do not compare across go-live; for sign-ups use the database (sign-up source). |
| `article_scroll` | articles only, at 25/50/75/100% of the article body (`.entry-content`) | `percent_scrolled` | new; GA4's built-in `scroll` (90% of the whole page) is unchanged |
| GA4 enhanced measurement | `scroll` (90%), outbound `click`, `file_download`, `form_start/submit` | | stream settings, unchanged |
| plugin events | pop-ups, videos, quiz, PDF redirect page views | | unchanged; now share the one gtag and the one measurement ID |

Dropped from the old container: Universal Analytics + ISP lookup (UA shut down 2024), Google Ads click linker (no paid ads), sample-form event (its rule never matched), 9 paused tags.

### Custom dimensions to register (event scope)

`logged_in`, `profession`, `page_type`, `content_section`, `therapy_area`, `brand`: **registered 18 Nov 2026** (GA4 fills them from that day). `percent_scrolled` is not registered: GA4 has a built-in "Percent scrolled" dimension (`percentScrolled`); confirm in the preview that it reports `article_scroll` values, and register a custom one only if it does not.

## Microsoft Clarity (project `zfe87ouule`)

Loaded with the same six context values as Clarity tags (filter recordings and heatmaps by them). Form fields are masked by Clarity's default (Balanced). Pages in the `account`, `samples` and `cpd` sections are masked in full (`data-clarity-mask` on `<html>`). The header's "Welcome {first name}" greeting is masked on every page (`template-parts/header4.php`); any new element that prints a name or AHPRA number needs the same attribute. Check in the Clarity dashboard that masking is **Balanced** or stricter.

## Our database

### Engagement table `{prefix}hcp_engagement`

One row per HCP x page x campaign: `user_id`, `object_id`, `utm_source`, `utm_medium`, `utm_campaign`, `views`, `first_seen`, `last_seen`. Written for logged-in, non-staff users on views of posts, pages, resources, videos, brands and LearnDash content, and on PDF clicks (recorded against the resource whose `download` field holds the PDF, else the PDF attachment). Therapy areas and brands are read from the page's terms at query time (`hcp_analytics_engagement_profile()`), never copied.

### Campaign of the visit

A tagged link's `utm_source`, `utm_medium`, `utm_campaign` are kept in the `hcp_visit_src` cookie for the visit: 30 minutes after the last page, the same rule as a GA4 session, so they still apply after the HCP logs in or signs up later in the visit but not days later. A later tagged link replaces them.

### Sign-up source

New accounts get user meta `hcp_signup_utm_source`, `hcp_signup_utm_medium`, `hcp_signup_utm_campaign` (empty strings = signed up with no campaign; no meta = signed up before tracking went live).

### What converted them (journey before sign-up)

While a visitor is not logged in, the `hcp_journey` cookie (30 days) keeps their first visit, landing page, first campaign and last 10 pages viewed (repeat views and 404s skipped). At sign-up it is saved to the account and the cookie cleared:

| User meta | Meaning |
|---|---|
| `hcp_signup_first_visit` | first page view on this device, site time |
| `hcp_signup_landing_page` | path of that first page |
| `hcp_signup_first_utm_source` / `_medium` / `_campaign` | first campaign in the journey (the sign-up source above is the campaign of the sign-up visit itself) |
| `hcp_signup_pages` | JSON list of the last 10 page paths before sign-up |

Per device: a visitor who browsed on a phone and signed up on a laptop shows only the laptop journey. No journey meta = no cookie (cleared, blocked, or signed up before 19 Nov 2026).

## Report questions and where the answer is

| Question | Source |
|---|---|
| Sign-ups by eDM / campaign | `usermeta hcp_signup_utm_*` joined to `users.user_registered` |
| What converts visitors: landing page, first campaign, pages before sign-up | `usermeta hcp_signup_landing_page`, `hcp_signup_first_utm_*`, `hcp_signup_pages` |
| Which HCPs engaged with which eDM, and what they viewed | `hcp_engagement` where `utm_campaign = ...` |
| What did HCPs from an eDM do next (audit, samples, resources) | `hcp_engagement` rows for those users after `first_seen` |
| An HCP's interests (therapy areas, brands) | `hcp_analytics_engagement_profile( $user_id )` |
| Page views split logged in vs anonymous, by profession | GA4 with the `logged_in` / `profession` dimensions |
| Returning visitors and visit frequency | GA4 `newVsReturning`, `sessionsPerUser` (per device; one HCP on two devices counts twice) |
| Journeys between sections | GA4 `content_section` / `page_type` by session |
| How far articles are read | GA4 `article_scroll` by `percent_scrolled` and page |
| Where attention drops, visually | Clarity heatmaps and recordings |

Example queries:

```sql
-- Sign-ups by campaign since go-live
SELECT COALESCE(NULLIF(c.meta_value, ''), '(no campaign)') AS campaign, COUNT(*) AS signups
FROM tbstwp_users u
JOIN tbstwp_usermeta c ON c.user_id = u.ID AND c.meta_key = 'hcp_signup_utm_campaign'
GROUP BY campaign ORDER BY signups DESC;

-- Landing pages of new sign-ups
SELECT l.meta_value AS landing_page, COUNT(*) AS signups
FROM tbstwp_usermeta l WHERE l.meta_key = 'hcp_signup_landing_page'
GROUP BY landing_page ORDER BY signups DESC;

-- HCPs who engaged from one eDM, and what they opened
SELECT e.user_id, p.post_type, p.post_title, e.views, e.first_seen
FROM tbstwp_hcp_engagement e JOIN tbstwp_posts p ON p.ID = e.object_id
WHERE e.utm_campaign = 'caph0148'
ORDER BY e.user_id, e.first_seen;
```

## Saved GA4 explorations (build once in the GA4 screen; Google offers no API for these)

1. **Section journeys:** Explore, Path exploration, starting point = event `page_view`, node type = `Content section`. Save as "Care Connect: section journeys".
2. **Sign-up funnel:** Explore, Funnel exploration, steps: `page_view` with `content_section = home` or any landing, then `page_view` on `/register/`, then `conversion_event_signup`. Breakdown: `Session campaign`. Save as "Care Connect: sign-up funnel".
3. **Logged in vs anonymous:** Explore, Free form, rows `Content section`, columns `Logged in`, metric `Views`. Save as "Care Connect: logged in by section".

## Deploy order

hcp-popups, hcp-videos, the theme's quiz analytics and the PDF redirect template take the GA4 ID from hcp-analytics; without it their tracking stops silently. So: upload hcp-analytics and **activate it first** (mode `off`), then upload the other changed files. hcp-popups and hcp-videos declare `Requires Plugins: hcp-analytics`, so wp-admin will not let anyone deactivate hcp-analytics while they are active. WP-CLI ignores that, so never `wp plugin deactivate hcp-analytics` on production; switch it off with the mode option instead.

## Go-live and rollback (production)

Go-live, after the preview checks pass and with Rob's OK:
1. Register the custom dimensions above.
2. In one step, with nothing in between: `wp option update hcp_analytics_mode live` **and** remove the old GTM snippet (`GTM-NX54ZZP`) from the Header and Footer plugin's head and body boxes (back up the `hefo` option first). Leaving both running double-counts page views.
3. Check GA4 Realtime and Clarity for live data.

Rollback: `wp option update hcp_analytics_mode off` and restore the `hefo` option. The engagement table and sign-up meta are kept.

## Preview test checklist

- Page source (preview on) has one gtag loader, `gtag('set', {...})` with the right context, and the Clarity loader.
- GA4 DebugView shows `page_view` with `logged_in`, `profession`, `page_type`, `content_section`; `allPages`; `15 Second Page View` after 15 s; `login` on `/welcome/`; `article_scroll` 25 to 100 on an article.
- Logged in as a test HCP from a tagged link: a row appears in `hcp_engagement` with the campaign; a PDF click adds a row against the resource.
- A real test registration through `/register/` from a tagged link: `Form_submissions` then `conversion_event_signup` in DebugView, and the new account has `hcp_signup_utm_*` meta. Delete the test account afterwards.
- Article scroll: `article_scroll` 100 fires at the end of the article text, before the footer.
- Clarity shows the session, with profile, sample-order and CPD pages masked and the header greeting masked on every page.
- With preview off, the page source has none of the above and pop-up / video events still arrive.

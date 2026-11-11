"""JAS26 media quality slides: non-human clicks, publisher accuracy, publisher value, asset-type value.

    reports/.venv/bin/python reports/slides/build_jas26_media_slides.py [out.pptx]

Reads the CSVs written by reports/ga4/jas26_media_quality.py.
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.chart.data import CategoryChartData
from pptx.enum.chart import XL_CHART_TYPE, XL_LEGEND_POSITION, XL_LABEL_POSITION
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (AMBER, GREY, INK, LIGHTBAR, NAVY, RED, SKY, TEAL, axis_quiet, bullets, csv_rows,  # noqa: E402
                                label, new_slide, no_title, open_template, rect, source, stat_card, style_chart,
                                subtitle, table, text, title)

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 publisher and asset analysis - Rob (review).pptx")
ORDER = ["IQVIA", "AJP", "Insight", "Medicine Today", "The Medical Republic", "mivision"]
REPORTS = {"IQVIA": "Unique clicks + raw click log", "AJP": "Unique clicks", "Insight": "Total clicks",
           "Medicine Today": "Unique clickers (each reader once)", "The Medical Republic": "Unique clicks per link",
           "mivision": "All clicks, 4 newsletters†"}
TRACKER = {"IQVIA": "Salesforce redirect", "AJP": "Mailchimp redirect", "Insight": "Own redirect page",
           "Medicine Today": "Mailchimp redirect", "The Medical Republic": "Not visible to us",
           "mivision": "Campaign Monitor redirect"}


def n(v) -> int:
    return int(v) if str(v).strip() else 0


def money(v) -> str:
    return f"${n(v):,}" if str(v).strip() else "–"


def pubs() -> dict[str, dict]:
    return {r["publisher"]: r for r in csv_rows("jas26_bot_by_publisher.csv")}


def slide_bot_types(prs, layout):
    p = pubs()
    s = new_slide(prs, layout)
    tot = sum(n(r["ga4_sessions"]) for r in p.values())
    human = sum(n(r["human_sessions"]) for r in p.values())
    burst, nopage = (sum(n(r[k]) for r in p.values()) for k in ("burst", "no_page"))
    ex = [r for k, r in p.items() if k != "IQVIA"]
    ex_tot, ex_human = sum(n(r["ga4_sessions"]) for r in ex), sum(n(r["human_sessions"]) for r in ex)
    title(s, f"NON-HUMAN CLICKS – {(tot - human) / tot * 100:.0f}% of JAS eDM sessions were automated; "
             f"{p['IQVIA']['automated_share']} of IQVIA's sessions were link scanners")
    subtitle(s, f"Across 9 publisher sends, {tot - human:,} of {tot:,} GA4 sessions came from software, not readers. "
                f"Without IQVIA the rate is {(ex_tot - ex_human) / ex_tot * 100:.0f}%.", italic=True, size=12)

    label(s, 0.42, 1.95, 7.6, "GA4 SESSIONS BY PUBLISHER: HUMAN VS AUTOMATED")
    cd = CategoryChartData()
    cats = list(reversed(ORDER))
    cd.categories = [f"{c} ({p[c]['automated_share']})" for c in cats]
    cd.add_series("Proven human", [n(p[c]["proven_human_sessions"]) for c in cats])
    cd.add_series("Unprovable (counted as human)", [n(p[c]["unprovable_sessions"]) for c in cats])
    cd.add_series("Burst link checks", [n(p[c]["burst"]) for c in cats])
    cd.add_series("No page loaded", [n(p[c]["no_page"]) for c in cats])
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_STACKED, Inches(0.35), Inches(2.2), Inches(7.7), Inches(3.75), cd)
    ch = gf.chart
    style_chart(ch, 10)
    no_title(ch)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.BOTTOM
    ch.legend.include_in_layout = False
    ch.legend.font.size = Pt(9.5)
    ch.plots[0].gap_width = 45
    for ser, col in zip(ch.series, (TEAL, SKY, RED, LIGHTBAR)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
    axis_quiet(ch.value_axis, gridlines=True, size=9)
    axis_quiet(ch.category_axis, size=10)

    label(s, 8.35, 1.95, 4.6, "THE TWO KINDS OF NON-HUMAN CLICK")
    cards = [
        ("Burst link checks", f"{burst:,}",
         "10+ arrivals in one minute. Email security software clicks every link as mail lands [1][2]. IQVIA's arrivals came in bursts "
         "about every 2 hours (8 Sep 08:00, 09:52, 11:52…), each hitting a different set of subscribers, which fits batch delivery being scanned on arrival [3]."),
        ("No page loaded", f"{nopage:,}", "A session started but no page was viewed: a fetch without a full browser load."),
        ("Counted as human", f"{sum(n(r['human_sessions']) for r in p.values()):,}",
         f"Everything else, wherever it came from: {sum(n(r['proven_human_sessions']) for r in p.values()):,} show a person (phone, 3s+ reading, several pages, "
         f"or a human-only action like dismissing the pop-up or downloading a PDF) and {sum(n(r['unprovable_sessions']) for r in p.values()):,} are unprovable "
         "and counted as human. Every burst-minute session was checked the same way. Location is never a test."),
    ]
    y = 2.2
    for cap, val, note in cards:
        rect(s, 8.35, y, 4.55, 1.22)
        text(s, 8.55, y + 0.1, 2.9, 0.3, cap, size=11.5, bold=True, color=INK)
        text(s, 11.3, y + 0.04, 1.45, 0.4, val, size=20, bold=True, color=TEAL if cap == "Counted as human" else RED, align=PP_ALIGN.RIGHT)
        text(s, 8.55, y + 0.42, 4.2, 0.8, note, size=9.5, color=GREY)
        y += 1.3

    iq = {r["list"]: r for r in csv_rows("jas26_iqvia_click_audit.csv")}
    tot_c = sum(n(r["unique_clickers"]) for r in iq.values()); soft = sum(n(r["software_behaviour"]) for r in iq.values())
    refetch = sum(n(r.get("clickers_same_link_re_fetched_20_90s_later", 0)) for r in iq.values())
    bullets(s, 0.42, 6.12, 12.5, 0.35, [
        f"IQVIA's own click log, read per person: {soft} of {tot_c} clickers ({soft / tot_c * 100:.0f}%) behave like software ({refetch} re-fetched the same link 20–90s later, "
        f"others hit the unsubscribe link or 4+ links); the other {tot_c - soft} could be people and are counted as such",
    ], size=10.5, gap=2)
    text(s, 0.32, 6.5, 12.4, 0.34, "References: [1] Suped, 'Why are email click rates inflated'. [2] Braze, 'Bot or not: understanding email bot clicks'; "
         "Maestra, 'Filtering out fake clicks'. [3] Univ. of Colorado, 'Throttling your email in Marketing Cloud' (batch sending is a Salesforce Marketing Cloud feature; IQVIA's schedule unconfirmed).",
         size=8, color=GREY)
    source(s, "Source: GA4 session_start events per send, send day to 30 Sep 2026, by minute and landing page; shares applied to GA4 sessions. "
              "Burst = 10+ arrivals from one send in one minute, verified by browser mix (desktop-only, no phones). IQVIA raw click export (13 Oct), times converted from US Central to Sydney.")
    s.notes_slide.notes_text_frame.text = "\n".join([
        "References",
        "[1] https://www.suped.com/knowledge/email-deliverability/troubleshooting/why-are-email-click-rates-inflated-and-how-to-solve-the-issue",
        "    https://www.suped.com/learn/email-deliverability/do-email-security-software-solutions-click-hyperlinks-in-emails",
        "    https://kpplaybook.com/resources/why-your-email-click-rate-is-wrong/",
        "[2] https://www.braze.com/resources/articles/bot-or-not-understanding-email-bot-clicks.md",
        "    https://help.maestra.io/docs/filtering-out-fake-clicks-keeping-your-click-rate-clean",
        "    https://www.mindbaz.com/?p=22944",
        "[3] https://www.cu.edu/blog/ecomm-wiki/throttling-your-email-marketing-cloud",
        "Mailchimp on scanner clicks: https://mailchimp.com/resources/bot-clicks-in-email-marketing/",
        "",
        "Batch evidence: each 2-hourly burst in IQVIA's click log hit a different set of subscribers (almost none repeat), "
        "so these are new deliveries being scanned, not re-checks of earlier emails.",
    ])


def slide_accuracy(prs, layout):
    p = pubs()
    s = new_slide(prs, layout)
    title(s, "WHICH PUBLISHERS SEND FAKE CLICKS – IQVIA and AJP most; Medicine Today's reported clicks are the most real")
    subtitle(s, "Five of the six publishers visibly route clicks through a tracking redirect (a 'middle man'; TMR's method isn't visible to us), so that alone "
                "doesn't make reports accurate. What separates them is what they count and whose inboxes they reach.", italic=True, size=12)
    rows = [["Publisher", "How clicks reach us", "What they report", "Publisher clicks", "GA4 sessions",
             "Automated share", "Real sessions", "Real ÷ reported"]]
    color = {}
    for i, k in enumerate(ORDER, 1):
        r = p[k]
        rows.append([k, TRACKER[k], REPORTS[k], f"{n(r['publisher_clicks']):,}", f"{n(r['ga4_sessions']):,}",
                     r["automated_share"], f"{n(r['human_sessions']):,}", r["human_vs_publisher_clicks"]])
        auto = int(r["automated_share"].rstrip("%"))
        color[(i, 5)] = RED if auto >= 40 else INK
        acc = int(r["human_vs_publisher_clicks"].rstrip("%"))
        color[(i, 7)] = TEAL if acc >= 60 else RED if acc < 35 else INK
    table(s, 0.42, 1.95, 12.5, [1.75, 2.3, 2.45, 1.15, 1.05, 1.15, 1.1, 1.15], rows, row_h=0.4, header_h=0.5,
          size=10.5, header_size=10, color_cells=color, bold_cols=(0, 7),
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.LEFT] + [PP_ALIGN.RIGHT] * 5)

    rect(s, 0.42, 5.0, 6.1, 1.85)
    text(s, 0.62, 5.1, 5.7, 0.3, "IS THE MIDDLE-MAN THEORY RIGHT?", size=11.5, bold=True, color=GREY)
    acc = {k: int(p[k]["human_vs_publisher_clicks"].rstrip("%")) for k in ORDER}
    lo, hi = min(acc[k] for k in ("IQVIA", "AJP", "mivision")), max(acc[k] for k in ("IQVIA", "AJP", "mivision"))
    bullets(s, 0.62, 5.42, 5.75, 1.4, [
        f"Partly. Insight routes clicks through its own page, and {acc['Insight']}% of its clicks show up as real sessions",
        f"But IQVIA, AJP and mivision also use a redirect (their email platform's) and come in at {lo}–{hi}%",
        "The redirect counts whatever hits it, scanner or reader; only Medicine Today's report counts each reader once (so it can top 100%)",
    ], size=10.5, gap=2)
    rect(s, 6.8, 5.0, 6.13, 1.85)
    text(s, 7.0, 5.1, 5.7, 0.3, "WHAT THIS MEANS", size=11.5, bold=True, color=GREY)
    bullets(s, 7.0, 5.42, 5.8, 1.4, [
        "Judge publishers on real sessions, not their click counts",
        "No publisher we use filters scanner clicks (Mailchimp, used by Medicine Today and AJP, says scans register as clicks). Ask each for a per-reader count and the raw click log",
        "Raw click logs (like IQVIA's) let us audit a publisher's numbers; request them as standard",
    ], size=10.5, gap=2)
    source(s, "Source: publisher reports + GA4, send day to 30 Sep 2026. Real sessions = GA4 sessions not in a burst minute and with a page view (location is not a test). "
              "Tracker evidence: IQVIA click log (click.mkthealthcare.com), mivision web version (createsend1.com), Mailchimp report format (MT) and auto-tags (AJP), "
              "Insight referrer (transport.primecreativemedia.com.au). †mivision clicks cover all links in 4 newsletters, not only Zaditen.")


def slide_value(prs, layout):
    p = pubs()
    sends = {r["send"]: r for r in csv_rows("jas26_bot_by_send.csv")}
    s = new_slide(prs, layout)
    title(s, "PUBLISHER VALUE – Medicine Today beat The Medical Republic on the same content for less; "
             "Insight was the cheapest real traffic")
    rows = [["Publisher", "Sends", "Media cost", "Delivered", "Real sessions", "Per 1,000 delivered", "Cost per real session",
             "Sign-ups (GA4-tagged)"]]
    order = sorted(ORDER, key=lambda k: n(p[k]["cost_per_human_session"]) or 10**6)
    for k in order:
        r = p[k]
        rows.append([k, r["sends"], money(r["media_cost"]) if r["media_cost"] else "in CPD package*", f"{n(r['delivered']):,}",
                     f"{n(r['human_sessions']):,}", r["human_per_1000_delivered"],
                     money(r["cost_per_human_session"]), r["ga4_tagged_signups"]])
    label(s, 0.42, 1.5, 7.3, "ALL JAS SENDS, BY PUBLISHER")
    table(s, 0.42, 1.8, 7.45, [1.8, 0.7, 1.25, 0.95, 0.95, 1.0, 1.05, 1.0], rows, row_h=0.38, header_h=0.52,
          size=10, header_size=9.5, bold_cols=(0, 6))

    label(s, 8.15, 1.5, 4.8, "HEAD TO HEAD: SAME eDM, SAME MONTH")
    h2h = [["Send", "Cost", "Real sessions", "Cost each"]]
    for lab, short in (("MT Solus eDM - Hydralyte Clinical Bites", "Hydralyte · MT"),
                       ("TMR Solus eDM - Hydralyte Clinical Bites", "Hydralyte · TMR"),
                       ("MT Solus eDM - FESS Clinical Bites", "FESS · MT"),
                       ("TMR Solus eDM - FESS Clinical Bites", "FESS · TMR")):
        r = sends[lab]
        h2h.append([short, money(r["media_cost"]), r["human_sessions"], f"${n(r['media_cost']) / n(r['human_sessions']):,.0f}"])
    table(s, 8.15, 1.8, 4.78, [1.7, 1.0, 1.1, 0.98], h2h, row_h=0.38, header_h=0.4, size=10.5, header_size=10,
          color_cells={(1, 3): TEAL, (3, 3): TEAL})
    ce = {k: n(sends[k]["media_cost"]) / n(sends[k]["human_sessions"]) for k in sends if n(sends[k]["media_cost"]) and n(sends[k]["human_sessions"])}
    hm, ht = ce["MT Solus eDM - Hydralyte Clinical Bites"], ce["TMR Solus eDM - Hydralyte Clinical Bites"]
    fm, ft = ce["MT Solus eDM - FESS Clinical Bites"], ce["TMR Solus eDM - FESS Clinical Bites"]
    text(s, 8.15, 3.75, 4.78, 0.6, f"Medicine Today delivered more real sessions for ~$2k less on both series: "
                                   f"${hm:,.0f} vs ${ht:,.0f} (Hydralyte) and ${fm:,.0f} vs ${ft:,.0f} (FESS) per real session.", size=10, italic=True, color=TEAL)

    rect(s, 0.42, 4.85, 12.5, 1.65)
    text(s, 0.62, 4.95, 6, 0.3, "WHICH PUBLISHER IS BEST?", size=11.5, bold=True, color=GREY)
    bullets(s, 0.62, 5.27, 12.1, 1.2, [
        "GP reach per dollar: Medicine Today. Same creative as TMR, cheaper, more real sessions, and its report counts each reader once rather than every click",
        "Sign-ups: TMR's 1 Jul Clinical Audit send (20 tagged, ~31 est.) – the CPD offer drives it, so it isn't a like-for-like publisher win",
        f"Cheapest real traffic: Insight (${n(p['Insight']['cost_per_human_session'])} each), but optometrists view the chart and don't register; "
        f"IQVIA is cheap per session only once scanners are removed (${n(p['IQVIA']['cost_per_human_session'])})",
        f"Weakest: AJP ({p['AJP']['automated_share']} of its sessions automated, 0 sign-ups) and mivision ({n(p['mivision']['publisher_clicks'])} clicks reported, "
        f"{n(p['mivision']['human_sessions'])} real sessions)",
    ], size=10.5, gap=3)
    source(s, "Source: media cost ex GST and planning fee from the FY27 reconciliation / publisher invoices (TMR Clinical Audit $13,000 per AMJ rate). "
              "Real sessions as on the previous slide. Sign-ups = GA4 sign-up events in sessions from each send (undercounts; DB estimates on the reconciliation slide). "
              "*AJP solus bundled in the $28,243 AJP CPD package.")


def slide_assets(prs, layout):
    rows_in = csv_rows("jas26_asset_types.csv")
    s = new_slide(prs, layout)
    title(s, "ASSET TYPES – datasheets reach the most unique users, the quiz holds attention longest, "
             "and the quiz and articles cost least per engaged minute")
    types = {}
    for r in rows_in:
        t = types.setdefault(r["type"], {"users": 0, "secs": 0, "cost": 0, "cost_users": 0, "cost_secs": 0})
        u, sec = n(r["unique_users"]), n(r["avg_engaged_secs_per_user"]) * n(r["unique_users"])
        t["users"] += u
        t["secs"] += sec
        if r["production_cost"]:
            t["cost"] += n(r["production_cost"])
            t["cost_users"] += u
            t["cost_secs"] += sec

    def mins(sec):
        return f"{int(sec // 60)}m {int(sec % 60):02d}s"

    cards = [
        ("Articles", "Articles (4 new)"), ("Interactives", "Interactives (quiz)"),
        ("Videos", "Videos (8 Clinical Bites)"), ("Datasheets", "Datasheets / downloads"),
    ]
    x = 0.42
    for key, cap in cards:
        t = types[key]
        if key == "Datasheets":
            stat_card(s, x, 1.55, 3.0, 1.45, cap, "~1,300", "unique users across 5 datasheets\nopens a PDF, so no time is measured",
                      value_size=24)
        else:
            per_min = t["cost"] / (t["cost_secs"] / 60)
            stat_card(s, x, 1.55, 3.0, 1.45, cap, f"${per_min:,.0f} / min",
                      f"{t['cost_users']} unique users · {mins(t['cost_secs'] / t['cost_users'])} each\n${t['cost'] / t['cost_users']:,.0f} per unique user",
                      value_size=24, value_color=TEAL if per_min < 40 else NAVY)
        x += 3.17

    trs = [["Asset", "Type", "Unique users", "Engaged time / user", "Production cost", "Per unique user", "Per engaged minute"]]
    color = {}
    for i, r in enumerate(rows_in, 1):
        u, sec = n(r["unique_users"]), n(r["avg_engaged_secs_per_user"])
        users = "~1,000–1,200*" if r["asset"].startswith("AC Treatments") else f"{u:,}"
        cost = n(r["production_cost"])
        per_min = f"${cost / (u * sec / 60):,.0f}" if cost and sec else "–"
        trs.append([r["asset"], r["type"], users, mins(sec) if sec else "PDF", money(r["production_cost"]) if cost else "–",
                    f"${cost / u:,.0f}" if cost and u else "–", per_min])
        if r["asset"] == "Guess the Glucose quiz":
            color[(i, 3)] = TEAL
            color[(i, 6)] = TEAL
    table(s, 0.42, 3.2, 12.5, [3.3, 1.3, 1.4, 1.55, 1.45, 1.4, 1.6], trs, row_h=0.235, header_h=0.3, size=9, header_size=9,
          color_cells=color, align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT] + [PP_ALIGN.RIGHT] * 5)
    source(s, "Source: GA4 JAS26, asset page paths. Engaged time = userEngagementDuration ÷ unique users (includes video watching). Production cost = FY27 "
              "quote/actual (articles at the $1,800 rate; leaflet costs cover print); media excluded, as eDMs promoted several assets at once. *Excludes email-scanner traffic.")


def slide_measurement(prs, layout):
    s = new_slide(prs, layout)
    sens = {r["burst_threshold"]: n(r["real_sessions"]) for r in csv_rows("jas26_traffic_bot_threshold_sensitivity.csv") if r["period"] == "JAS26"}
    q = next(r for r in csv_rows("jas26_traffic_bot_adjusted.csv") if r["period"] == "JAS26")
    title(s, "MEASURING REAL PEOPLE – how the scanner filters work, why, and how the numbers get firmer")
    subtitle(s, f"No tool proves a single visit was a person. The filters remove only visits proven to be software and keep everyone else: of JAS26's "
                f"{n(q['real_sessions']):,} real sessions, {n(q['proven_human_sessions']):,} show a person and {n(q['unprovable_sessions']):,} are unprovable but counted. "
                "Once the legal and consent work lands, logged-in tracking replaces estimates with counts.", italic=True, size=12)

    rect(s, 0.42, 1.95, 3.3, 4.75)
    text(s, 0.62, 2.07, 3.0, 0.3, "WHY IT IS HARD", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 0.62, 2.42, 2.95, 4.2, [
        "Email security software opens links in a real browser, so it runs our tracking and looks like a visitor",
        "GA4 never shows IP address or browser identity; its built-in filter only removes crawlers that announce themselves",
        "Chart and leaflet links redirect straight to the PDF, so a person and a scanner both record 0 seconds and one page",
        "No publisher we use filters scanner clicks, and the host keeps no access logs we can read",
    ], size=10, gap=5)

    label(s, 3.95, 1.95, 9, "THE FILTERS – WHAT EACH DOES AND WHY")
    rows = [["Filter", "What it does", "Why it works", "What proved it"],
            ["1  Burst", "Flags every arrival when 10+ arrive from the same send in the same minute",
             "Security software checks a whole delivery batch at once, so its hits land together; people open email over hours",
             "Burst minutes are ~100% desktop Chrome with no phones (other minutes 14–22% phones); IQVIA's log shows the same minutes"],
            ["2  Human rescue", "Any flagged visit that shows a person is kept: phone or tablet, 3+ seconds reading, 3+ pages, or a human-only action "
             "(pop-up dismissed, PDF downloaded, form sent, video played, log-in)",
             "Scanners run headless desktop browsers and leave at once (0–1s); phones, reading time, browsing and clicking are human",
             "Checked every burst visit: 19% of JAS26 burst visits rescued, mostly GPs who clicked as a batch landed"],
            ["3  No page loaded", "Removes visits that started tracking but never recorded a page view",
             "A real browser always records the page; a fetch that stops before rendering is a link preview or pre-check",
             "35 visits in the quarter; too few to move any figure"],
            ["Not used", "Location, and engagement time on its own",
             "Real people are overseas too; and on redirect pages real readers look identical to scanners (0s, one page)",
             "Location rule cut 24–45% off quiet weeks; engagement-only would bin a third of real chart readers"],
            ["Threshold test", "The burst cut re-run at 5, 10 and 20 arrivals a minute",
             "If the result depended on the exact cut, it wouldn't be trustworthy",
             f"JAS26 real sessions {sens['5']:,} / {sens['10']:,} / {sens['20']:,}: within ±4%; phones fall from 36% of lone arrivals to 0% at 50+/min"]]
    table(s, 3.95, 2.25, 8.97, [1.15, 2.45, 2.75, 2.62], rows, row_h=0.58, header_h=0.26, size=8, header_size=8.5,
          align=[PP_ALIGN.LEFT] * 4, bold_cols=(0,))

    rect(s, 3.95, 5.5, 8.97, 1.2)
    text(s, 4.15, 5.58, 8.6, 0.3, "HOW THE NUMBERS GET FIRMER", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 4.15, 5.88, 8.6, 0.8, [
        "Now: ask the host for raw access logs (IP + browser) for 8–17 Sep to settle JAS26; log each hit on redirect pages and require a human presence signal before counting; "
        "ask publishers for per-reader counts and raw click logs",
        "After the legal and consent work: track logged-in members (GA4 User-ID), so course, resource and video use is counted per person, not estimated",
    ], size=9.5, gap=3)
    source(s, "Sources: GA4 bot filtering (IAB known-bots list only); Mailchimp, 'Bot clicks in email marketing'; Suped, Braze and Maestra on scanner click patterns; "
              "Wordfence live-traffic retention on prod (2,000 rows). Logged-in tracking is planned, not built; timing depends on the compliance work.")


def _pubs_sorted():
    p = pubs()
    return p, sorted(ORDER, key=lambda k: n(p[k]["cost_per_human_session"]) or 10**6)


def story_bots(prs, layout):
    p = pubs()
    s = new_slide(prs, layout)
    tot = sum(n(r["ga4_sessions"]) for r in p.values()); human = sum(n(r["human_sessions"]) for r in p.values())
    ex = [r for k, r in p.items() if k != "IQVIA"]
    ex_tot, ex_human = sum(n(r["ga4_sessions"]) for r in ex), sum(n(r["human_sessions"]) for r in ex)
    title(s, "ONE IN THREE eDM CLICKS THIS QUARTER WASN'T A PERSON")
    subtitle(s, "Email security software opens every link as mail lands, and it looks like a visitor. We found it, removed it, and kept everyone else.", italic=True, size=12)
    stat_card(s, 0.42, 1.95, 3.9, 1.5, "eDM sessions that were software", f"{(tot - human) / tot * 100:.0f}%", f"{tot - human:,} of {tot:,} sessions across 9 sends", value_color=RED)
    stat_card(s, 4.52, 1.95, 3.9, 1.5, "IQVIA alone", p["IQVIA"]["automated_share"], "hospital and practice inboxes, delivered in batches", value_color=RED)
    stat_card(s, 8.62, 1.95, 4.3, 1.5, "Every other publisher", f"{(ex_tot - ex_human) / ex_tot * 100:.0f}%", "Medicine Today, TMR, Insight, AJP, mivision combined", value_color=TEAL)
    label(s, 0.42, 3.7, 6.3, "REAL VS AUTOMATED, BY PUBLISHER")
    cd = CategoryChartData()
    cats = list(reversed(ORDER))
    cd.categories = cats
    cd.add_series("Real people", [n(p[c]["human_sessions"]) for c in cats])
    cd.add_series("Software", [n(p[c]["ga4_sessions"]) - n(p[c]["human_sessions"]) for c in cats])
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_STACKED, Inches(0.35), Inches(3.95), Inches(6.5), Inches(2.85), cd)
    ch = gf.chart; style_chart(ch, 10); no_title(ch)
    ch.has_legend = True; ch.legend.position = XL_LEGEND_POSITION.BOTTOM; ch.legend.include_in_layout = False; ch.legend.font.size = Pt(9.5)
    ch.plots[0].gap_width = 45
    for ser, col in zip(ch.series, (TEAL, RED)):
        ser.format.fill.solid(); ser.format.fill.fore_color.rgb = col
    axis_quiet(ch.value_axis, gridlines=True, size=9); axis_quiet(ch.category_axis, size=10)
    rect(s, 7.1, 3.7, 5.82, 3.1)
    text(s, 7.3, 3.8, 5.5, 0.3, "THE STORY", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 7.3, 4.12, 5.45, 2.6, [
        "How we spotted it: hundreds of arrivals in the same minute, all desktop, no phones. IQVIA's own click log shows the same pattern",
        "What we removed: only visits proven to be software. Anyone showing a sign of a person stays in, wherever they are",
        "What it changes: IQVIA's 1,140 clicks were really 350–470 readers. The GP publishers' numbers barely move",
        "Scanner clicks also sit inside every publisher's click report; none of them filter it out",
    ], size=10.5, gap=4)
    source(s, "Source: GA4 + publisher reports, send day to 30 Sep 2026. Method and proof on the appendix slides.")


def story_publishers(prs, layout):
    p, order = _pubs_sorted()
    sends = {r["send"]: r for r in csv_rows("jas26_bot_by_send.csv")}
    s = new_slide(prs, layout)
    title(s, "WHICH PUBLISHER IS BEST – Medicine Today for GP reach, Insight for eye-care; TMR earns its place on sign-ups")
    subtitle(s, "Judged on real sessions per dollar, not on the clicks publishers report.", italic=True, size=12)
    verdict = {"Insight": "Cheapest reach, but optometrists read the chart and don't register",
               "IQVIA": "Good value once scanners are removed; ask for bot-filtered reports",
               "Medicine Today": "Best GP value: same email as TMR, cheaper, more real readers",
               "The Medical Republic": "Dearer per session, but its Clinical Audit send drove the sign-ups",
               "mivision": "619 clicks reported, 81 real sessions; newsletter banners, not a solus",
               "AJP": "Pharmacists; a quarter of sessions were software, no sign-ups"}
    rows = [["Publisher", "Cost per real session", "Real sessions", "Sign-ups", "Verdict"]]
    color = {}
    for i, k in enumerate(order, 1):
        r = p[k]
        rows.append([k, money(r["cost_per_human_session"]) if r["cost_per_human_session"] else "in CPD package", f"{n(r['human_sessions']):,}", r["ga4_tagged_signups"], verdict[k]])
        if k in ("Insight", "Medicine Today"): color[(i, 1)] = TEAL
    table(s, 0.42, 1.95, 12.5, [2.0, 1.6, 1.3, 1.0, 6.6], rows, row_h=0.46, header_h=0.4, size=10.5, header_size=10,
          align=[PP_ALIGN.LEFT, PP_ALIGN.RIGHT, PP_ALIGN.RIGHT, PP_ALIGN.RIGHT, PP_ALIGN.LEFT], bold_cols=(0, 1), color_cells=color)
    hm = n(sends["MT Solus eDM - Hydralyte Clinical Bites"]["media_cost"]) / n(sends["MT Solus eDM - Hydralyte Clinical Bites"]["human_sessions"])
    ht = n(sends["TMR Solus eDM - Hydralyte Clinical Bites"]["media_cost"]) / n(sends["TMR Solus eDM - Hydralyte Clinical Bites"]["human_sessions"])
    rect(s, 0.42, 5.3, 12.5, 1.45)
    text(s, 0.62, 5.4, 6, 0.3, "WHAT TO DO WITH IT", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 0.62, 5.72, 12.1, 1.0, [
        f"Same Hydralyte email, same month: Medicine Today ${hm:,.0f} per real session, TMR ${ht:,.0f}. Lead with Medicine Today for content; use TMR when the goal is CPD sign-ups",
        "Keep Insight for eye-care reach, price IQVIA on real readers, and drop AJP solus and mivision banners unless the brief is pharmacy-only",
    ], size=10.5, gap=4)
    source(s, "Source: media cost ex GST and planning fee (FY27 reconciliation, publisher invoices; TMR Clinical Audit at the AMJ rate); real sessions = GA4 minus proven scanner traffic; "
              "sign-ups = GA4-tagged (undercounts). AJP solus is inside the $28,243 CPD package.")


def story_assets(prs, layout):
    rows_in = csv_rows("jas26_asset_types.csv")
    s = new_slide(prs, layout)
    title(s, "WHAT CONTENT EARNS ITS KEEP – PDFs reach the most people, the quiz holds them longest")
    subtitle(s, "Reach, attention and production cost per minute of attention, by content type.", italic=True, size=12)
    types = {}
    for r in rows_in:
        t = types.setdefault(r["type"], {"users": 0, "secs": 0, "cost": 0, "cost_users": 0, "cost_secs": 0})
        u, sec = n(r["unique_users"]), n(r["avg_engaged_secs_per_user"]) * n(r["unique_users"])
        t["users"] += u; t["secs"] += sec
        if r["production_cost"]:
            t["cost"] += n(r["production_cost"]); t["cost_users"] += u; t["cost_secs"] += sec
    def mins(sec): return f"{int(sec // 60)}m {int(sec % 60):02d}s"
    tiles = [
        ("Datasheets (PDFs)", "~1,300", "unique users, the biggest reach", "Chart and leaflet links; reading time can't be seen once the PDF opens", NAVY),
        ("Videos (Clinical Bites)", f"{types['Videos']['users']}", f"unique users · {mins(types['Videos']['secs'] / types['Videos']['users'])} each", f"${types['Videos']['cost'] / (types['Videos']['cost_secs'] / 60):,.0f} per minute of attention; the dearest, but they drove 24 Clinical Audit pop-up clicks", NAVY),
        ("Interactive (glucose quiz)", f"{types['Interactives']['cost_users']}", f"unique users · {mins(types['Interactives']['cost_secs'] / types['Interactives']['cost_users'])} each", f"${types['Interactives']['cost'] / (types['Interactives']['cost_secs'] / 60):,.0f} per minute of attention, the cheapest; small reach so far", TEAL),
        ("Articles", f"{types['Articles']['users']}", f"unique users · {mins(types['Articles']['secs'] / types['Articles']['users'])} each", f"${types['Articles']['cost'] / (types['Articles']['cost_secs'] / 60):,.0f} per minute; the Zaditen 'spot the allergy eye' piece did most of the work", NAVY),
    ]
    x = 0.42
    for cap, val, sub, note_t, col in tiles:
        rect(s, x, 1.95, 3.0, 2.75)
        text(s, x + 0.2, 2.07, 2.6, 0.3, cap, size=11.5, bold=True, color=GREY)
        text(s, x + 0.2, 2.42, 2.6, 0.6, val, size=30, bold=True, color=col)
        text(s, x + 0.2, 3.05, 2.6, 0.5, sub, size=10, color=GREY)
        text(s, x + 0.2, 3.6, 2.6, 1.0, note_t, size=10, color=INK)
        x += 3.17
    rect(s, 0.42, 4.95, 12.5, 1.5)
    text(s, 0.62, 5.05, 6, 0.3, "THE STORY", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 0.62, 5.4, 12.1, 1.0, [
        "Give people something to download and they come in numbers; give them something to do and they stay. Both have a place",
        "Next builds: one more interactive (cheap attention) and keep every PDF behind a tracked page so reach stays measurable",
    ], size=10.5, gap=4)
    source(s, "Source: GA4 JAS26 by asset page; engaged time = userEngagementDuration ÷ unique users; production cost from FY27 quotes/actuals (articles at the $1,800 rate; media excluded). Detail in the appendix.")


def story_measure(prs, layout):
    q = next(r for r in csv_rows("jas26_traffic_bot_adjusted.csv") if r["period"] == "JAS26")
    s = new_slide(prs, layout)
    title(s, "HOW WE COUNT REAL PEOPLE – remove proven software, keep everyone else, then make it exact")
    subtitle(s, f"JAS26: {n(q['real_sessions']):,} real sessions, of which {n(q['proven_human_sessions']):,} show a person and {n(q['unprovable_sessions']):,} can't be proven either way and are counted as human.", italic=True, size=12)
    cols = [
        ("WE REMOVE", RED, ["Bursts: 10 or more arrivals from one email in the same minute. That's security software checking a delivery batch, and it's all desktop with no phones",
                            "Fetches that never loaded a page"]),
        ("WE KEEP", TEAL, ["Anyone showing a sign of a person, even inside a burst: a phone, 3+ seconds reading, several pages, a pop-up dismissed, a PDF downloaded",
                           "Everything we can't prove either way. Location is never a test"]),
        ("WHAT'S NEXT", NAVY, ["Ask the host for September's server logs to settle this quarter for good",
                              "Log every hit on the chart and leaflet pages and require a sign of a person before it counts",
                              "Once the legal and consent work lands: track logged-in members, so courses, resources and videos are counted per person, not estimated"]),
    ]
    x = 0.42
    for head, col, items in cols:
        rect(s, x, 1.95, 4.05, 3.2)
        text(s, x + 0.2, 2.07, 3.7, 0.3, head, size=12, bold=True, color=col, spacing=200)
        bullets(s, x + 0.2, 2.45, 3.7, 2.6, items, size=11, gap=6)
        x += 4.22
    text(s, 0.42, 5.35, 12.5, 0.6, "Checked: moving the burst cut from 5 to 20 arrivals a minute changes the quarter's real sessions by under 4%. Two independent methods (GA4 timing, IQVIA's own click log) agree on IQVIA.",
         size=11, italic=True, color=TEAL)
    source(s, "Filters, evidence and references on the appendix slides. Logged-in tracking is planned, not built; timing depends on the compliance work.")


def appendix_divider(prs, layout):
    s = new_slide(prs, layout)
    title(s, "APPENDIX – measurement detail")
    text(s, 0.42, 2.0, 12.5, 1.0, "Workings behind the publisher, content and real-people slides: non-human click breakdown, publisher accuracy, "
                                   "publisher value, asset types, and the filter table with references.", size=14, color=GREY)


def main() -> None:
    prs, layout = open_template()
    for build in (story_bots, story_publishers, story_assets, story_measure, appendix_divider, slide_bot_types, slide_accuracy, slide_value, slide_assets, slide_measurement):
        build(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

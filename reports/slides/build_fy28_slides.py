"""Build the NEW slides for the FY28 Care Connect plan (CAPH0077 follow-up).

    reports/.venv/bin/python reports/slides/build_fy28_slides.py <out.pptx>

Only the slides that don't already exist in a previous deck are built here.
assemble_fy28.ps1 interleaves them with slides lifted from the FY27 brand plan,
the AMJ/JAS quarterly reviews and CAPH0068, in the order set in its manifest.
Figures are the circulated quarterly-review figures (as at 30 Sep 2026).
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches, Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    AMBER, CARD, GREY, INK, LINE, NAVY, PINK, PURPLE, TEAL, TEAL2, WHITE, ORANGE,
    RGBColor, bullets, label, new_slide, open_template, rect, source, stat_card, table, text, title, subtitle,
)

GREEN = RGBColor(0x2E, 0x7D, 0x32)
FESS_BLUE = RGBColor(0x00, 0x5E, 0xB8)
HYD_ORANGE = RGBColor(0xF2, 0x7A, 0x2A)
ZAD_GREEN = RGBColor(0x00, 0x8C, 0x7A)
RECTO_PURPLE = RGBColor(0x6A, 0x2C, 0x9A)
SOFT_GREEN = RGBColor(0xE8, 0xF5, 0xE9)
SOFT_AMBER = RGBColor(0xFF, 0xF4, 0xE0)
SOFT_BLUE = RGBColor(0xE8, 0xF0, 0xFA)

QBR_SOURCE = "Source: CAPH0107 AMJ '26 and JAS '26 HCP Results (as presented); figures as at 30 Sep 2026 unless noted."


def pill(slide, x, y, w, h, t, fill, color=WHITE, size=10.5, bold=True):
    rect(slide, x, y, w, h, fill=fill, line=None, radius=0.3)
    text(slide, x, y, w, h, t, size=size, color=color, bold=bold, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)


# ── FY27 recap ──────────────────────────────────────────────────────────────
def slide_scorecard(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="FY27 SCORECARD: all four KPIs ahead of target at the half-way mark")
    objectives = [
        ("Support the GP Detailing Program with educational material",
         "Care Connect: Sick Day Plan, AR + AC charts. Outside the portal: GP Desk Set (16,257 engagements)"),
        ("Continue to grow the Care Connect database",
         "1,555 registered HCPs, +19% on the FY26 close"),
        ("Build brand equity and awareness via multi-channel comms",
         "457K+ trade media impressions (AU + NZ) and 9 solus eDMs in JAS alone"),
        ("Create and grow strong partnerships: KOLs and organisations",
         "RACGP-accredited audit, AJP CPD, CWH + TWCM masterclasses, KOL content with 3 experts"),
    ]
    x = 0.42
    for head, result in objectives:
        rect(s, x, 1.45, 3.02, 0.95, fill=NAVY, line=None, radius=0.12)
        text(s, x + 0.12, 1.45, 2.78, 0.95, head, size=11, color=WHITE, bold=True, align=PP_ALIGN.CENTER,
             anchor=MSO_ANCHOR.MIDDLE)
        rect(s, x, 2.55, 3.02, 0.95)
        text(s, x + 0.14, 2.55, 2.74, 0.95, result, size=10.5, color=INK, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        x += 3.14

    label(s, 0.42, 3.68, 6, "TOP-DOWN KPIs")
    rows = [
        ["KPI", "FY27 target", "Result so far", "Status"],
        ["New portal registrations", "+5% vs FY26 = 349 new", "249 by 30 Sep (71% of target)", "Ahead"],
        ["eDM unique CTR", "4%", "6.6% JAS average (excl. IQVIA); 6.3% / 6.4% on AMJ TMR sends", "Ahead"],
        ["Portal traffic", "+4% on FY26", "Up on last year every quarter: AMJ 1,482 users (+74%), JAS 3,605 users (+198%)", "Ahead"],
        ["Average engaged time", "Maintain 2m 20s", "~2m 22s across AMJ + JAS", "Ahead"],
    ]
    fills, colors = {}, {}
    for r in range(1, len(rows)):
        ok = rows[r][3] == "Ahead"
        fills[(r, 3)] = SOFT_GREEN if ok else SOFT_AMBER
        colors[(r, 3)] = GREEN if ok else AMBER
    tbl = table(s, 0.42, 3.98, 12.5, [2.3, 2.6, 5.6, 1.1], rows, row_h=0.46, size=11, header_size=11,
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.CENTER], bold_cols=(0, 3),
          fills=fills, color_cells=colors)
    for r, line in ((2, "Real results: 1,899 real visits from 8 JAS eDMs (bots removed); 56 sign-ups"),
                    (4, "4m 21s in AMJ (audit), 1m 33s in JAS (quick look-ups)")):
        run = tbl.cell(r, 2).text_frame.add_paragraph().add_run()
        run.text = line
        run.font.size = Pt(9.5)
        run.font.color.rgb = GREY
        run.font.italic = True
        tbl.rows[r].height = Inches(0.62)
    source(s, "Source: CAPH0107 AMJ '26 and JAS '26 HCP Results (as presented), to 30 Sep 2026. Engaged time: AMJ and JAS combined, weighted by users.")


def slide_by_brand(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="WHAT WE DELIVERED: every focus brand had new HCP tools on Care Connect in FY27")
    cols = [
        ("FESS", FESS_BLUE, [
            "Children's C&F campaign: AU + NZ trade media, 131K AU impressions",
            "Dr Jonny Taitz article on paediatric URTIs (245 unique users)",
            "Clinical Bites: Allergic Rhinitis Rounds (3 episodes)",
            "2026 AR Treatments Chart landing page",
            "AJP CPD article: 3,472 completions in one month",
            "GP Desk Set: Paediatric Sinus Chart + Patient Leaflet",
            "CWH Pharmacist Masterclass (Dr Jessica Tattersall)",
        ]),
        ("HYDRALYTE", HYD_ORANGE, [
            "Winter V&D trade campaign: 120K impressions",
            "Clinical Bites: Diabetes Sick Day Management (5 episodes, Deb Hawthorne)",
            "Diabetes Sick Day Plan + GP leave behind",
            "Guess the Glucose quiz",
            "Diabetes travel case study (live 27 Oct)",
            "Gastro Desk Set: 4,080 engagements, +99% YoY",
            "Sticks NPD trade campaign (from Nov)",
        ]),
        ("ZADITEN", ZAD_GREEN, [
            "Back in Stock campaign across pharmacists, GPs and eye specialists",
            "NEW Allergic Conjunctivitis Treatments Chart: top resource of the year",
            "First IQVIA and Insight solus eDMs",
            "Mivision sponsored content + AJP onsert",
            "115K AU pharmacy impressions in September alone",
        ]),
        ("RECTOGESIC", RECTO_PURPLE, [
            "Anal Fissure Clinical Audit (RACGP, launched 19 Mar)",
            "Promoted via ADG, TMR and MT solus eDMs",
            "CPD pop-up on Care Connect (live 6 Aug)",
            "20 audits approved, certificates issued (to 30 Sep)",
            "Audit v2: case-based, auto-checked inputs (live 5 Nov)",
        ]),
    ]
    x = 0.42
    for name, colr, items in cols:
        rect(s, x, 1.45, 3.02, 0.5, fill=colr, line=None, radius=0.15)
        text(s, x, 1.45, 3.02, 0.5, name, size=13, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE,
             spacing=150)
        rect(s, x, 2.1, 3.02, 3.95)
        bullets(s, x + 0.15, 2.26, 2.75, 3.7, items, size=11, gap=4)
        x += 3.14
    rect(s, 0.42, 6.2, 12.5, 0.5, fill=SOFT_BLUE, line=None, radius=0.2)
    text(s, 0.42, 6.2, 12.5, 0.5, [[("ENABLER: CARE CONNECT OPTIMISATION  ", {"bold": True, "color": NAVY}),
                                      ("SEO go-live, sample-order fix, video hosting, licences and theme in-house  \u00b7  4 fundamentals on track for 31 March 2027", {})]],
         size=10.5, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    source(s, QBR_SOURCE)


def slide_media_learnings(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="eDM LEARNINGS: choose the publisher for the content, and read every result through a bot filter")
    rows = [
        ["Publisher", "Audience", "FY27 highlight", "What it tells us"],
        ["The Medical Republic", "GPs", "Audit eDM 1 Jul: 31 sign-ups, 285 real visits", "Most consistent results; preferred GP partner"],
        ["Medicine Today", "GPs", "263 real visits on Hydralyte diabetes; 130 on FESS videos", "Signs of eDM fatigue; topic-dependent"],
        ["ADG", "GPs", "Audit eDM 22 May: 104 sign-ups, the biggest single result of FY27", "Brings volume; cost limits frequency"],
        ["IQVIA", "GPs + eye specialists", "68% of eDM sessions were security software, not people", "Cheap per click once cleaned; data needs filtering"],
        ["Insight", "Ophthal / optom", "490 real visits on Zaditen, the most of any JAS send", "Specialist titles reach specialists best"],
        ["AJP", "Pharmacists", "96 real visits; most clicks went to the CPD article on AJP's own site", "Pharmacists click education, little else"],
    ]
    table(s, 0.42, 1.45, 12.5, [2.0, 1.8, 5.0, 3.7], rows, row_h=0.52, size=11, header_size=11,
          align=[PP_ALIGN.LEFT] * 4, bold_cols=(0,))
    stat_card(s, 0.42, 5.35, 4.0, 1.35, "Sign-ups attributable to eDMs", "75% / 59%", "of new registrations in AMJ / JAS", value_size=24)
    stat_card(s, 4.67, 5.35, 4.0, 1.35, "CPD vs content eDMs", "8.4% vs 0.5%", "sign-up conversion: TMR Audit eDM vs FESS video eDMs", value_color=TEAL, value_size=24)
    stat_card(s, 8.92, 5.35, 4.0, 1.35, "eDM sessions that were software (JAS)", "31%", "filtered out before reporting; IQVIA alone 68%", value_color=PINK, value_size=24)
    source(s, QBR_SOURCE)


def slide_top_assets(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="WHAT HCPs VALUED MOST: practical clinical tools and CPD beat every other format")
    label(s, 0.42, 1.45, 12.5, "ON CARE CONNECT")
    portal = [
        ("Zaditen AC Chart", "~1,000", "unique users in its first month; top resource of FY27", ZAD_GREEN),
        ("Diabetes Sick Day Plan", "~122", "unique users: ~99 from eDMs + 23 browsing the site", HYD_ORANGE),
        ("Clinical Audit (CPD)", "184", "enrolled since 19 Mar; 20 completed with certificates issued, to 30 Sep", RECTO_PURPLE),
        ("Clinical Bites videos", "45%", "of arrivals pressed play (130 of 289)", PINK),
    ]
    w = (12.5 - (len(portal) - 1) * 0.15) / len(portal)
    for i, (cap, val, note, colr) in enumerate(portal):
        stat_card(s, 0.42 + i * (w + 0.15), 1.75, w, 1.55, cap, val, note, value_color=colr, value_size=22)
    label(s, 0.42, 3.5, 12.5, "OUTSIDE THE PORTAL  \u00b7  PARTNER-REPORTED")
    outside = [
        ("AJP allergy CPD (on AJP's site)", "3,472", "completions in one month vs ~2,000 typical in a year", FESS_BLUE),
        ("GP Desk Set (Princeton app)", "16,257", "engagements reported by Princeton; patient resources lead", NAVY),
        ("Gastro Desk Set (Princeton app)", "4,080", "engagements reported by Princeton, +99% YoY", HYD_ORANGE),
    ]
    for i, (cap, val, note, colr) in enumerate(outside):
        stat_card(s, 0.42 + i * (12.5 + 0.15) / 3, 3.8, (12.5 - 2 * 0.15) / 3, 1.45, cap, val, note, value_color=colr, value_size=24)
    rect(s, 0.42, 5.45, 12.5, 1.25, fill=SOFT_BLUE, line=None, radius=0.1)
    band = bullets(s, 0.62, 5.45, 12.1, 1.25, [
        [("Charts, plans and leaflets travel: ", {"bold": True}), ("HCPs open them from eDMs, find them again on the site, and use them with patients.", {})],
        [("CPD is the sign-up driver: ", {"bold": True}), ("audit eDMs convert at 8.4%; video is an education format, with fewer than half of arrivals pressing play.", {})],
    ], size=11.5, gap=4)
    band.text_frame.vertical_anchor = MSO_ANCHOR.MIDDLE
    source(s, QBR_SOURCE + " Outside-the-portal figures are the partners' own reporting (AJP, Princeton).")


def slide_fundamentals(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="FUNDAMENTALS UPDATE: compliance complete, the other three on track for 31 March 2027")
    cols = [
        ("01  COMPLIANCE", ORANGE, "COMPLETE", GREEN,
         ["Live 16 Nov: consent pop-up, preferences page and sign-up tick box",
          "Update email sent to 1,634 HCPs (17 Nov)",
          "Every answer logged with wording, date and version"]),
        ("02  ANALYTICS", NAVY, "QUEUED", GREY,
         ["Per-user page tracking and journeys", "Microsoft Clarity heatmaps and session recordings",
          "Retention tracking (3, 7, 30 day)", "Starts after Resources; turns ranges into facts"]),
        ("03  FAILURE LOGGING", NAVY, "COMPLETE", GREEN,
         ["Live since 30 Jun across site, server and email delivery",
          "Caught and fixed: login failures, search errors, sample-order script error, registration page error",
          "Alerts reach us before HCPs report them"]),
        ("04  RESOURCES", TEAL, "IN DESIGN", AMBER,
         ["Full audit complete", "Resource Hubs by therapy area: landing design agreed",
          "Care reviewing items to retire", "Next: inside-a-hub page, search + filter, build"]),
    ]
    x = 0.42
    for head, colr, status, scol, items in cols:
        rect(s, x, 1.45, 3.02, 0.5, fill=colr, line=None, radius=0.1)
        text(s, x + 0.15, 1.45, 2.8, 0.5, head, size=11.5, color=WHITE, bold=True, anchor=MSO_ANCHOR.MIDDLE, spacing=100)
        rect(s, x, 2.1, 3.02, 2.45)
        text(s, x + 0.15, 2.25, 2.8, 0.3, status, size=11, color=scol, bold=True, spacing=150)
        bullets(s, x + 0.15, 2.6, 2.75, 1.9, items, size=11, gap=6)
        x += 3.14
    label(s, 0.42, 4.75, 8, "WHAT EACH ONE UNLOCKS IN FY28")
    unlocks = ["Consented database: the CRM plays can send", "Per-user data: the personalised page has signals to use",
               "Faster fixes: HCPs hit fewer walls", "A findable library: better landing pages for every eDM"]
    x = 0.42
    for u in unlocks:
        rect(s, x, 5.05, 3.02, 1.65, fill=SOFT_BLUE, line=None, radius=0.1)
        text(s, x + 0.2, 5.05, 2.65, 1.65, u, size=11, color=NAVY, bold=True, anchor=MSO_ANCHOR.MIDDLE)
        x += 3.14
    source(s, "Status as at 18 Nov 2026.")


def slide_platform(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="BEYOND THE FUNDAMENTALS: what else we fixed and improved in FY27")
    cards = [
        ("Opened to search engines (23 Jul)", "2,948", "Google impressions to 30 Sep; 34 search clicks + 4 from ChatGPT. The site had been hidden from search before handover.", TEAL),
        ("Sample-order fix (12 Aug)", "65", "orders reached Care in JAS vs 17 in AMJ. Since Dec 2024, 143+ practices had repeat orders silently lost.", NAVY),
        ("Video hosting moved (13 Oct)", "Same day", "site videos back online after the Vimeo suspension (two recovered from archives), now with captions, end cards and series autoplay.", PINK),
        ("Licences on Care's accounts (3 Nov)", "3", "paid plugins (Formidable, LearnDash, Imagify) moved off previous-supplier accounts.", PURPLE),
    ]
    for i, (cap, val, note, colr) in enumerate(cards):
        x = 0.42 + (i % 2) * 6.32
        y = 1.45 + (i // 2) * 2.05
        stat_card(s, x, y, 6.18, 1.9, cap, val, note, value_color=colr, value_size=30)
    rect(s, 0.42, 5.45, 12.5, 1.25, fill=SOFT_BLUE, line=None, radius=0.1)
    text(s, 0.62, 5.62, 12.1, 0.3, "LICENCE SAVINGS  \u00b7  BALLPARK", size=11, bold=True, color=GREY, spacing=200)
    text(s, 0.62, 5.95, 12.1, 0.7, [[("~A$810 a year ", {"bold": True}),
                                    ("in licences stopped or downgraded: the previous supplier's theme licence (A$330, theme now in-house), WP Rocket, "
                                     "Formidable Business instead of Elite, and ACF Pro kept on its lifetime licence. ", {}),
                                    ("Vimeo ($5,300 bill) no longer needed: ", {"bold": True}),
                                    ("videos moved to our own storage.", {})]],
         size=11.5)
    source(s, QBR_SOURCE + " Licence and video items as at Nov 2026; savings at list prices, US$ at ~1.55.")


# ── FY28 plan ───────────────────────────────────────────────────────────────
RATE = 130  # $/hr set by Claire (RW review, 18 Nov 2026): BAU 388 hrs = $50,440
BAU_HRS = 388
BAU_COST = 50.44  # $K
LICENCES = 4  # $K a year, see slide_licences

FY28_ITEMS = [
    ("Bringing CPD in-house", "Recommendation 1: CPD platform + registration gate", 80),
    ("Always-on CRM plan", "Recommendation 2", 100),
    ("Personalised landing page", "Recommendation 3", 90),
    ("Mobile UX audit + fixes", "Recommendation 4", 70),
    ("Dietitian access", "Recommendation 5: dietitian sign-up, review + own section", 11),
]


def slide_budget(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="FY28 BUDGET ON A PAGE: $103K ex GST, with half going to growth")
    subtitle(s, "FY28 = 1 April 2027 to 31 March 2028. Assumes the 4 fundamentals are complete by 31 March 2027.", y=1.2)
    # proportional bar
    segs = [("BAU", "$50K", 50, NAVY), ("Licences", f"~${LICENCES}K", LICENCES, GREY),
            ("FY28 growth plan", f"~${103 - 50 - LICENCES}K", 103 - 50 - LICENCES, TEAL)]
    x, total_w = 0.42, 12.5
    for name, amt, v, colr in segs:
        w = total_w * v / 103
        rect(s, x, 1.85, w, 0.75, fill=colr, line=None, rounded=False)
        if w > 1.0:
            text(s, x, 1.85, w, 0.75, [[(name + "  ", {"bold": True}), (amt, {})]], size=14, color=WHITE,
                 align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        x += w
    text(s, 0.42, 2.65, 12.5, 0.3, f"Licences (~${LICENCES}K) sit between the two: see the licences slide.", size=10, color=GREY, italic=True)

    rows = [["Bucket", "What it covers", "Hours", "Cost"]]
    rows.append(["BAU", "Content uploads, eDM management, UTM/QR, quarterly reporting, security + maintenance, co-ordination", f"~{BAU_HRS}", "$50,440"])
    rows.append(["Licences + subscriptions", "LearnDash, Formidable, Imagify, web authoring tools, video hosting, staging, CRM email sending", "n/a", f"~${LICENCES}K"])
    for name, kind, hrs in FY28_ITEMS:
        rows.append([name, kind, f"~{hrs}", f"~${hrs * RATE / 1000:.1f}K"])
    recs = sum(h for *_, h in FY28_ITEMS)
    rows.append(["TOTAL", "Within the $103K budget", f"~{BAU_HRS + recs}", f"~${BAU_COST + LICENCES + recs * RATE / 1000:.1f}K"])
    n = len(rows)
    fills = {(n - 1, c): SOFT_BLUE for c in range(4)}
    table(s, 0.42, 3.0, 12.5, [3.2, 6.5, 1.2, 1.6], rows, row_h=0.41, size=11, header_size=11,
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.CENTER, PP_ALIGN.CENTER], bold_cols=(0,), fills=fills)
    source(s, "Hours are Panwar Health estimates; costs at ~$130/hr, to be confirmed. New CPD course content (recommended by Panwar staff) to be costed separately.")


def slide_bau(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="BAU: everything that keeps Care Connect running")
    subtitle(s, "FY27 set aside $90K for BAU. A year in, we know the real workload, have implemented efficiencies and can do this for substantially less.", y=1.2)
    rows = [
        ["Line item", "What's included", "Hours / yr"],
        ["Content uploads + page builds", "Articles, resources, landing pages, tracked PDF links, ungate windows", "80"],
        ["eDM campaign management", "Builds, testing and sends; publisher HTML and link checks", "60"],
        ["UTM links + QR codes", "Tagging every eDM, print and QR link to the convention", "16"],
        ["Quarterly reporting", "4 quarterly reviews: GA4, database, eDM reconciliation, bot filtering", "96"],
        ["Security monitoring", "Error alerts, backups, access reviews, vulnerability response", "40"],
        ["Platform maintenance", "Quarterly WordPress + plugin updates tested on staging; fixes", "56"],
        ["Account + project co-ordination", "Status, briefs, run-throughs, reviews, approvals", "40"],
        ["TOTAL", "", "388 hours $50,440"],
    ]
    n = len(rows)
    fills = {(n - 1, c): SOFT_BLUE for c in range(3)}
    table(s, 0.42, 1.85, 12.5, [3.3, 7.6, 1.6], rows, row_h=0.53, size=11.5, header_size=11.5,
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.CENTER], bold_cols=(0,), fills=fills)
    source(s, "Hours are Panwar Health estimates; cost to be finalised. PHP version upgrades are handled by Ayuda Hosting.")


def slide_licences(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t=f"LICENCES + SUBSCRIPTIONS: now on Care-owned accounts, ~${LICENCES}K a year")
    rows = [
        ["Licence / service", "What it does", "Annual cost", "Status"],
        ["LearnDash", "Courses, quizzes, CPD certificates", "US$350", "Paid to Oct 2027; not renewed after Recommendation 1"],
        ["Formidable Forms Business", "Registration, sample orders, surveys, audit", "US$399", "Care account (live 2 Nov)"],
        ["Imagify Growth", "Image compression (site speed)", "~US$60", "Care account (live 2 Nov)"],
        ["Web authoring tools", "Code, build and content tools", "US$1,200", "Panwar account, charged to Care Connect"],
        ["Cloudflare R2", "Video hosting (replaced Vimeo)", "Usage, low", "Panwar account"],
        ["Azure staging", "Test site for every change before prod", "Usage, low", "Paused between jobs"],
        ["Email platform for CRM", "Automated emails (Recommendation 2)", "Usage, low", "Provided and run by Panwar"],
        ["Free / bundled", "GA4, Search Console, Clarity, Sentry (free tier); SMTP + Mailgun (Ayuda); ACF Pro (lifetime)", "$0", "No action"],
    ]
    fills = {}
    table(s, 0.42, 1.45, 12.5, [2.6, 4.9, 1.6, 3.4], rows, row_h=0.47, size=11, header_size=11,
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.CENTER, PP_ALIGN.LEFT], bold_cols=(0,), fills=fills)
    rect(s, 0.42, 5.85, 12.5, 0.85, fill=SOFT_BLUE, line=None, radius=0.15)
    text(s, 0.62, 5.85, 12.1, 0.85, [[("No longer paying for: ", {"bold": True}),
                                      ("Vimeo (suspended Oct 2026), the previous supplier's theme licence, WP Rocket (inactive). "
                                       "Restrict Content Pro is no longer sold on its own; Recommendation 1 (Bringing CPD in-house) replaces it with our own registration gate.", {})]],
         size=11.5, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Hosting (Ayuda) not included. USD list prices; AUD will vary with the exchange rate.")


def slide_rebuild_what(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="RECOMMENDATION 1: Future-proofing Care Connect")
    subtitle(s, "We write our own LMS module for Care Connect and remove LearnDash and the other third-party course and registration plugins, "
                "including Restrict Content Pro. HCPs get a smoother, more reliable site; Care gets new CPD courses faster and at lower cost.", y=1.2, size=12)
    label(s, 0.42, 1.85, 12.5, "WHAT CARE GETS FROM THE FY28 PROPOSAL")
    cols = [
        ("MORE CONTROL", "over the whole experience", TEAL, [
            "Full control of the look and feel, from sign-up to certificate",
            "Full control of the code: changes made directly, not worked around a plugin",
            "Fewer bugs: no more fragile third-party plugins wired together",
            "One \u201cMy CPD\u201d page for HCPs' progress and certificates",
        ]),
        ("EASIER CPD COURSES", "for Care", NAVY, [
            "Courses built in Panwar's SCORM editor, if Care wants it",
            "Quicker to develop new courses: no more time lost working around the hacks and patches holding the current LMS together",
            "Audit approval and certificate in one click",
            "Simple CPD reports for college reporting",
        ]),
        ("LESS RELIANCE", "on third parties", PURPLE, [
            "Course and registration plugins replaced with our own",
            "No LearnDash renewal once live (paid to Oct 2027)",
            "Care's code on Care's site: no supplier lock-in",
        ]),
        ("CLEANER FOUNDATIONS", "behind the scenes", GREEN, [
            "Untangles the inherited code so the course, registration and sign-up parts each stand on their own, and a fix in one is far less likely to break another",
            "Fixes found once, not patched around",
            "A clean codebase for everything Care Connect adds next",
        ]),
    ]
    w = (12.5 - 3 * 0.14) / 4
    x = 0.42
    for head, sub, colr, items in cols:
        rect(s, x, 2.17, w, 2.6)
        rect(s, x, 2.17, w, 0.62, fill=colr, line=None, radius=0.08)
        text(s, x + 0.15, 2.19, w - 0.3, 0.33, head, size=11.5, color=WHITE, bold=True, spacing=60)
        text(s, x + 0.15, 2.48, w - 0.3, 0.28, sub, size=9.5, color=WHITE)
        box = bullets(s, x + 0.12, 2.9, w - 0.22, 1.85, items, size=9.5, gap=6)
        for para in box.text_frame.paragraphs:
            para.line_spacing = 1.1
        x += w + 0.14
    rect(s, 0.42, 4.92, 9.9, 1.15, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.62, 4.99, 9.5, 0.26, "WHY NOW", size=10, bold=True, color=NAVY, spacing=200)
    text(s, 0.62, 5.25, 9.6, 0.8, [[("CPD drives sign-ups: ", {"bold": True}),
                                   ("an estimated 135 of FY27's 249 came from the Clinical Audit eDMs; 3,472 pharmacists did Care's CPD on AJP's site. ", {}),
                                   ("The inherited course setup is patched together: ", {"bold": True}),
                                   ("every new CPD course built on it adds more workarounds and risk, so the problem grows as CPD grows. "
                                    "Its registration gate (Restrict Content Pro) is out of date and no longer sold on its own.", {})]],
         size=10)
    rect(s, 10.47, 4.92, 2.45, 1.15, fill=PINK, line=None, radius=0.08)
    text(s, 10.47, 4.95, 2.45, 0.6, "~80 HRS", size=24, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    text(s, 10.47, 5.52, 2.45, 0.4, "AMJ '27", size=12, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    label(s, 0.42, 6.35, 3.0, "WHERE THIS SITS")
    chevrons = [
        ("FY27 \u2713  Theme, videos, licences in-house", GREEN),
        ("FY28  CPD platform + registration gate in-house", PINK),
        ("FY29+  Remaining code tidy, forms review", GREY),
    ]
    cx, cw = 2.55, 3.5
    for i, (t, colr) in enumerate(chevrons):
        shp = s.shapes.add_shape(MSO_SHAPE.PENTAGON if i == 0 else MSO_SHAPE.CHEVRON,
                                 Inches(cx + i * (cw - 0.1)), Inches(6.25), Inches(cw), Inches(0.45))
        shp.fill.solid()
        shp.fill.fore_color.rgb = colr
        shp.line.fill.background()
        shp.shadow.inherit = False
        text(s, cx + i * (cw - 0.1) + (0.15 if i == 0 else 0.3), 6.25, cw - 0.5, 0.45, t, size=9.5, color=WHITE, bold=True,
             anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Sign-ups: CAPH0107 JAS '26 HCP Results, FY27 to 30 Sep 2026; AJP figure is AJP's own reporting. Hours are Panwar Health estimates; cost to be finalised.")


def slide_rebuild_how(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="BRINGING CPD IN-HOUSE: how we'll do it, with no disruption for HCPs")
    steps = [
        ("Build alongside, not over the top", "The new platform is built and tested on our staging site while Care Connect runs as normal."),
        ("Move the Clinical Audit across", "Courses, progress, certificates and CPD history copied over, then checked record by record."),
        ("Test with the people who use it", "Panwar staff test every HCP journey and sign off audit approval and certificates."),
        ("Switch over in a quiet window", "E.g. a Monday evening. The old system stays read-only as a safety net."),
        ("Remove the old plugins and code", "Only once everything checks out. Leaves a clean, documented codebase."),
        ("First new SCORM course goes live", "Proof that publishing a new CPD course is now quick and simple."),
    ]
    for i, (head, body) in enumerate(steps):
        x = 0.42 + (i % 3) * 4.215
        y = 1.45 + (i // 3) * 2.0
        rect(s, x, y, 4.07, 1.85)
        rect(s, x + 0.2, y + 0.2, 0.55, 0.55, fill=TEAL if i < 5 else PINK, line=None, radius=0.5)
        text(s, x + 0.2, y + 0.2, 0.55, 0.55, str(i + 1), size=16, color=WHITE, bold=True, align=PP_ALIGN.CENTER,
             anchor=MSO_ANCHOR.MIDDLE)
        text(s, x + 0.9, y + 0.2, 3.0, 0.55, head, size=13, bold=True, anchor=MSO_ANCHOR.MIDDLE)
        text(s, x + 0.2, y + 0.92, 3.7, 0.85, body, size=12)
    rect(s, 0.42, 5.5, 12.5, 1.2, fill=SOFT_GREEN, line=None, radius=0.1)
    text(s, 0.62, 5.5, 12.1, 1.2, [[("CPD records are protected. ", {"bold": True}),
                                   ("Certificates, completion dates and CPD hours must match exactly. "
                                    "The current system stays in place until the new one matches it 1:1.", {})]],
         size=12, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Timing: AMJ '27, live before the LearnDash renewal in Oct 2027. New CPD course content per Panwar staff's CPD plan.")


def slide_rebuild_costs(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="BRINGING CPD IN-HOUSE: easier courses, full design control, lower running costs")
    label(s, 0.42, 1.45, 7.6, "WEBSITE BUILD TIME PER NEW CPD COURSE")
    stat_card(s, 0.42, 1.75, 3.725, 1.45, "Today, on the inherited LMS", "~26-32 hrs", "developer hours to put it on the site", value_color=AMBER, value_size=28)
    stat_card(s, 4.295, 1.75, 3.725, 1.45, "In-house", "~4-5 hrs", "developer hours to put it on the site", value_color=GREEN, value_size=28)
    rows = [
        ["New courses a year", "Inherited LMS", "In-house", "Dev hours saved"],
        ["2", "~60", "~10", "~50"],
        ["3", "~90", "~15", "~75"],
        ["4", "~120", "~20", "~100"],
    ]
    table(s, 0.42, 3.35, 7.6, [2.0, 1.9, 1.7, 2.0], rows, row_h=0.33, size=10.5, header_size=10.5,
          align=[PP_ALIGN.LEFT, PP_ALIGN.CENTER, PP_ALIGN.CENTER, PP_ALIGN.CENTER], bold_cols=(0, 3),
          fills={(r, 3): SOFT_GREEN for r in range(1, 4)})
    text(s, 0.42, 4.8, 7.6, 0.42, "These are developer hours only, to build each course into Care Connect. Writing, reviewing and "
                                   "accrediting the course content is separate and not included.", size=10.5, bold=True, color=NAVY)
    text(s, 0.42, 5.28, 7.6, 0.42, "The LMS inherited from the previous agency wires three third-party plugins together with custom code, "
                                   "tangled with registration and site code, so every new course needs a developer to rewire and retest it.",
         size=10, italic=True, color=INK)

    label(s, 8.3, 1.45, 4.62, "WHAT WE KEEP PAYING FOR TODAY")
    rect(s, 8.3, 1.75, 4.62, 2.15, fill=SOFT_AMBER, line=None, radius=0.05)
    bullets(s, 8.48, 1.92, 4.3, 1.9, [
        [("LearnDash licence: ", {"bold": True}), ("US$350 a year", {})],
        [("Registration gate plugin: ", {"bold": True}), ("now only sold in a ~US$300+ a year bundle", {})],
        [("No SCORM built in: ", {"bold": True}), ("LearnDash needs yet another add-on (e.g. ~US$79+ a year), one more plugin to license and maintain", {})],
        [("Look and feel ", {"bold": True}), ("locked to the plugin's templates", {})],
    ], size=10.5, gap=5)
    rect(s, 8.3, 4.05, 4.62, 1.65, fill=SOFT_GREEN, line=None, radius=0.05)
    text(s, 8.48, 4.05, 4.3, 1.65, [[("In-house: ", {"bold": True}),
                                    ("$0 licences, SCORM built in, course pages designed to match Care Connect. Saves ~A$1,000-1,300 a year in licences.", {})]],
         size=10.5, anchor=MSO_ANCHOR.MIDDLE)

    rect(s, 0.42, 5.8, 12.5, 0.9, fill=NAVY, line=None, radius=0.1)
    text(s, 0.62, 5.8, 12.1, 0.9, "Pays back from day one in a better HCP experience, SCORM support and a cleaner, untangled codebase. "
                                  "In dev time alone, it covers its ~80 hrs in about a year at 3\u20134 new courses a year.",
         size=12.5, color=WHITE, bold=True, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Hours are Panwar Health estimates of website build time, based on the 2026 Clinical Audit build (CAPH0150). USD list prices; AUD at ~1.55, will vary.")


def slide_timeline(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="FY28 ROADMAP: fix the foundations first, then switch on the database")
    qx, qw = 3.35, 2.39
    quarters = ["AMJ '27", "JAS '27", "OND '27", "JFM '28"]
    for i, q in enumerate(quarters):
        rect(s, qx + i * qw + 0.04, 1.45, qw - 0.08, 0.42, fill=NAVY, line=None, radius=0.15)
        text(s, qx + i * qw, 1.45, qw, 0.42, q, size=12, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    rows = [
        ("BAU (ongoing)", 0, 4, GREY, "Content, eDMs, reporting, maintenance"),
        ("Bringing CPD in-house", 0, 1, PINK, "CPD platform + gate  ·  ~80 hrs"),
        ("Mobile UX audit + fixes", 0, 2, PURPLE, "Audit AMJ, fixes JAS  ·  ~70 hrs"),
        ("Dietitian access", 1, 2, GREEN, "Sign-up + review  ·  ~11 hrs"),
        ("Always-on CRM plan", 1, 4, TEAL, "Build JAS, plays live from OND  ·  ~100 hrs"),
        ("Personalised landing page", 2, 4, NAVY, "Needs 6+ months of analytics data  ·  ~90 hrs"),
        ("New CPD courses", 0, 4, ORANGE, "Scope from Panwar staff; published on the new platform"),
    ]
    y = 2.0
    for name, start, end, colr, note in rows:
        text(s, 0.42, y, 2.85, 0.46, name, size=11.5, bold=True, anchor=MSO_ANCHOR.MIDDLE)
        rect(s, qx + start * qw + 0.04, y + 0.04, (end - start) * qw - 0.08, 0.38, fill=colr, line=None, radius=0.3)
        text(s, qx + start * qw + 0.1, y + 0.04, (end - start) * qw - 0.2, 0.38, note, size=10.5, color=WHITE, bold=True,
             anchor=MSO_ANCHOR.MIDDLE)
        y += 0.46
    label(s, 0.42, y + 0.15, 6, "BRAND TIE-INS")
    tie = [
        ("FESS: grow", "CRM play 1 announces the new kids range content as it lands", FESS_BLUE),
        ("Hydralyte: defend", "GLP-1 and diabetes resources surfaced to GPs on the personalised page", HYD_ORANGE),
        ("Rectogesic: promote the audit", "Onboarding and dormancy plays point new and lapsed GPs to the audit", RECTO_PURPLE),
    ]
    x = 0.42
    for head, body, colr in tie:
        rect(s, x, y + 0.45, 4.07, 6.7 - (y + 0.45))
        text(s, x + 0.18, y + 0.58, 3.75, 0.3, head, size=11.5, bold=True, color=colr)
        text(s, x + 0.18, y + 0.9, 3.75, 0.6, body, size=10.5)
        x += 4.215
    source(s, "Sequence: mobile fixes improve everything that follows; CRM needs consent live (Compliance fundamental); personalisation needs per-user analytics data.")


def slide_still_to_come(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="STILL TO COME IN FY27: what lands between now and 31 March 2027")
    cols = [
        ("OND '26  ·  OCT TO DEC", [
            ("CARE CONNECT", NAVY, ["Compliance live: consent pop-up, preferences, T&C update email",
                                    "Resource Hubs: design sign-off, then build"]),
            ("HYDRALYTE", HYD_ORANGE, ["Diabetes travel case study + TMR solus eDM (Diabetes Awareness Month)",
                                       "Sticks NPD trade campaign (AU) + NZ range campaign"]),
            ("RECTOGESIC", RECTO_PURPLE, ["Audit v2 changeover: enrolled users emailed 6 Nov, pop-up updated",
                                          "Old audit closes end of year; flyer + LinkedIn post"]),
            ("FESS / ZADITEN", FESS_BLUE, ["ENT direct mailer (full range, fax-back for samples)",
                                           "Allergy and Zaditen campaigns continue"]),
        ]),
        ("JFM '27  ·  JAN TO MAR", [
            ("CARE CONNECT", NAVY, ["Analytics fundamental: per-user journeys, Clarity, retention",
                                    "All 4 fundamentals complete by 31 Mar"]),
            ("HYDRALYTE", HYD_ORANGE, ["GLP-1 leave behind for APP (with Dr Angela Kwong)"]),
            ("THERATEARS", ZAD_GREEN, ["Launch support (timing TBC)"]),
            ("REPORTING", GREY, ["OND quarterly review", "JFM review + FY27 full-year wrap-up"]),
        ]),
    ]
    x = 0.42
    for head, groups in cols:
        rect(s, x, 1.45, 6.18, 0.5, fill=NAVY, line=None, radius=0.15)
        text(s, x, 1.45, 6.18, 0.5, head, size=13, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE,
             spacing=100)
        rect(s, x, 2.1, 6.18, 4.6)
        y = 2.28
        for g, colr, items in groups:
            text(s, x + 0.2, y, 5.8, 0.28, g, size=10.5, bold=True, color=colr, spacing=150)
            bullets(s, x + 0.2, y + 0.32, 5.8, 0.27 * len(items), items, size=11, gap=3)
            y += 0.32 + 0.27 * len(items) + 0.27
        x += 6.32
    source(s, "Plan as at 10 Nov 2026, from the JAS '26 OND priorities and current build status. Dates subject to Care approvals.")


def slide_fy28_targets(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="FY28 TARGETS: the brief's KPIs, and how we'll hit each one")
    subtitle(s, "Care's FY28 brief keeps registrations at 349 and makes CPD, stickiness and return visits the new focus.", y=1.2)
    rows = [
        ["KPI", "FY27 so far", "FY28 target", "How we'll hit it in FY28"],
        ["eDM click-through rate", "6.6% (publisher-reported)", "5%", "Match each eDM to the right publisher; CRM emails (Rec 2)"],
        ["Traffic to Care Connect", "AMJ +74%, JAS +198% YoY", "+5% YoY", "Personalised landing page (Rec 3); CRM emails (Rec 2)"],
        ["New registrations", "249 to 30 Sep (target 349)", "349", "CPD in-house (Rec 1); audit eDMs; open sign-up to dietitians (Rec 5)"],
        ["CPD, stickiness + return visits", "New for FY28", "Tracked from Apr 2027", "CPD in-house (Rec 1); CRM plays (Rec 2); mobile fixes (Rec 4)"],
    ]
    table(s, 0.42, 1.95, 12.5, [3.0, 2.9, 1.9, 4.7], rows, row_h=0.62, size=12, header_size=12,
          align=[PP_ALIGN.LEFT, PP_ALIGN.LEFT, PP_ALIGN.CENTER, PP_ALIGN.LEFT], bold_cols=(0, 2))
    rect(s, 0.42, 5.45, 12.5, 1.25, fill=SOFT_BLUE, line=None, radius=0.1)
    text(s, 0.62, 5.45, 12.1, 1.25, [[("Every recommendation that follows maps to at least one of these targets. ", {"bold": True}),
                                     ("Return visits and stickiness are measured with the per-user analytics set up in the "
                                      "Analytics fundamental (live by 31 March 2027).", {})]],
         size=12, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Targets: Care Pharma HCP FY28 Brief (8 Nov 2026). FY27 figures: " + QBR_SOURCE[len("Source: "):])


def slide_dietitians(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="RECOMMENDATION 5: Opening Care Connect to dietitians")
    subtitle(s, "Dietitians are a new FY28 audience for Care and key to Hydralyte's GLP-1 and diabetes education. "
                "They have no AHPRA number, so we add a dietitian sign-up "
                "that Panwar staff check by hand against the Dietitians Australia register.", y=1.2, size=12)
    steps = [
        ("Tick the box", "On the registration form, \u201cI'm a dietitian\u201d swaps the AHPRA field for an APD Registration ID."),
        ("Access from day one", "A dietitian gets the same access as any other HCP when they sign up, and every new sign-up is checked (step 3)."),
        ("We check the register", "New dietitian sign-ups appear on an admin page. Panwar staff search the APD register by name and state and match the ID."),
        ("Remove bad actors", "No match: the account and details are deleted. If sign-ups are ever abused, new dietitian accounts start paused, with no access until we approve them."),
    ]
    w = (12.5 - 3 * 0.15) / 4
    for i, (head, body) in enumerate(steps):
        x = 0.42 + i * (w + 0.15)
        rect(s, x, 2.0, w, 2.1)
        rect(s, x + 0.2, 2.2, 0.5, 0.5, fill=GREEN, line=None, radius=0.5)
        text(s, x + 0.2, 2.2, 0.5, 0.5, str(i + 1), size=15, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        text(s, x + 0.82, 2.2, w - 0.95, 0.5, head, size=12.5, bold=True, anchor=MSO_ANCHOR.MIDDLE)
        text(s, x + 0.2, 2.85, w - 0.35, 1.2, body, size=11)
    label(s, 0.42, 4.3, 5.0, "WHAT DIETITIANS GET")
    rect(s, 0.42, 4.6, 4.95, 2.1, fill=SOFT_GREEN, line=None, radius=0.08)
    bullets(s, 0.6, 4.7, 4.65, 1.95, [
        "Their own section on Care Connect",
        "Content chosen for them by Panwar (GLP-1 and Hydralyte are the obvious start)",
        "A ready audience for a Dietitian Connection partnership",
    ], size=11, gap=5)
    label(s, 5.52, 4.3, 5.0, "QUESTIONS FOR CARE")
    rect(s, 5.52, 4.6, 4.95, 2.1, fill=SOFT_AMBER, line=None, radius=0.08)
    bullets(s, 5.7, 4.7, 4.65, 1.95, [
        "Which content can dietitians see?",
        "Do we want CPD for dietitians? It needs separate accreditation, as they log CPD with Dietitians Australia.",
    ], size=11, gap=5)
    rect(s, 10.62, 4.6, 2.3, 2.1, fill=PINK, line=None, radius=0.08)
    text(s, 10.62, 4.85, 2.3, 0.8, "~11 HRS", size=24, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    text(s, 10.62, 5.6, 2.3, 0.8, ["JAS '27", "after Rec 1"], size=12, color=WHITE, bold=True, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Hours are Panwar Health estimates: form ~2, skip AHPRA check ~2, review page + delete email ~3, role + section ~1, paused-account switch ~1, testing ~2. "
              "Register: Dietitians Australia, Register of Accredited Practising Dietitians.")


def slide_consent(prs, layout):
    s = new_slide(prs, layout)
    title(s, size=20, t="COMPLIANCE LIVE: early results from the consent roll-out")
    subtitle(s, "Every HCP is now asked for marketing consent when they log in. First two days, 16 to 18 November:", y=1.2)
    cards = [
        ("HCPs answered", "23", "in the first two days", NAVY),
        ("Said Yes", "16", "70% of answers", GREEN),
        ("Said No", "7", "30% of answers", PINK),
        ("Emailed the update", "1,634", "Terms and Privacy email, 17 Nov", TEAL),
    ]
    w = (12.5 - 3 * 0.15) / 4
    for i, (cap, val, note, colr) in enumerate(cards):
        stat_card(s, 0.42 + i * (w + 0.15), 1.8, w, 1.55, cap, val, note, value_color=colr, value_size=30)
    label(s, 0.42, 3.6, 6, "HOW THEY ANSWERED")
    rect(s, 0.42, 3.9, 6.18, 1.6)
    bullets(s, 0.6, 4.0, 5.85, 1.45, [
        "21 on the pop-up when they logged in",
        "2 on Edit Your Profile",
        "No one has changed their answer",
    ], size=12, gap=5)
    label(s, 6.74, 3.6, 6, "WHAT IT MEANS FOR THE DATABASE")
    rect(s, 6.74, 3.9, 6.18, 1.6)
    bullets(s, 6.92, 4.0, 5.85, 1.45, [
        "April audit: only 19 HCPs had valid marketing consent",
        "New system: 16 Yes in two days, each logged with wording, date and version",
        "Most HCPs answer when they next log in, so the numbers build over time",
    ], size=12, gap=5)
    rect(s, 0.42, 5.7, 12.5, 1.0, fill=SOFT_BLUE, line=None, radius=0.1)
    text(s, 0.62, 5.7, 12.1, 1.0, [[("Next: ", {"bold": True}),
                                   ("we track answers weekly and report them at each quarterly review. "
                                    "The Yes list is who the CRM plays (Recommendation 2) can email.", {})]],
         size=12.5, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Source: Care Connect consent log, 18 Nov 2026 10:45am AEST; Panwar, Care and test accounts excluded. "
              "April audit: consent review of 1,366 registered HCPs (30 Apr 2026).")


def airy(slide, body=1.2, cell=1.1, extra_gap=2):
    """Open up line spacing on every text box and table cell that hasn't set its own."""
    def frames():
        for shp in slide.shapes:
            if shp.has_text_frame and shp.name not in ("Title", "Source"):
                yield shp.text_frame, body
            if shp.has_table:
                for row in shp.table.rows:
                    for c in row.cells:
                        yield c.text_frame, cell
    for tf, ls in frames():
        multi = len(tf.paragraphs) > 1
        for para in tf.paragraphs:
            if para.line_spacing is None:
                para.line_spacing = ls
            if multi and para.space_after is not None:
                para.space_after = Pt(para.space_after.pt + extra_gap)


def main() -> None:
    prs, layout = open_template()
    builds = [slide_scorecard, slide_by_brand, slide_media_learnings, slide_top_assets, slide_fundamentals, slide_platform,
              slide_budget, slide_bau, slide_licences, slide_rebuild_what, slide_rebuild_costs, slide_rebuild_how, slide_timeline,
              slide_still_to_come, slide_fy28_targets, slide_dietitians, slide_consent]
    for build in builds:
        build(prs, layout)
    for slide in prs.slides:
        airy(slide)
    dest = Path(sys.argv[1])
    prs.save(str(dest))
    print(f"wrote {dest} ({len(prs.slides)} slides)")
    for i, b in enumerate(builds, 1):
        print(i, b.__name__)


if __name__ == "__main__":
    main()

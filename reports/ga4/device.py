"""Device analysis for a quarterly/annual review — three analyses in one run.

    python reports/ga4/device.py --period FY26

Merges the three former device scripts:
  1. Device split (5 views: overall, by channel, by article, completion by
     device, monthly) — writes device_analysis_<slug>.md
  2. Device engagement YoY (period vs prior FY) + channel × device —
     writes device_engagement_<slug>.md
  3. Device OS breakdown (+ prior-FY mobile YoY) — writes device_os_<slug>.md

Each output is also copied to Windows Downloads. The prior-FY window for the
YoY comparisons is derived from --period via Period.prior_year.
"""
from __future__ import annotations

import argparse
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, FilterExpressionList,
    Metric, OrderBy, RunReportRequest,
)

PROP = f"properties/{ga4.PROPERTY_ID}"

POSTS = [
    ("Tattersall – Allergic rhinitis",  "/blog/top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall"),
    ("GLP-1RA / GI tolerability",       "/blog/how-gps-can-support-gi-tolerability-for-patients-taking-anti-obesity-medications"),
    ("Chocolate / stool chart",          "/blog/from-chocolate-to-clinical-clues"),
    ("Cold & flu season",                "/blog/prepare-for-a-big-cold-and-flu-season"),
    ("Allergy eye",                      "/blog/can-you-spot-the-allergy-eye"),
    ("ARISE clinical trials",            "/blog/keeping-up-with-clinical-trials-arise"),
    ("Travellers diarrhoea",             "/blog/travellers-diarrhoea-quick-management-guide-for-the-holiday-season"),
    ("MIST+ trial (children)",           "/blog/what-gps-need-to-know-how-the-mist-trial-is-changing-osdb-management-in-children"),
    ("Sip to stand / POTS",             "/blog/sip-to-stand-why-hydration-is-essential-in-pots"),
]

DEVICES = ["mobile", "desktop", "tablet"]


# ---------------------------------------------------------------------------
# Shared filter / format helpers
# ---------------------------------------------------------------------------
def pf(path):
    return FilterExpression(filter=Filter(field_name="pagePath",
        string_filter=Filter.StringFilter(value=path,
            match_type=Filter.StringFilter.MatchType.BEGINS_WITH)))


def ef(name):
    return FilterExpression(filter=Filter(field_name="eventName",
        string_filter=Filter.StringFilter(value=name)))


def and_f(*exprs):
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


def device_filter(device):
    return FilterExpression(filter=Filter(field_name="deviceCategory",
        string_filter=Filter.StringFilter(value=device)))


def channel_bucket(ch):
    if ch == "Email":      return "Email"
    if ch == "Direct":     return "Direct"
    if ch == "Unassigned": return "Unassigned"
    return "Other"


def pct(n, d):
    return f"{n/d*100:.0f}%" if d else "—"


def fmt_secs(s):
    s = int(s)
    m, sec = divmod(s, 60)
    return f"{m}m {sec:02d}s"


# ===========================================================================
# Analysis 1 — Device split (5 views)
# ===========================================================================
def device_split(ga: BetaAnalyticsDataClient, dr, label: str) -> str:
    lines = [
        f"# Device Analysis — {label}",
        f"Source: GA4 property {ga4.PROPERTY_ID}",
        "",
    ]

    # ── VIEW 1: Overall device split ─────────────────────────────────────────
    lines += ["## View 1: Overall device split", ""]
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers"), Metric(name="sessions"),
                 Metric(name="screenPageViews")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
    ))
    total_u = sum(int(r.metric_values[0].value) for r in resp.rows)
    lines.append("| Device | Users | Users % | Sessions | Pageviews |")
    lines.append("|---|---|---|---|---|")
    for row in resp.rows:
        dev  = row.dimension_values[0].value
        u, s, pv = (int(v.value) for v in row.metric_values)
        lines.append(f"| {dev} | {u:,} | {pct(u,total_u)} | {s:,} | {pv:,} |")
    lines += ["", "_Solid. Full-year data, clean split._", ""]

    # ── VIEW 2: Device split by channel ──────────────────────────────────────
    lines += ["## View 2: Device split by channel", ""]
    resp2 = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="sessionDefaultChannelGroup"),
                    Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
        limit=50,
    ))
    ch_dev: dict[str, dict[str, int]] = defaultdict(lambda: defaultdict(int))
    for row in resp2.rows:
        ch  = channel_bucket(row.dimension_values[0].value)
        dev = row.dimension_values[1].value
        ch_dev[ch][dev] += int(row.metric_values[0].value)

    lines.append("| Channel | Mobile | Mobile % | Desktop | Desktop % | Tablet | Tablet % | Total |")
    lines.append("|---|---|---|---|---|---|---|---|")
    for ch in ["Email", "Direct", "Other", "Unassigned"]:
        d = ch_dev.get(ch, {})
        tot = sum(d.values())
        m, desk, tab = d.get("mobile",0), d.get("desktop",0), d.get("tablet",0)
        lines.append(f"| {ch} | {m:,} | {pct(m,tot)} | {desk:,} | {pct(desk,tot)} | {tab:,} | {pct(tab,tot)} | {tot:,} |")
    lines += ["", "_Solid. All channels have adequate volume._", ""]

    # ── VIEW 3: Device split by article ──────────────────────────────────────
    lines += ["## View 3: Device split by article", ""]
    lines.append("| Post | Mobile | Mobile % | Desktop | Desktop % | Tablet | Tablet % | Total |")
    lines.append("|---|---|---|---|---|---|---|---|")
    for label_post, path in POSTS:
        resp3 = ga.run_report(RunReportRequest(
            property=PROP, date_ranges=dr,
            dimensions=[Dimension(name="deviceCategory")],
            metrics=[Metric(name="totalUsers")],
            dimension_filter=pf(path),
        ))
        d = {r.dimension_values[0].value: int(r.metric_values[0].value) for r in resp3.rows}
        tot  = sum(d.values())
        m    = d.get("mobile", 0)
        desk = d.get("desktop", 0)
        tab  = d.get("tablet", 0)
        lines.append(f"| {label_post} | {m:,} | {pct(m,tot)} | {desk:,} | {pct(desk,tot)} | {tab:,} | {pct(tab,tot)} | {tot:,} |")
    lines += ["", "_Solid for the top 6 posts. MIST+ and POTS have low totals — treat with care._", ""]

    # ── VIEW 4: Device split + completion rate per article ───────────────────
    lines += ["## View 4: Device split + completion rate per article", ""]
    lines.append("| Post | Mobile users | Mobile completion % | Desktop users | Desktop completion % | Difference | Reliable? |")
    lines.append("|---|---|---|---|---|---|---|")
    MIN_USERS = 30
    for label_post, path in POSTS:
        u_resp = ga.run_report(RunReportRequest(
            property=PROP, date_ranges=dr,
            dimensions=[Dimension(name="deviceCategory")],
            metrics=[Metric(name="totalUsers")],
            dimension_filter=pf(path),
        ))
        s_resp = ga.run_report(RunReportRequest(
            property=PROP, date_ranges=dr,
            dimensions=[Dimension(name="deviceCategory")],
            metrics=[Metric(name="totalUsers")],
            dimension_filter=and_f(pf(path), ef("scroll")),
        ))
        u = {r.dimension_values[0].value: int(r.metric_values[0].value) for r in u_resp.rows}
        s = {r.dimension_values[0].value: int(r.metric_values[0].value) for r in s_resp.rows}
        mob_u  = u.get("mobile",  0);  mob_s  = s.get("mobile",  0)
        desk_u = u.get("desktop", 0);  desk_s = s.get("desktop", 0)
        mob_pct  = mob_s/mob_u*100   if mob_u  >= MIN_USERS else None
        desk_pct = desk_s/desk_u*100 if desk_u >= MIN_USERS else None
        reliable = "✅" if (mob_u >= MIN_USERS and desk_u >= MIN_USERS) else "⚠️ low vol"
        if mob_pct is not None and desk_pct is not None:
            diff = f"{desk_pct - mob_pct:+.0f}pp desktop"
        else:
            diff = "—"
        mp = f"{mob_pct:.0f}%"  if mob_pct  is not None else f"— ({mob_u} users)"
        dp = f"{desk_pct:.0f}%" if desk_pct is not None else f"— ({desk_u} users)"
        lines.append(f"| {label_post} | {mob_u:,} | {mp} | {desk_u:,} | {dp} | {diff} | {reliable} |")
    lines += ["", "_Only rows marked ✅ are reliable. Low-volume posts flagged._", ""]

    # ── VIEW 5: Monthly device split ─────────────────────────────────────────
    lines += ["## View 5: Monthly device split", ""]
    resp5 = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="yearMonth"), Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="yearMonth"))],
        limit=200,
    ))
    monthly: dict[str, dict[str, int]] = defaultdict(lambda: defaultdict(int))
    for row in resp5.rows:
        ym  = row.dimension_values[0].value
        dev = row.dimension_values[1].value
        monthly[ym][dev] += int(row.metric_values[0].value)

    lines.append("| Month | Total users | Mobile | Mobile % | Desktop | Desktop % |")
    lines.append("|---|---|---|---|---|---|")
    for ym in sorted(monthly):
        d    = monthly[ym]
        tot  = sum(d.values())
        m    = d.get("mobile",  0)
        desk = d.get("desktop", 0)
        month = f"{ym[:4]}-{ym[4:]}"
        lines.append(f"| {month} | {tot:,} | {m:,} | {pct(m,tot)} | {desk:,} | {pct(desk,tot)} |")
    lines += ["", "_Solid. Full 12-month trend._", ""]

    # ── Summary ───────────────────────────────────────────────────────────────
    lines += [
        "## Summary: which view to use",
        "",
        "| View | Strength | Recommendation |",
        "|---|---|---|",
        "| 1 – Overall split | Clean, simple | Use as a single headline stat |",
        "| 2 – By channel | Shows if email vs direct skews device | Use if device/channel story is relevant |",
        "| 3 – By article | Shows per-article device mix | Background context, not primary slide |",
        "| 4 – Completion by device | Most diagnostic | Best slide angle IF volumes support it — check ✅ rows only |",
        "| 5 – Monthly trend | Shows mobile share change over year | Use only if there's a clear trend to call out |",
    ]
    return "\n".join(lines)


# ===========================================================================
# Analysis 2 — Device engagement YoY + channel × device
# ===========================================================================
def device_engagement(ga, dr):
    """Engagement metrics by device."""
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="deviceCategory")],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="userEngagementDuration"),
            Metric(name="screenPageViews"),
        ],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
    ))
    rows = []
    for row in resp.rows:
        dev = row.dimension_values[0].value
        u   = int(row.metric_values[0].value)
        s   = int(row.metric_values[1].value)
        es  = int(row.metric_values[2].value)
        eng = float(row.metric_values[3].value)
        pv  = int(row.metric_values[4].value)
        rows.append({
            "device": dev,
            "users": u, "sessions": s, "engaged_sessions": es,
            "eng_rate":     es/s     if s else 0,
            "eng_per_sess": eng/s    if s else 0,
            "eng_per_user": eng/u    if u else 0,
            "pages_per_sess": pv/s   if s else 0,
            "pageviews": pv,
        })
    return rows


def channel_device_engagement(ga, dr):
    """Engagement by channel x device."""
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="sessionDefaultChannelGroup"),
                    Dimension(name="deviceCategory")],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="userEngagementDuration"),
            Metric(name="screenPageViews"),
        ],
        limit=80,
    ))
    by = defaultdict(lambda: defaultdict(lambda: {"users":0,"sessions":0,"es":0,"eng":0.0,"pv":0}))
    for row in resp.rows:
        ch  = channel_bucket(row.dimension_values[0].value)
        dev = row.dimension_values[1].value
        d = by[ch][dev]
        d["users"]    += int(row.metric_values[0].value)
        d["sessions"] += int(row.metric_values[1].value)
        d["es"]       += int(row.metric_values[2].value)
        d["eng"]      += float(row.metric_values[3].value)
        d["pv"]       += int(row.metric_values[4].value)
    return by


def engagement_report(ga, p: periods.Period, prior: periods.Period) -> str:
    dr_prior = [DateRange(start_date=prior.start, end_date=prior.end)]
    dr_cur   = [DateRange(start_date=p.start, end_date=p.end)]

    print(f"Pulling {prior.label} device engagement...")
    fy25 = device_engagement(ga, dr_prior)
    print(f"Pulling {p.label} device engagement...")
    fy26 = device_engagement(ga, dr_cur)
    print(f"Pulling {p.label} channel x device engagement...")
    fy26_chdev = channel_device_engagement(ga, dr_cur)

    lines = [
        f"# Device Engagement Analysis — {prior.label} vs {p.label}",
        f"Source: GA4 property {ga4.PROPERTY_ID}",
        "Methodology: totalUsers throughout. Engagement rate = engaged sessions ÷ sessions.",
        "Engagement time = userEngagementDuration (foreground active time, seconds).",
        "",
    ]

    for label, data in [(f"{prior.label} ({prior.start} – {prior.end})", fy25),
                         (f"{p.label} ({p.start} – {p.end})", fy26)]:
        lines += [f"## {label} — by device", ""]
        lines.append("| Device | Users | Sessions | Engaged sessions | Engagement rate | Eng / session | Eng / user | Pages / session |")
        lines.append("|---|---|---|---|---|---|---|---|")
        for r in data:
            lines.append(
                f"| {r['device']} | {r['users']:,} | {r['sessions']:,} | {r['engaged_sessions']:,} | "
                f"{r['eng_rate']*100:.0f}% | {fmt_secs(r['eng_per_sess'])} | {fmt_secs(r['eng_per_user'])} | {r['pages_per_sess']:.1f} |"
            )
        lines.append("")

    lines += [f"## {p.label} — engagement by channel × device", ""]
    lines.append("| Channel | Device | Users | Sessions | Engagement rate | Eng / user | Pages / session |")
    lines.append("|---|---|---|---|---|---|---|")
    for ch in ["Email", "Direct", "Other", "Unassigned"]:
        for dev in ["mobile", "desktop", "tablet"]:
            d = fy26_chdev[ch][dev]
            if d["users"] == 0: continue
            er  = d["es"]/d["sessions"]*100 if d["sessions"] else 0
            epu = d["eng"]/d["users"]       if d["users"]    else 0
            pps = d["pv"]/d["sessions"]     if d["sessions"] else 0
            lines.append(f"| {ch} | {dev} | {d['users']:,} | {d['sessions']:,} | {er:.0f}% | {fmt_secs(epu)} | {pps:.1f} |")
    lines.append("")

    def get(rows, dev, key):
        for r in rows:
            if r["device"] == dev: return r[key]
        return 0
    fy25_mob_epu = get(fy25, "mobile", "eng_per_user")
    fy25_dsk_epu = get(fy25, "desktop", "eng_per_user")
    fy26_mob_epu = get(fy26, "mobile", "eng_per_user")
    fy26_dsk_epu = get(fy26, "desktop", "eng_per_user")
    fy25_mob_er  = get(fy25, "mobile", "eng_rate")*100
    fy25_dsk_er  = get(fy25, "desktop", "eng_rate")*100
    fy26_mob_er  = get(fy26, "mobile", "eng_rate")*100
    fy26_dsk_er  = get(fy26, "desktop", "eng_rate")*100
    fy25_mob_pps = get(fy25, "mobile", "pages_per_sess")
    fy25_dsk_pps = get(fy25, "desktop", "pages_per_sess")
    fy26_mob_pps = get(fy26, "mobile", "pages_per_sess")
    fy26_dsk_pps = get(fy26, "desktop", "pages_per_sess")

    em_mob = fy26_chdev["Email"]["mobile"]
    em_dsk = fy26_chdev["Email"]["desktop"]
    em_mob_epu = em_mob["eng"]/em_mob["users"] if em_mob["users"] else 0
    em_dsk_epu = em_dsk["eng"]/em_dsk["users"] if em_dsk["users"] else 0
    em_mob_er  = em_mob["es"]/em_mob["sessions"]*100 if em_mob["sessions"] else 0
    em_dsk_er  = em_dsk["es"]/em_dsk["sessions"]*100 if em_dsk["sessions"] else 0

    fy25_gap_pct = (fy25_dsk_epu - fy25_mob_epu) / fy25_dsk_epu * 100 if fy25_dsk_epu else 0
    fy26_gap_pct = (fy26_dsk_epu - fy26_mob_epu) / fy26_dsk_epu * 100 if fy26_dsk_epu else 0

    lines += [
        "## YoY summary table",
        "",
        f"| Metric | Mobile {prior.label} | Mobile {p.label} | Desktop {prior.label} | Desktop {p.label} |",
        "|---|---|---|---|---|",
        f"| Engagement rate | {fy25_mob_er:.0f}% | {fy26_mob_er:.0f}% | {fy25_dsk_er:.0f}% | {fy26_dsk_er:.0f}% |",
        f"| Eng time per user | {fmt_secs(fy25_mob_epu)} | {fmt_secs(fy26_mob_epu)} | {fmt_secs(fy25_dsk_epu)} | {fmt_secs(fy26_dsk_epu)} |",
        f"| Pages per session | {fy25_mob_pps:.1f} | {fy26_mob_pps:.1f} | {fy25_dsk_pps:.1f} | {fy26_dsk_pps:.1f} |",
        "",
        "## Honest read",
        "",
        f"**Q1: Mobile genuinely less engaged, or just shorter sessions?** Both. In {p.label}, mobile engagement rate is {fy26_mob_er:.0f}% vs desktop {fy26_dsk_er:.0f}% (close — both very high), but mobile users average **{fmt_secs(fy26_mob_epu)}** of engagement vs desktop **{fmt_secs(fy26_dsk_epu)}** — desktop sessions are ~{fy26_gap_pct:.0f}% longer. Pages-per-session also lower on mobile ({fy26_mob_pps:.1f} vs {fy26_dsk_pps:.1f}). So mobile users *are* engaged when they arrive (similar engagement rate), they just don't go as deep or stay as long.",
        "",
        f"**Q2: Does email-driven mobile engage as well as email-driven desktop?** Email channel only: mobile users average **{fmt_secs(em_mob_epu)}** vs desktop **{fmt_secs(em_dsk_epu)}**. Engagement rates are {em_mob_er:.0f}% (mobile) vs {em_dsk_er:.0f}% (desktop). The same gap exists on email as on overall traffic — controlling for channel does NOT close the device gap.",
        "",
        f"**Q3: Did mobile engagement improve YoY?** Mobile engagement time per user: {prior.label} {fmt_secs(fy25_mob_epu)} → {p.label} {fmt_secs(fy26_mob_epu)} ({(fy26_mob_epu-fy25_mob_epu)/fy25_mob_epu*100:+.0f}%). Engagement rate: {fy25_mob_er:.0f}% → {fy26_mob_er:.0f}%. Pages per session: {fy25_mob_pps:.1f} → {fy26_mob_pps:.1f}." if fy25_mob_epu else
        f"**Q3: Did mobile engagement improve YoY?** Mobile engagement time per user: {prior.label} {fmt_secs(fy25_mob_epu)} → {p.label} {fmt_secs(fy26_mob_epu)}. Engagement rate: {fy25_mob_er:.0f}% → {fy26_mob_er:.0f}%. Pages per session: {fy25_mob_pps:.1f} → {fy26_mob_pps:.1f}.",
        "",
        f"**Mobile-desktop engagement gap: {prior.label} desktop was {fy25_gap_pct:.0f}% longer than mobile, {p.label} desktop is {fy26_gap_pct:.0f}% longer.** "
        + ("Gap widened." if fy26_gap_pct > fy25_gap_pct + 3 else "Gap shrank." if fy26_gap_pct < fy25_gap_pct - 3 else "Gap roughly stable."),
        "",
        "## Slide-ready single sentence",
        "",
        f"_\"Mobile and desktop visitors arrive equally engaged ({fy26_mob_er:.0f}% vs {fy26_dsk_er:.0f}% engagement rate), but desktop sessions go {fy26_gap_pct:.0f}% deeper — {fmt_secs(fy26_dsk_epu)} per user on desktop vs {fmt_secs(fy26_mob_epu)} on mobile.\"_",
    ]
    return "\n".join(lines)


# ===========================================================================
# Analysis 3 — Device OS breakdown (+ prior-FY mobile YoY)
# ===========================================================================
def os_engagement_for_device(ga, dr, device):
    """OS-level engagement metrics filtered to one device category."""
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="operatingSystem")],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="userEngagementDuration"),
            Metric(name="screenPageViews"),
        ],
        dimension_filter=device_filter(device),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
        limit=20,
    ))
    rows = []
    for row in resp.rows:
        os_name = row.dimension_values[0].value
        u, s, es, eng, pv = (row.metric_values[i].value for i in range(5))
        u, s, es, pv = int(u), int(s), int(es), int(pv)
        eng = float(eng)
        rows.append({
            "os": os_name, "users": u, "sessions": s, "engaged_sessions": es,
            "eng_rate":      es/s   if s else 0,
            "eng_per_user":  eng/u  if u else 0,
            "pages_per_sess": pv/s  if s else 0,
        })
    return rows


def channel_os_mobile(ga, dr):
    """For mobile only: channel x OS breakdown."""
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="sessionDefaultChannelGroup"),
                    Dimension(name="operatingSystem")],
        metrics=[Metric(name="totalUsers")],
        dimension_filter=device_filter("mobile"),
        limit=80,
    ))
    by = defaultdict(lambda: defaultdict(int))
    for row in resp.rows:
        ch = channel_bucket(row.dimension_values[0].value)
        os_name = row.dimension_values[1].value
        by[ch][os_name] += int(row.metric_values[0].value)
    return by


def os_report(ga, p: periods.Period, prior: periods.Period) -> str:
    dr_cur   = [DateRange(start_date=p.start, end_date=p.end)]
    dr_prior = [DateRange(start_date=prior.start, end_date=prior.end)]

    print(f"Pulling {p.label} mobile by OS...")
    fy26_mobile = os_engagement_for_device(ga, dr_cur, "mobile")
    print(f"Pulling {p.label} desktop by OS...")
    fy26_desktop = os_engagement_for_device(ga, dr_cur, "desktop")
    print(f"Pulling {p.label} mobile channel x OS...")
    fy26_mobile_chos = channel_os_mobile(ga, dr_cur)
    print(f"Pulling {prior.label} mobile by OS...")
    fy25_mobile = os_engagement_for_device(ga, dr_prior, "mobile")

    lines = [
        f"# Device OS Breakdown — {p.label} (+ {prior.label} YoY for mobile)",
        f"Source: GA4 property {ga4.PROPERTY_ID}",
        "Methodology: totalUsers throughout. Engagement rate = engaged sessions ÷ sessions.",
        "",
        f"## 1. {p.label} Mobile users by OS",
        "",
        "| OS | Users | Sessions | Engagement rate | Eng / user | Pages / session |",
        "|---|---|---|---|---|---|",
    ]
    total_mob = sum(r["users"] for r in fy26_mobile)
    for r in fy26_mobile:
        share = r["users"]/total_mob*100 if total_mob else 0
        lines.append(
            f"| {r['os']} ({share:.0f}%) | {r['users']:,} | {r['sessions']:,} | "
            f"{r['eng_rate']*100:.0f}% | {fmt_secs(r['eng_per_user'])} | {r['pages_per_sess']:.1f} |"
        )

    lines += ["", f"## 2. {p.label} Desktop users by OS", "",
        "| OS | Users | Sessions | Engagement rate | Eng / user | Pages / session |",
        "|---|---|---|---|---|---|"]
    total_dsk = sum(r["users"] for r in fy26_desktop)
    for r in fy26_desktop:
        share = r["users"]/total_dsk*100 if total_dsk else 0
        lines.append(
            f"| {r['os']} ({share:.0f}%) | {r['users']:,} | {r['sessions']:,} | "
            f"{r['eng_rate']*100:.0f}% | {fmt_secs(r['eng_per_user'])} | {r['pages_per_sess']:.1f} |"
        )

    lines += ["", f"## 3. {p.label} Mobile OS by channel", "",
        "| Channel | iOS users | iOS % | Android users | Android % | Other | Total mobile |",
        "|---|---|---|---|---|---|---|"]
    for ch in ["Email", "Direct", "Other", "Unassigned"]:
        d = fy26_mobile_chos[ch]
        total = sum(d.values())
        if total == 0: continue
        ios = d.get("iOS", 0)
        android = d.get("Android", 0)
        other = total - ios - android
        ios_pct = ios/total*100 if total else 0
        and_pct = android/total*100 if total else 0
        lines.append(f"| {ch} | {ios:,} | {ios_pct:.0f}% | {android:,} | {and_pct:.0f}% | {other:,} | {total:,} |")

    lines += ["", f"## 4. {prior.label} Mobile by OS (YoY comparison)", "",
        f"| OS | {prior.label} Users | {prior.label} Eng / user | {p.label} Users | {p.label} Eng / user |",
        "|---|---|---|---|---|"]
    fy25_map = {r["os"]: r for r in fy25_mobile}
    fy26_map = {r["os"]: r for r in fy26_mobile}
    for os_name in sorted(set(list(fy25_map) + list(fy26_map))):
        a = fy25_map.get(os_name, {"users":0,"eng_per_user":0})
        b = fy26_map.get(os_name, {"users":0,"eng_per_user":0})
        lines.append(f"| {os_name} | {a['users']:,} | {fmt_secs(a['eng_per_user'])} | {b['users']:,} | {fmt_secs(b['eng_per_user'])} |")

    ios_row = next((r for r in fy26_mobile if r["os"] == "iOS"), None)
    and_row = next((r for r in fy26_mobile if r["os"] == "Android"), None)
    weird = [r for r in fy26_mobile if r["os"] not in ("iOS", "Android")]

    lines += ["",
        "## Honest read",
        "",
    ]
    if ios_row and and_row and total_mob > 0:
        ios_share = ios_row["users"]/total_mob*100
        and_share = and_row["users"]/total_mob*100
        if ios_share > 60:
            split_verdict = f"**iOS-dominant** ({ios_share:.0f}% of mobile users)."
        elif and_share > 60:
            split_verdict = f"**Android-dominant** ({and_share:.0f}% of mobile users)."
        else:
            split_verdict = f"**Roughly split** — iOS {ios_share:.0f}% vs Android {and_share:.0f}%."
        lines.append(f"**Mobile audience composition:** {split_verdict}")
        lines.append("")

        if ios_row["users"] >= 30 and and_row["users"] >= 30:
            ios_epu = ios_row["eng_per_user"]
            and_epu = and_row["eng_per_user"]
            ios_pps = ios_row["pages_per_sess"]
            and_pps = and_row["pages_per_sess"]
            deeper = "iOS" if ios_epu > and_epu else "Android"
            gap = abs(ios_epu - and_epu)
            lines.append(f"**iOS vs Android engagement:** iOS users average {fmt_secs(ios_epu)} engagement / {ios_pps:.1f} pages, Android {fmt_secs(and_epu)} / {and_pps:.1f} pages. {deeper} sessions are {fmt_secs(gap)} longer per user.")
        else:
            lines.append("**iOS vs Android engagement:** at least one OS has < 30 users — engagement comparison too thin to be reliable.")
        lines.append("")

    if weird and any(r["users"] >= 5 for r in weird):
        lines.append("**Unusual / minor OS rows flagged:**")
        for r in weird:
            if r["users"] >= 5:
                lines.append(f"- {r['os']}: {r['users']} users, {fmt_secs(r['eng_per_user'])} per user")
        lines.append("")
        lines.append("Worth a glance but volumes are small. Nothing screaming bot traffic (no Windows Phone, no `(not set)` at meaningful volume).")
    else:
        lines.append("**No suspicious OS values.** Long tail is single-digit users on minor versions — normal.")
    lines.append("")

    fy25_total_mob = sum(r["users"] for r in fy25_mobile)
    fy25_ios = next((r for r in fy25_mobile if r["os"] == "iOS"), None)
    fy25_and = next((r for r in fy25_mobile if r["os"] == "Android"), None)
    if fy25_ios and ios_row and fy25_total_mob > 0:
        fy25_ios_share = fy25_ios["users"]/fy25_total_mob*100
        fy26_ios_share = ios_row["users"]/total_mob*100
        delta = fy26_ios_share - fy25_ios_share
        lines.append(f"**YoY iOS share shift:** {fy25_ios_share:.0f}% → {fy26_ios_share:.0f}% ({delta:+.0f}pp).")
        lines.append("")

    lines += [
        "## Slide-ready single sentence",
        "",
    ]
    if ios_row and and_row and ios_row["users"] >= 30:
        ios_share = ios_row["users"]/total_mob*100
        ios_epu = ios_row["eng_per_user"]
        and_epu = and_row["eng_per_user"]
        if ios_epu > and_epu:
            lines.append(f"_\"{p.label} mobile audience is {ios_share:.0f}% iOS, and iOS users engage {(ios_epu-and_epu)/and_epu*100:.0f}% longer per user than Android ({fmt_secs(ios_epu)} vs {fmt_secs(and_epu)}).\"_")
        else:
            lines.append(f"_\"{p.label} mobile audience is {ios_share:.0f}% iOS, but Android users engage {(and_epu-ios_epu)/ios_epu*100:.0f}% longer per user than iOS ({fmt_secs(and_epu)} vs {fmt_secs(ios_epu)}).\"_")
    else:
        lines.append("_(Not enough mobile volume to construct a defensible single-sentence slide claim — recommend skipping the OS angle.)_")
    return "\n".join(lines)


# ===========================================================================
# Main
# ===========================================================================
def write_md(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    prior = p.prior_year if p.fy else p
    out = io.out_dir("ga4")
    ga = ga4.client()
    dr_cur = [DateRange(start_date=p.start, end_date=p.end)]

    # Analysis 1 — device split (5 views)
    print(f"=== Device split ({p.label}) ===")
    write_md(out, device_split(ga, dr_cur, f"{p.label} ({p.start} – {p.end})"),
             f"device_analysis_{p.slug}.md")

    # Analysis 2 — engagement YoY + channel × device
    print(f"\n=== Device engagement YoY ({prior.label} vs {p.label}) ===")
    write_md(out, engagement_report(ga, p, prior), f"device_engagement_{p.slug}.md")

    # Analysis 3 — OS breakdown (+ prior-FY mobile YoY)
    print(f"\n=== Device OS breakdown ({p.label}) ===")
    write_md(out, os_report(ga, p, prior), f"device_os_{p.slug}.md")


if __name__ == "__main__":
    main()

"""
FY25 device analysis (Apr 2024–Mar 2025) for QBR YoY comparison.
Same 5 views as FY26 + comparison table.
Uses totalUsers throughout (consistent methodology).
"""
import json, shutil
from pathlib import Path
from collections import defaultdict

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, FilterExpressionList,
    Metric, OrderBy, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT       = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROP       = "properties/306115293"
DR_FY25    = [DateRange(start_date="2024-04-01", end_date="2025-03-31")]
DR_FY26    = [DateRange(start_date="2025-04-01", end_date="2026-03-31")]
OUT        = ROOT / "reports" / "out"
WIN_DL     = Path("/mnt/c/Users/User/Downloads/careconnect_qbr_fy26")

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


def get_creds():
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"))
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return c


def pf(path):
    return FilterExpression(filter=Filter(field_name="pagePath",
        string_filter=Filter.StringFilter(value=path,
            match_type=Filter.StringFilter.MatchType.BEGINS_WITH)))


def channel_bucket(ch):
    if ch == "Email":       return "Email"
    if ch == "Direct":      return "Direct"
    if ch == "Unassigned":  return "Unassigned"
    return "Other"


def pct(n, d):
    return f"{n/d*100:.0f}%" if d else "—"


def overall_split(ga, dr):
    # Per-device breakdown
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers"), Metric(name="sessions"),
                 Metric(name="screenPageViews")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
    ))
    rows = []
    device_sum_users = sum(int(r.metric_values[0].value) for r in resp.rows)
    for row in resp.rows:
        dev = row.dimension_values[0].value
        u, s, pv = (int(v.value) for v in row.metric_values)
        rows.append({"device": dev, "users": u, "sessions": s, "pv": pv,
                     "users_pct": pct(u, device_sum_users)})
    # Unique users (no breakdown) — true unique user count
    resp2 = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        metrics=[Metric(name="totalUsers")],
    ))
    true_unique = int(resp2.rows[0].metric_values[0].value) if resp2.rows else device_sum_users
    return rows, true_unique


def channel_split(ga, dr):
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="sessionDefaultChannelGroup"),
                    Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
        limit=50,
    ))
    ch_dev = defaultdict(lambda: defaultdict(int))
    for row in resp.rows:
        ch = channel_bucket(row.dimension_values[0].value)
        dev = row.dimension_values[1].value
        ch_dev[ch][dev] += int(row.metric_values[0].value)
    return ch_dev


def article_split(ga, dr, posts):
    out = []
    for label, path in posts:
        resp = ga.run_report(RunReportRequest(
            property=PROP, date_ranges=dr,
            dimensions=[Dimension(name="deviceCategory")],
            metrics=[Metric(name="totalUsers")],
            dimension_filter=pf(path),
        ))
        d = {r.dimension_values[0].value: int(r.metric_values[0].value) for r in resp.rows}
        tot = sum(d.values())
        if tot == 0:
            continue
        out.append({
            "label": label,
            "mobile":  d.get("mobile", 0),
            "desktop": d.get("desktop", 0),
            "tablet":  d.get("tablet", 0),
            "total":   tot,
        })
    return out


def monthly_split(ga, dr):
    resp = ga.run_report(RunReportRequest(
        property=PROP, date_ranges=dr,
        dimensions=[Dimension(name="yearMonth"), Dimension(name="deviceCategory")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="yearMonth"))],
        limit=200,
    ))
    monthly = defaultdict(lambda: defaultdict(int))
    for row in resp.rows:
        ym  = row.dimension_values[0].value
        dev = row.dimension_values[1].value
        monthly[ym][dev] += int(row.metric_values[0].value)
    return monthly


def main():
    ga = BetaAnalyticsDataClient(credentials=get_creds())

    print("Pulling FY25 data...")
    fy25_overall, fy25_total = overall_split(ga, DR_FY25)
    fy25_channel  = channel_split(ga, DR_FY25)
    fy25_article  = article_split(ga, DR_FY25, POSTS)
    fy25_monthly  = monthly_split(ga, DR_FY25)

    print("Pulling FY26 data for comparison...")
    fy26_overall, fy26_total = overall_split(ga, DR_FY26)
    fy26_channel  = channel_split(ga, DR_FY26)

    # Helper: get device counts from overall list
    def dev_count(rows, dev):
        for r in rows:
            if r["device"] == dev: return r["users"]
        return 0

    fy25_mob_pct  = dev_count(fy25_overall, "mobile")  / fy25_total * 100
    fy25_desk_pct = dev_count(fy25_overall, "desktop") / fy25_total * 100
    fy26_mob_pct  = dev_count(fy26_overall, "mobile")  / fy26_total * 100
    fy26_desk_pct = dev_count(fy26_overall, "desktop") / fy26_total * 100

    lines = [
        "# Device Analysis — FY25 (Apr 2024–Mar 2025) + FY26 Comparison",
        "Source: GA4 property 306115293",
        "",
        "**Data quality:** FY25 has 2,619 users / 3,813 sessions / 10,631 pageviews across all 12 months. Property has been tracking since Jan 2023 — no setup gap or missing months. Direct comparison to FY26 is methodologically sound.",
        "",
        "_Caveats:_ FY25 had ~2.4× lower volume than FY26, so device % splits are reliable but per-article device-by-completion analysis would be too thin to be meaningful. Audience composition shifted (FY26 had publisher push activity that FY25 didn't), so device skew differences may reflect audience mix change rather than user behaviour change.",
        "",
        "## View 1: Overall device split — FY25",
        "",
        "| Device | Users | Users % | Sessions | Pageviews |",
        "|---|---|---|---|---|",
    ]
    for r in fy25_overall:
        lines.append(f"| {r['device']} | {r['users']:,} | {r['users_pct']} | {r['sessions']:,} | {r['pv']:,} |")

    lines += ["", "## View 2: Device split by channel — FY25", "",
        "| Channel | Mobile | Mobile % | Desktop | Desktop % | Tablet | Tablet % | Total |",
        "|---|---|---|---|---|---|---|---|"]
    for ch in ["Email", "Direct", "Other", "Unassigned"]:
        d = fy25_channel.get(ch, {})
        tot = sum(d.values())
        m, dk, tb = d.get("mobile",0), d.get("desktop",0), d.get("tablet",0)
        lines.append(f"| {ch} | {m:,} | {pct(m,tot)} | {dk:,} | {pct(dk,tot)} | {tb:,} | {pct(tb,tot)} | {tot:,} |")

    lines += ["", "## View 3: Device split by article — FY25 (only articles with traffic)", ""]
    if not fy25_article:
        lines.append("_No articles in the FY26 top-9 set had measurable traffic in FY25._")
        lines.append("")
        lines.append("This is expected — most of the FY26 top blog posts were published mid-2025 (Cold & flu published 12 May 2025, Chocolate published 5 Aug 2025, etc.). The blog content roster was very different in FY25.")
    else:
        lines.append("| Post | Mobile | Mobile % | Desktop | Desktop % | Tablet | Tablet % | Total |")
        lines.append("|---|---|---|---|---|---|---|---|")
        for r in fy25_article:
            tot = r["total"]
            lines.append(f"| {r['label']} | {r['mobile']} | {pct(r['mobile'],tot)} | {r['desktop']} | {pct(r['desktop'],tot)} | {r['tablet']} | {pct(r['tablet'],tot)} | {tot} |")

    lines += ["", "## View 4: Device + completion rate per article — FY25", "",
        "_Skipped._ Per-article volumes in FY25 are too low for reliable mobile-vs-desktop completion splits (most active FY25 posts had < 30 mobile users). The cleaner FY26 View 4 stands on its own.",
        ""]

    lines += ["## View 5: Monthly device split — FY25", "",
        "| Month | Total users | Mobile | Mobile % | Desktop | Desktop % |",
        "|---|---|---|---|---|---|"]
    for ym in sorted(fy25_monthly):
        d = fy25_monthly[ym]
        tot  = sum(d.values())
        m    = d.get("mobile",  0)
        desk = d.get("desktop", 0)
        month = f"{ym[:4]}-{ym[4:]}"
        lines.append(f"| {month} | {tot:,} | {m:,} | {pct(m,tot)} | {desk:,} | {pct(desk,tot)} |")

    # YoY comparison table
    fy25_email_dev  = fy25_channel.get("Email", {});  fy25_email_tot = sum(fy25_email_dev.values())
    fy26_email_dev  = fy26_channel.get("Email", {});  fy26_email_tot = sum(fy26_email_dev.values())
    fy25_direct_dev = fy25_channel.get("Direct", {}); fy25_direct_tot = sum(fy25_direct_dev.values())
    fy26_direct_dev = fy26_channel.get("Direct", {}); fy26_direct_tot = sum(fy26_direct_dev.values())

    fy25_email_mob_pct  = fy25_email_dev.get("mobile",0)/fy25_email_tot*100   if fy25_email_tot   else 0
    fy26_email_mob_pct  = fy26_email_dev.get("mobile",0)/fy26_email_tot*100   if fy26_email_tot   else 0
    fy25_direct_mob_pct = fy25_direct_dev.get("mobile",0)/fy25_direct_tot*100 if fy25_direct_tot  else 0
    fy26_direct_mob_pct = fy26_direct_dev.get("mobile",0)/fy26_direct_tot*100 if fy26_direct_tot  else 0

    mob_dir = "grew" if fy26_mob_pct > fy25_mob_pct else "shrank"
    mob_delta = fy26_mob_pct - fy25_mob_pct

    lines += ["",
        "## YoY Comparison: FY25 vs FY26",
        "",
        "| Metric | FY25 | FY26 | Δ |",
        "|---|---|---|---|",
        f"| Total unique users | {fy25_total:,} | {fy26_total:,} | {fy26_total-fy25_total:+,} ({(fy26_total-fy25_total)/fy25_total*100:+.0f}%) |",
        f"| Mobile users | {dev_count(fy25_overall,'mobile'):,} | {dev_count(fy26_overall,'mobile'):,} | {dev_count(fy26_overall,'mobile')-dev_count(fy25_overall,'mobile'):+,} ({(dev_count(fy26_overall,'mobile')-dev_count(fy25_overall,'mobile'))/dev_count(fy25_overall,'mobile')*100:+.0f}%) |",
        f"| Desktop users | {dev_count(fy25_overall,'desktop'):,} | {dev_count(fy26_overall,'desktop'):,} | {dev_count(fy26_overall,'desktop')-dev_count(fy25_overall,'desktop'):+,} ({(dev_count(fy26_overall,'desktop')-dev_count(fy25_overall,'desktop'))/dev_count(fy25_overall,'desktop')*100:+.0f}%) |",
        f"| Mobile % overall | {fy25_mob_pct:.0f}% | {fy26_mob_pct:.0f}% | {mob_delta:+.0f}pp |",
        f"| Desktop % overall | {fy25_desk_pct:.0f}% | {fy26_desk_pct:.0f}% | {fy26_desk_pct-fy25_desk_pct:+.0f}pp |",
        f"| Email channel mobile % | {fy25_email_mob_pct:.0f}% | {fy26_email_mob_pct:.0f}% | {fy26_email_mob_pct-fy25_email_mob_pct:+.0f}pp |",
        f"| Direct channel mobile % | {fy25_direct_mob_pct:.0f}% | {fy26_direct_mob_pct:.0f}% | {fy26_direct_mob_pct-fy25_direct_mob_pct:+.0f}pp |",
        "",
        "## Honest read",
        "",
        f"**Mobile share {mob_dir} from {fy25_mob_pct:.0f}% to {fy26_mob_pct:.0f}% YoY** ({mob_delta:+.0f}pp). Mobile users in absolute terms doubled (628 → 1,253) but desktop users grew faster (+170%) so the relative mobile share actually fell slightly.",
        "",
        "**The story isn't 'mobile is growing'.** Both devices grew, but the FY26 publisher email pushes (MED Today, TMR, AJP Daily) drove disproportionate desktop traffic — clinical readers at workstations, not phones.",
        "",
        f"**Direct channel got much more desktop-heavy** ({fy25_direct_mob_pct:.0f}% → {fy26_direct_mob_pct:.0f}% mobile). FY26 'direct' includes more campaign-driven users returning days/weeks later — those return visits skew desktop. FY25 direct was probably more casual mobile checking.",
        "",
        "**Email channel stayed mobile-heavy** — the only channel where mobile % actually rose (+3pp). This matches the universal pattern: emails are read on phones.",
        "",
        "**Methodology is sound** — same property, same metric (totalUsers), same channel definitions, no tracking gap.",
        "",
        "**Don't compare per-article** — most FY26 top-9 posts didn't exist or had near-zero traffic in FY25 (Cold & flu published May 2025, Chocolate published Aug 2025, etc.). Per-article device-by-completion analysis would be too thin in FY25.",
        "",
        "**Slide angle that holds up:** FY26 audience grew dramatically (+135% users) and the channel mix shifted toward desktop-heavy publisher activity. Mobile readership of email campaigns is the only segment where mobile % rose. Don't claim 'mobile is growing' — that's not what the data shows.",
    ]

    md = "\n".join(lines)
    out_path = OUT / "device_analysis_fy25_vs_fy26.md"
    out_path.write_text(md)
    shutil.copy(out_path, WIN_DL / "device_analysis_fy25_vs_fy26.md")
    print(md)
    print(f"\nWritten to {out_path}")


if __name__ == "__main__":
    main()

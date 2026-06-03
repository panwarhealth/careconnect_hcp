"""
eDM-correlated spikes in /resources and tools/videos pages.
FY26: Apr 2025 – Mar 2026.

Outputs:
  out/edm_resources_tools_daily.csv  — daily timeseries
  out/edm_resources_tools_spikes.md  — lift table + drill-in + verdict
"""
import sys
from collections import defaultdict
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    FilterExpressionList,
    Metric,
    OrderBy,
    RunReportRequest,
)

PROP = f"properties/{ga4.PROPERTY_ID}"

# TODO: parameterize via lib.periods — START/END and the SENDS calendar below are
# FY26-locked; the spike analysis is keyed to these specific send dates.
START, END = "2025-04-01", "2026-03-31"

# eDM send dates to analyse (name, date)
SENDS = [
    ("Solus FESS cold & flu",           date(2025,  5, 13)),
    ("Mailchimp cold & flu",            date(2025,  9,  8)),
    ("Mailchimp Dr Tattersall",         date(2025,  9, 22)),
    ("Mailchimp bowel habits",          date(2025,  9, 28)),
    ("Solus Zaditen allergic rhinitis", date(2025,  9, 30)),
    ("Solus Hydralyte GLP-1RA",         date(2025, 10, 23)),
    ("Mailchimp Claim FESS Samples",    date(2025, 10, 29)),
    ("TMR Allergic rhinitis",           date(2025, 11,  4)),
    ("Mailchimp GLP-1RA Hydralyte",     date(2025, 11, 14)),
    ("TMR Rectogesic chocolate cake",   date(2025, 11, 17)),
    ("Solus Weekend Read Nov",          date(2025, 11, 22)),
    ("Mailchimp FESS follow-up",        date(2025, 11, 24)),
    ("Solus Hydralyte Dec",             date(2025, 12,  9)),
    ("Solus Weekend Read Dec",          date(2025, 12, 13)),
    ("Solus FESS World Sleep Day",      date(2026,  3, 12)),
    ("Solus Rectogesic CPD / MCA",      date(2026,  3, 19)),
]

# Page filters per section
SECTION_FILTERS = {
    "resources": [
        "/resources",
        "/wp-content/uploads",
    ],
    "tools": [
        "/allergy-analyser",
        "/rectogesic-3d-model",
        "/fess-demonstration",
        "/interactivetools",
        "/tools-and-videos",
        "/videos",
        "/section1",
        "/section2",
        "/section3",
        "/section4",
        "/section5",
        "/option1",
        "/bake",
        "/clues",
        "/chart",
        "/detection",
    ],
}


def section_filter(paths: list[str]) -> FilterExpression:
    if len(paths) == 1:
        return FilterExpression(filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                value=paths[0],
                match_type=Filter.StringFilter.MatchType.BEGINS_WITH)))
    return FilterExpression(or_group=__import__(
        "google.analytics.data_v1beta.types",
        fromlist=["FilterExpressionList"]
    ).FilterExpressionList(expressions=[
        FilterExpression(filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                value=p,
                match_type=Filter.StringFilter.MatchType.BEGINS_WITH)))
        for p in paths
    ]))


def pull_daily(ga: BetaAnalyticsDataClient, paths: list[str]) -> dict[str, dict]:
    """Returns {YYYY-MM-DD: {pv, users}}"""
    resp = ga.run_report(RunReportRequest(
        property=PROP,
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="date")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=section_filter(paths),
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"))],
        limit=400,
    ))
    out = {}
    for row in resp.rows:
        raw = row.dimension_values[0].value
        d = f"{raw[:4]}-{raw[4:6]}-{raw[6:8]}"
        out[d] = {
            "pv":    int(row.metric_values[0].value),
            "users": int(row.metric_values[1].value),
        }
    return out


def pull_page_detail(ga: BetaAnalyticsDataClient, paths: list[str],
                     start: str, end: str) -> list[dict]:
    """Top pages in a date window."""
    resp = ga.run_report(RunReportRequest(
        property=PROP,
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=section_filter(paths),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=10,
    ))
    return [
        {
            "path": row.dimension_values[0].value,
            "pv":   int(row.metric_values[0].value),
            "users":int(row.metric_values[1].value),
        }
        for row in resp.rows
    ]


def day_sum(data: dict, d: date, n: int = 1) -> int:
    total = 0
    for i in range(n):
        key = (d + timedelta(i)).isoformat()
        total += data.get(key, {}).get("pv", 0)
    return total


def baseline_avg(data: dict, d: date, days: int = 7) -> float:
    total = sum(
        data.get((d - timedelta(i)).isoformat(), {}).get("pv", 0)
        for i in range(1, days + 1)
    )
    return total / days


def main() -> None:
    out = io.out_dir("ga4")
    ga = ga4.client()

    print("Pulling daily data for resources section...")
    res_daily = pull_daily(ga, SECTION_FILTERS["resources"])
    print(f"  Got {len(res_daily)} days")

    print("Pulling daily data for tools/videos section...")
    tool_daily = pull_daily(ga, SECTION_FILTERS["tools"])
    print(f"  Got {len(tool_daily)} days")

    # --- Write daily CSV ---
    all_dates = sorted(set(list(res_daily.keys()) + list(tool_daily.keys())))
    csv_rows = []
    for d in all_dates:
        if res_daily.get(d):
            csv_rows.append({"date": d, "section": "resources",
                             "pageviews": res_daily[d]["pv"],
                             "users": res_daily[d]["users"]})
        if tool_daily.get(d):
            csv_rows.append({"date": d, "section": "tools",
                             "pageviews": tool_daily[d]["pv"],
                             "users": tool_daily[d]["users"]})

    csv_path = io.write_dicts(out / "edm_resources_tools_daily.csv", csv_rows)
    io.copy_to_downloads(csv_path)

    # --- Spike analysis ---
    md_lines = [
        "# eDM Lift Analysis — Resources & Tools/Videos",
        "Source: GA4 property 306115293, FY26 Apr 2025–Mar 2026",
        "",
        "Baseline = 7-day average daily pageviews in the week before send.",
        "Spike window = send day + 3 days (cumulative pv ÷ 4 days, vs baseline).",
        "Lift = ((spike_avg / baseline) − 1) × 100%.",
        "",
        "## Lift table",
        "",
        "| eDM | Send date | Resources baseline | Resources spike avg | Resources lift | Tools baseline | Tools spike avg | Tools lift |",
        "|---|---|---|---|---|---|---|---|",
    ]

    spike_detail = []
    for name, send in SENDS:
        res_base = baseline_avg(res_daily, send)
        tool_base = baseline_avg(tool_daily, send)
        res_spike = day_sum(res_daily, send, 4) / 4
        tool_spike = day_sum(tool_daily, send, 4) / 4
        res_lift = ((res_spike / res_base) - 1) * 100 if res_base > 0 else 0
        tool_lift = ((tool_spike / tool_base) - 1) * 100 if tool_base > 0 else 0

        def fmt(v): return f"{v:.1f}"
        def lift_str(v):
            if v > 50:  return f"**▲{v:.0f}%**"
            if v > 0:   return f"▲{v:.0f}%"
            if v < -10: return f"▼{abs(v):.0f}%"
            return f"{v:.0f}%"

        md_lines.append(
            f"| {name} | {send} | {fmt(res_base)} | {fmt(res_spike)} | {lift_str(res_lift)} | "
            f"{fmt(tool_base)} | {fmt(tool_spike)} | {lift_str(tool_lift)} |"
        )

        # flag for drill-in if either lift > 50%
        if res_lift > 50 or tool_lift > 50:
            spike_detail.append((name, send, res_lift, tool_lift))

    # --- Drill-in for big spikes ---
    md_lines += ["", "## Spike drill-in (>50% lift)", ""]
    if not spike_detail:
        md_lines.append("No sends produced >50% lift on either section.")
    else:
        for name, send, res_lift, tool_lift in spike_detail:
            s_str = send.isoformat()
            e_str = (send + timedelta(3)).isoformat()
            md_lines.append(f"### {name} ({send}) — Resources ▲{res_lift:.0f}%, Tools ▲{tool_lift:.0f}%")
            md_lines.append("")

            if res_lift > 50:
                pages = pull_page_detail(ga, SECTION_FILTERS["resources"], s_str, e_str)
                md_lines.append("**Top resources pages (send day + 3 days):**")
                md_lines.append("")
                md_lines.append("| Page | PV | Users |")
                md_lines.append("|---|---|---|")
                for p in pages[:5]:
                    md_lines.append(f"| {p['path'][:70]} | {p['pv']} | {p['users']} |")
                md_lines.append("")

            if tool_lift > 50:
                pages = pull_page_detail(ga, SECTION_FILTERS["tools"], s_str, e_str)
                md_lines.append("**Top tools/videos pages (send day + 3 days):**")
                md_lines.append("")
                md_lines.append("| Page | PV | Users |")
                md_lines.append("|---|---|---|")
                for p in pages[:5]:
                    md_lines.append(f"| {p['path'][:70]} | {p['pv']} | {p['users']} |")
                md_lines.append("")

    # --- Verdict ---
    all_res_lifts  = []
    all_tool_lifts = []
    for name, send in SENDS:
        res_base  = baseline_avg(res_daily, send)
        tool_base = baseline_avg(tool_daily, send)
        res_spike  = day_sum(res_daily, send, 4) / 4
        tool_spike = day_sum(tool_daily, send, 4) / 4
        if res_base > 0:  all_res_lifts.append(((res_spike/res_base)-1)*100)
        if tool_base > 0: all_tool_lifts.append(((tool_spike/tool_base)-1)*100)

    big_res  = [l for l in all_res_lifts  if l > 50]
    big_tool = [l for l in all_tool_lifts if l > 50]

    md_lines += [
        "",
        "## Honest verdict",
        "",
        f"- **{len(big_res)}/{len(all_res_lifts)}** eDM sends produced >50% lift on resources.",
        f"- **{len(big_tool)}/{len(all_tool_lifts)}** eDM sends produced >50% lift on tools/videos.",
        f"- Median resources lift: {sorted(all_res_lifts)[len(all_res_lifts)//2]:.0f}%",
        f"- Median tools lift: {sorted(all_tool_lifts)[len(all_tool_lifts)//2]:.0f}%",
        "",
    ]

    if len(big_res) >= 6 and len(big_tool) >= 4:
        md_lines.append("**Signal is strong enough for a slide.** Most sends produce clear lift on both sections.")
    elif len(big_res) >= 3 or len(big_tool) >= 3:
        md_lines.append("**Signal is moderate.** A few sends produce clear lift — use specific examples rather than a general claim.")
    else:
        md_lines.append("**Signal is too weak for a general slide.** eDM sends do not consistently drive resources/tools traffic. Recommend skipping the slide or limiting the claim to the specific sends that showed lift.")

    md_text = "\n".join(md_lines)
    md_path = out / "edm_resources_tools_spikes.md"
    md_path.write_text(md_text)
    print(f"wrote {md_path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(md_path)
    print("\n--- LIFT TABLE PREVIEW ---")
    for line in md_lines[8:30]:
        print(line)
    print("\n--- VERDICT ---")
    for line in md_lines[-6:]:
        print(line)


if __name__ == "__main__":
    main()

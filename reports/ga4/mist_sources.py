"""
FY26 MIST+ article — raw session source/medium breakdown.

Pulls all sessions where the landing page matches the MIST+ article slug.
Uses sessionSource + sessionMedium + sessionCampaignName dimensions so we
can see exactly what UTM values are present (or absent).

Also checks for "email with missing source" pattern:
  sessionMedium = "email" but sessionSource = "(direct)" or "(not set)"

    python reports/ga4/mist_sources.py --period FY26

Outputs (to reports/out/ga4/, also copied to Windows Downloads):
  - <slug>_mist_sources.csv
  - <slug>_mist_sources.md
"""
import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)


def landing_re(regex: str) -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name="landingPage",
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
            value=regex,
        ),
    ))


def write_csv(out: Path, rows: list[dict], filename: str) -> None:
    if not rows:
        return
    io.copy_to_downloads(io.write_dicts(out / filename, rows))


def write_text(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    out = io.out_dir("ga4")
    START, END = p.start, p.end
    ga = ga4.client()

    # Step 1: confirm which landing page slugs contain "mist"
    print("Step 1: Finding MIST+ landing page slugs...")
    slug_resp = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=landing_re(r"mist"),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=20,
    ))
    print(f"  {'Landing page':<60} {'Sessions':>8} {'Users':>7}")
    print("  " + "-" * 78)
    mist_slugs = []
    for row in slug_resp.rows:
        page = row.dimension_values[0].value
        sess = int(row.metric_values[0].value)
        users = int(row.metric_values[1].value)
        print(f"  {page:<60} {sess:>8,} {users:>7,}")
        mist_slugs.append((page, sess, users))

    # Step 2: raw source / medium / campaign for MIST+ landing sessions
    print("\nStep 2: Source/medium/campaign breakdown for MIST+ landing sessions...")
    src_resp = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[
            Dimension(name="sessionDefaultChannelGroup"),
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=landing_re(r"mist"),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=200,
    ))

    src_rows = []
    total_sess = 0
    total_users = 0
    for row in src_resp.rows:
        ch_group = row.dimension_values[0].value
        source   = row.dimension_values[1].value
        medium   = row.dimension_values[2].value
        campaign = row.dimension_values[3].value
        sess     = int(row.metric_values[0].value)
        users    = int(row.metric_values[1].value)
        total_sess  += sess
        total_users += users
        src_rows.append({
            "channel_group":   ch_group,
            "source":          source,
            "medium":          medium,
            "campaign":        campaign,
            "sessions":        sess,
            "users":           users,
        })
        print(f"  {ch_group:<18} src={source:<25} med={medium:<12} "
              f"camp={campaign:<20} sess={sess:>4} users={users:>4}")

    print(f"\n  TOTAL: {total_sess} sessions, {total_users} users")

    # Step 3: flag anything that looks like email with missing/partial UTMs
    print("\nStep 3: Anomaly flags...")
    flags = []
    for r in src_rows:
        src = r["source"].lower()
        med = r["medium"].lower()
        ch  = r["channel_group"].lower()
        # Email medium but channel shows as Direct or Unassigned
        if "email" in med and ch in ("direct", "unassigned", "(other)"):
            flags.append(("email medium / non-email channel", r))
        # Direct channel but medium is not (none) — suggests partial UTM
        if ch == "direct" and med not in ("(none)", "(not set)", ""):
            flags.append(("direct channel but medium set", r))
        # Source looks mailchimp-adjacent
        if any(x in src for x in ["mailchimp", "mc", "mist", "chimpmail"]):
            flags.append(("mailchimp-adjacent source", r))
        # Campaign name set but source is (direct)
        if r["campaign"] not in ("(not set)", "(direct)", "") and ch == "direct":
            flags.append(("campaign name present but channel=Direct", r))

    if flags:
        for label, r in flags:
            print(f"  FLAG [{label}]: source={r['source']} medium={r['medium']} "
                  f"campaign={r['campaign']} ch={r['channel_group']} sess={r['sessions']}")
    else:
        print("  No anomalies flagged.")

    # Write CSV
    write_csv(out, src_rows, f"{p.slug}_mist_sources.csv")

    # Markdown
    md: list[str] = []
    md.append("# FY26 MIST+ Article — Session Source / Medium Breakdown\n")
    md.append(f"**Window:** {p.label} ({p.start} – {p.end}) (GA4 property {ga4.PROPERTY_ID})  ")
    md.append("**Methodology:** landing-page sessions only (first page of session = MIST+ article).  ")
    md.append(f"**Total: {total_sess} sessions, {total_users} users**\n")

    md.append("## MIST+ landing page slugs found\n")
    md.append("| Landing page | Sessions | Users |")
    md.append("|---|---:|---:|")
    for page, sess, users in mist_slugs:
        md.append(f"| `{page}` | {sess:,} | {users:,} |")
    md.append("")

    md.append("## Raw source / medium / campaign breakdown\n")
    md.append("| GA4 channel | Source | Medium | Campaign | Sessions | Users |")
    md.append("|---|---|---|---|---:|---:|")
    for r in src_rows:
        md.append(f"| {r['channel_group']} | `{r['source']}` | `{r['medium']}` | "
                  f"`{r['campaign']}` | {r['sessions']:,} | {r['users']:,} |")
    md.append(f"| **TOTAL** | | | | **{total_sess:,}** | **{total_users:,}** |")
    md.append("")

    # Channel group rollup
    by_channel: dict[str, tuple[int, int]] = {}
    for r in src_rows:
        ch = r["channel_group"]
        s, u = by_channel.get(ch, (0, 0))
        by_channel[ch] = (s + r["sessions"], u + r["users"])
    md.append("## Rollup by GA4 channel group\n")
    md.append("| Channel group | Sessions | Users | % of sessions |")
    md.append("|---|---:|---:|---:|")
    for ch, (s, u) in sorted(by_channel.items(), key=lambda x: x[1][0], reverse=True):
        pct = s / total_sess * 100 if total_sess else 0
        md.append(f"| {ch} | {s:,} | {u:,} | {pct:.1f}% |")
    md.append("")

    if flags:
        md.append("## Anomaly flags\n")
        md.append("Rows that suggest UTM misconfiguration or misattribution:\n")
        md.append("| Flag | Source | Medium | Campaign | Channel | Sessions |")
        md.append("|---|---|---|---|---|---:|")
        for label, r in flags:
            md.append(f"| {label} | `{r['source']}` | `{r['medium']}` | "
                      f"`{r['campaign']}` | {r['channel_group']} | {r['sessions']:,} |")
        md.append("")

    md.append("## What to reclassify and why\n")
    md.append("*(Populated manually after reviewing the raw source table above.)*\n")
    md.append("Review the raw breakdown above and look for:\n")
    md.append("- Any row where `source` contains `mailchimp`, `mc`, or a campaign name matching the MIST+ send")
    md.append("- Any row where `medium = email` but `channel_group = Direct` or `Unassigned`")
    md.append("- Any row where `campaign` names a specific eDM but `source = (direct)`")
    md.append("- The `Direct / (none)` rows — these are the ones most likely to be email click-throughs "
              "with lost referrers (no UTM, no referrer header). Cross-reference against your Mailchimp "
              "send date for the MIST+ article to see if the Direct traffic spiked on or just after send day.\n")

    write_text(out, "\n".join(md), f"{p.slug}_mist_sources.md")
    print("\nDone.")


if __name__ == "__main__":
    main()

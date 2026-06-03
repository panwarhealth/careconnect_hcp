"""Check what fraction of historical traffic has full UTM attribution vs (direct).

If almost everything is (direct) → UTMs being stripped systemically.
If lots of named campaigns → attribution works for most, your test is the outlier.

Usage: python reports/ga4/attribution_check.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4

from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Metric,
    OrderBy,
    RunReportRequest,
)

# Rolling 30-day window (30daysAgo..today) — not an FY period, no --period CLI.
PROPERTY_ID = ga4.PROPERTY_ID


def main() -> None:
    client = ga4.client()

    print("=== Last 30 days: top 25 source/medium/campaign combos ===")
    resp = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="30daysAgo", end_date="today")],
        dimensions=[
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=25,
    ))
    print(f"{'source':<22}{'medium':<14}{'campaign':<32}{'sessions':>10}{'users':>8}")
    print("-" * 86)
    total_sess = 0
    direct_sess = 0
    for row in resp.rows:
        src, med, cmp = (v.value for v in row.dimension_values)
        sess, usr = (int(v.value) for v in row.metric_values)
        total_sess += sess
        if src == "(direct)":
            direct_sess += sess
        print(f"{src[:22]:<22}{med[:14]:<14}{cmp[:32]:<32}{sess:>10}{usr:>8}")
    print("-" * 86)
    pct = (direct_sess / total_sess * 100) if total_sess else 0
    print(f"\n(direct) fraction: {direct_sess}/{total_sess} sessions ({pct:.1f}%)")
    print("Healthy GA4: typically 30-60% direct. If 90%+ direct → UTMs being stripped systemically.")


if __name__ == "__main__":
    main()

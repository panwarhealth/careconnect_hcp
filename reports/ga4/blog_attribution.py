"""Per-blog-post traffic broken down by source/medium/campaign.
Two outputs: rolling 12-month and JFM 2026.

Usage: python reports/ga4/blog_attribution.py
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    Metric,
    OrderBy,
    RunReportRequest,
)

PROPERTY_ID = ga4.PROPERTY_ID


def pull(c: BetaAnalyticsDataClient, start: str, end: str) -> list[list[str]]:
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[
            Dimension(name="landingPagePlusQueryString"),
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[
            Metric(name="sessions"),
            Metric(name="totalUsers"),
            Metric(name="engagedSessions"),
            Metric(name="screenPageViews"),
        ],
        dimension_filter=FilterExpression(
            filter=Filter(
                field_name="landingPagePlusQueryString",
                string_filter=Filter.StringFilter(
                    match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                    value="^/blog/",
                ),
            ),
        ),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=10000,
    )
    resp = c.run_report(req)
    out = []
    for r in resp.rows:
        dims = [v.value for v in r.dimension_values]
        mets = [v.value for v in r.metric_values]
        out.append(dims + mets)
    return out


def write_csv(path: Path, header: list[str], rows: list[list[str]]) -> None:
    io.write_csv(path, header, rows)


def main():
    c = ga4.client()
    out = io.out_dir("ga4")
    header = ["landing_page", "source", "medium", "campaign", "sessions", "users", "engaged_sessions", "pageviews"]

    # TODO: parameterize via lib.periods — emits two distinct windows
    # (full FY26 + JFM 2026) in one run, so date constants stay.
    rows12 = pull(c, "2025-04-01", "2026-03-31")
    write_csv(out / "blog_landing_attribution_apr2025_mar2026.csv", header, rows12)

    rows_jfm = pull(c, "2026-01-01", "2026-03-31")
    write_csv(out / "blog_landing_attribution_jfm_2026.csv", header, rows_jfm)


if __name__ == "__main__":
    main()

"""Deep GA4 pull — every dimension that matters for the QBR.
Outputs CSVs in reports/out/ga4/.

Usage: python reports/ga4/deep_dive.py
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
    Metric,
    OrderBy,
    RunReportRequest,
)

PROPERTY_ID = ga4.PROPERTY_ID

# TODO: parameterize via lib.periods — this script pulls several unrelated
# windows (JFM YoY pairs, full FY, Apr2024–Apr2026), so date constants stay.
WINDOWS = [
    ("jfm_2025", "2025-01-01", "2025-03-31"),
    ("jfm_2026", "2026-01-01", "2026-03-31"),
    ("apr2024_mar2025", "2024-04-01", "2025-03-31"),
    ("apr2025_mar2026", "2025-04-01", "2026-03-31"),
]


def run(c: BetaAnalyticsDataClient, *, dims: list[str], metrics: list[str], start: str, end: str, limit: int = 100000, order_by=None, filter_expr=None):
    return c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name=d) for d in dims],
        metrics=[Metric(name=m) for m in metrics],
        limit=limit,
        order_bys=order_by or [],
        dimension_filter=filter_expr,
    ))


def write(name: str, header: list[str], rows: list[list[str]]) -> None:
    io.write_csv(io.out_dir("ga4") / f"{name}.csv", header, rows)


def to_rows(resp) -> list[list[str]]:
    return [[v.value for v in r.dimension_values] + [v.value for v in r.metric_values] for r in resp.rows]


def main():
    c = ga4.client()

    # 1. Daily traffic for FULL window (Apr 2024 - Apr 2026)
    r = run(c, dims=["date"],
            metrics=["totalUsers", "newUsers", "activeUsers", "sessions", "engagedSessions", "screenPageViews", "eventCount", "averageSessionDuration", "bounceRate", "engagementRate"],
            start="2024-04-01", end="2026-04-30",
            order_by=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"), desc=False)])
    write("daily_traffic_apr2024_apr2026",
          ["date", "users", "new_users", "active_users", "sessions", "engaged_sessions", "pageviews", "events", "avg_session_duration_s", "bounce_rate", "engagement_rate"],
          to_rows(r))

    # Per-window deep dives
    for label, start, end in WINDOWS:
        # Device category
        r = run(c, dims=["deviceCategory"], metrics=["totalUsers", "sessions", "screenPageViews", "engagementRate"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)])
        write(f"device_{label}", ["device", "users", "sessions", "pageviews", "engagement_rate"], to_rows(r))

        # Country
        r = run(c, dims=["country"], metrics=["totalUsers", "sessions", "screenPageViews"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)], limit=30)
        write(f"country_{label}", ["country", "users", "sessions", "pageviews"], to_rows(r))

        # Australian regions (where most traffic is)
        r = run(c, dims=["region"], metrics=["totalUsers", "sessions", "screenPageViews"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)], limit=20)
        write(f"region_{label}", ["region", "users", "sessions", "pageviews"], to_rows(r))

        # New vs returning
        r = run(c, dims=["newVsReturning"], metrics=["totalUsers", "sessions", "screenPageViews", "engagementRate"], start=start, end=end)
        write(f"new_vs_returning_{label}", ["type", "users", "sessions", "pageviews", "engagement_rate"], to_rows(r))

        # Events (key events / interactions)
        r = run(c, dims=["eventName"], metrics=["eventCount", "totalUsers"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)], limit=40)
        write(f"events_{label}", ["event_name", "event_count", "users"], to_rows(r))

        # Site-search terms (if site search is configured)
        try:
            r = run(c, dims=["searchTerm"], metrics=["eventCount", "totalUsers"], start=start, end=end,
                    order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)], limit=50)
            if r.rows:
                write(f"search_terms_{label}", ["search_term", "events", "users"], to_rows(r))
        except Exception as e:
            pass

    # 2. Top pages JFM 2026 — already done, also do for full year
    r = run(c, dims=["pagePath", "pageTitle"],
            metrics=["screenPageViews", "totalUsers", "sessions", "averageSessionDuration", "bounceRate", "engagementRate"],
            start="2025-04-01", end="2026-03-31",
            order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)], limit=200)
    write("top_pages_apr2025_mar2026",
          ["page_path", "page_title", "pageviews", "users", "sessions", "avg_session_duration_s", "bounce_rate", "engagement_rate"],
          to_rows(r))

    # 3. Landing pages overall (not just /register)
    for label, start, end in [("jfm_2026", "2026-01-01", "2026-03-31"), ("apr2025_mar2026", "2025-04-01", "2026-03-31")]:
        r = run(c, dims=["landingPagePlusQueryString"],
                metrics=["sessions", "totalUsers", "newUsers", "engagementRate"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)], limit=100)
        write(f"top_landing_pages_{label}", ["landing_page", "sessions", "users", "new_users", "engagement_rate"], to_rows(r))

    # 4. Hourly distribution JFM 2026
    r = run(c, dims=["hour"], metrics=["totalUsers", "sessions", "screenPageViews"], start="2026-01-01", end="2026-03-31",
            order_by=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="hour"), desc=False)])
    write("hourly_jfm_2026", ["hour", "users", "sessions", "pageviews"], to_rows(r))

    # 5. Day-of-week
    r = run(c, dims=["dayOfWeekName"], metrics=["totalUsers", "sessions", "screenPageViews"], start="2026-01-01", end="2026-03-31")
    write("dow_jfm_2026", ["day", "users", "sessions", "pageviews"], to_rows(r))

    # 6. Monthly source/medium for the full year
    r = run(c, dims=["yearMonth", "sessionSource", "sessionMedium"],
            metrics=["totalUsers", "sessions", "screenPageViews"],
            start="2025-04-01", end="2026-04-30",
            order_by=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="yearMonth")),
                      OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=2000)
    write("source_medium_monthly", ["year_month", "source", "medium", "users", "sessions", "pageviews"], to_rows(r))

    # 7. Campaign-level for the full year (utm_campaign)
    r = run(c, dims=["sessionCampaignName", "sessionSource", "sessionMedium"],
            metrics=["totalUsers", "sessions", "screenPageViews", "engagementRate"],
            start="2025-04-01", end="2026-04-30",
            order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)], limit=200)
    write("campaign_apr2025_apr2026", ["campaign", "source", "medium", "users", "sessions", "pageviews", "engagement_rate"], to_rows(r))


if __name__ == "__main__":
    main()

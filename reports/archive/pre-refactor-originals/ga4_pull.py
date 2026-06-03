"""Pull headline GA4 numbers for the quarterly review.

Outputs CSVs in reports/out/ga4/:
- traffic_jfm_2026.csv         daily totals for JFM 2026
- traffic_yoy_monthly.csv      monthly totals Apr 2024 - Apr 2026
- top_pages_jfm_2026.csv       top pages by views, JFM 2026
- channel_jfm_2026.csv         channel/source breakdown JFM 2026
- channel_yoy.csv              same, both YoY windows
"""
from __future__ import annotations

import csv
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    FilterExpression,
    Filter,
    Metric,
    OrderBy,
    RunReportRequest,
)
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
OUT = ROOT / "reports" / "out" / "ga4"
PROPERTY_ID = "306115293"


def client() -> BetaAnalyticsDataClient:
    info = json.loads(TOKEN_JSON.read_text())
    creds = Credentials(
        token=info.get("token"),
        refresh_token=info["refresh_token"],
        token_uri=info["token_uri"],
        client_id=info["client_id"],
        client_secret=info["client_secret"],
        scopes=info.get("scopes"),
    )
    return BetaAnalyticsDataClient(credentials=creds)


def run(c: BetaAnalyticsDataClient, *, dims: list[str], metrics: list[str], start: str, end: str, limit: int = 100000, order_by: list[OrderBy] | None = None):
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name=d) for d in dims],
        metrics=[Metric(name=m) for m in metrics],
        limit=limit,
        order_bys=order_by or [],
    )
    return c.run_report(req)


def write_csv(path: Path, header: list[str], rows: list[list[str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="") as f:
        w = csv.writer(f)
        w.writerow(header)
        w.writerows(rows)
    print(f"wrote {path.relative_to(ROOT)}  ({len(rows)} rows)")


def report_to_rows(resp, dim_count: int) -> list[list[str]]:
    out = []
    for row in resp.rows:
        dims = [v.value for v in row.dimension_values]
        mets = [v.value for v in row.metric_values]
        out.append(dims + mets)
    return out


def main() -> None:
    c = client()

    # 1. JFM 2026 daily traffic
    r = run(c, dims=["date"], metrics=["totalUsers", "newUsers", "sessions", "screenPageViews", "engagementRate", "averageSessionDuration"], start="2026-01-01", end="2026-03-31")
    write_csv(OUT / "traffic_jfm_2026.csv",
              ["date", "users", "new_users", "sessions", "pageviews", "engagement_rate", "avg_session_duration_s"],
              report_to_rows(r, 1))

    # 2. Monthly traffic Apr 2024 - Apr 2026 (YoY)
    r = run(c, dims=["yearMonth"], metrics=["totalUsers", "newUsers", "sessions", "screenPageViews", "engagementRate"], start="2024-04-01", end="2026-04-30")
    write_csv(OUT / "traffic_yoy_monthly.csv",
              ["year_month", "users", "new_users", "sessions", "pageviews", "engagement_rate"],
              sorted(report_to_rows(r, 1)))

    # 3. Top pages JFM 2026
    r = run(c, dims=["pagePath", "pageTitle"], metrics=["screenPageViews", "totalUsers", "averageSessionDuration", "bounceRate"], start="2026-01-01", end="2026-03-31",
            order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)], limit=200)
    write_csv(OUT / "top_pages_jfm_2026.csv",
              ["page_path", "page_title", "pageviews", "users", "avg_session_duration_s", "bounce_rate"],
              report_to_rows(r, 2))

    # 4. Channel breakdown JFM 2026
    r = run(c, dims=["sessionDefaultChannelGroup"], metrics=["totalUsers", "newUsers", "sessions", "screenPageViews"], start="2026-01-01", end="2026-03-31",
            order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)])
    write_csv(OUT / "channel_jfm_2026.csv",
              ["channel", "users", "new_users", "sessions", "pageviews"],
              report_to_rows(r, 1))

    # 5. Channel breakdown YoY (each window separately)
    for label, start, end in [("apr2024_mar2025", "2024-04-01", "2025-03-31"), ("apr2025_mar2026", "2025-04-01", "2026-03-31")]:
        r = run(c, dims=["sessionDefaultChannelGroup"], metrics=["totalUsers", "newUsers", "sessions", "screenPageViews"], start=start, end=end,
                order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)])
        write_csv(OUT / f"channel_{label}.csv",
                  ["channel", "users", "new_users", "sessions", "pageviews"],
                  report_to_rows(r, 1))

    # 6. Source/medium for JFM 2026 (more granular than channel)
    r = run(c, dims=["sessionSource", "sessionMedium"], metrics=["totalUsers", "newUsers", "sessions"], start="2026-01-01", end="2026-03-31",
            order_by=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)], limit=100)
    write_csv(OUT / "source_medium_jfm_2026.csv",
              ["source", "medium", "users", "new_users", "sessions"],
              report_to_rows(r, 2))


if __name__ == "__main__":
    main()

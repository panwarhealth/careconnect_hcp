"""For each blog post, pull pageviews + 15-second-reads + scroll (90%) events.
This gives a proxy funnel: landed → stayed 15s → scrolled to ~90%."""
from __future__ import annotations

import csv
import json
from pathlib import Path

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
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
OUT = ROOT / "reports" / "out" / "ga4"
PROPERTY_ID = "306115293"


def client() -> BetaAnalyticsDataClient:
    creds = Credentials.from_authorized_user_file(str(TOKEN_JSON))
    if not creds.valid:
        from google.auth.transport.requests import Request
        creds.refresh(Request())
        TOKEN_JSON.write_text(creds.to_json())
    return BetaAnalyticsDataClient(credentials=creds)


def page_event_counts(c: BetaAnalyticsDataClient, event_name: str, start: str, end: str) -> dict[str, tuple[int, int]]:
    """Return {pagePath: (event_count, users)} for a specific event_name on /blog/* pages."""
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="eventCount"), Metric(name="totalUsers")],
        dimension_filter=FilterExpression(
            and_group={
                "expressions": [
                    {"filter": {"field_name": "eventName", "string_filter": {"value": event_name}}},
                    {"filter": {"field_name": "pagePath", "string_filter": {"match_type": "BEGINS_WITH", "value": "/blog/"}}},
                ]
            }
        ),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)],
        limit=200,
    )
    resp = c.run_report(req)
    return {row.dimension_values[0].value: (int(row.metric_values[0].value), int(row.metric_values[1].value)) for row in resp.rows}


def main():
    c = client()
    start, end = "2025-04-01", "2026-03-31"

    pageviews = page_event_counts(c, "page_view", start, end)
    fifteen_sec = page_event_counts(c, "15 Second Page View", start, end)
    scrolls = page_event_counts(c, "scroll", start, end)

    paths = sorted(set(pageviews) | set(fifteen_sec) | set(scrolls), key=lambda p: -pageviews.get(p, (0, 0))[0])

    rows = []
    for p in paths:
        pv, pv_users = pageviews.get(p, (0, 0))
        f15, f15_users = fifteen_sec.get(p, (0, 0))
        sc, sc_users = scrolls.get(p, (0, 0))
        if pv == 0:
            continue
        rows.append({
            "page_path": p,
            "pageviews": pv,
            "users_pageview": pv_users,
            "started_reading_15s": f15,
            "users_15s": f15_users,
            "scrolled_to_bottom": sc,
            "users_scrolled": sc_users,
            "pct_started_reading": round(100 * f15 / pv, 1) if pv else 0,
            "pct_scrolled_of_started": round(100 * sc / f15, 1) if f15 else 0,
            "pct_completed_read": round(100 * sc / pv, 1) if pv else 0,
        })

    out_path = OUT / "article_read_depth_apr2025_mar2026.csv"
    with out_path.open("w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=rows[0].keys())
        w.writeheader()
        w.writerows(rows)
    print(f"wrote {out_path.relative_to(ROOT)}  ({len(rows)} blog pages)")
    for r in rows[:15]:
        print(f"  {r['page_path'][:80]:80}  pv={r['pageviews']:>5}  15s={r['started_reading_15s']:>4} ({r['pct_started_reading']:>5}%)  scrolled={r['scrolled_to_bottom']:>4} ({r['pct_completed_read']:>5}%)")


if __name__ == "__main__":
    main()

"""Pull daily GA4 totals for last 90 days. Look for a cliff edge where traffic collapses.

    python reports/ga4/daily_trend.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4

from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Metric,
    RunReportRequest,
)


def main() -> None:
    client = ga4.client()
    resp = client.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date="90daysAgo", end_date="today")],
        dimensions=[Dimension(name="date")],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="screenPageViews"),
            Metric(name="eventCount"),
        ],
        order_bys=[{"dimension": {"dimension_name": "date"}}],
    ))
    print(f"{'date':<12}{'users':>10}{'sessions':>12}{'pageviews':>12}{'events':>12}")
    print("-" * 58)
    for row in resp.rows:
        date = row.dimension_values[0].value
        users, sessions, views, events = (v.value for v in row.metric_values)
        # Format YYYYMMDD -> YYYY-MM-DD
        d = f"{date[:4]}-{date[4:6]}-{date[6:8]}"
        print(f"{d:<12}{users:>10}{sessions:>12}{views:>12}{events:>12}")


if __name__ == "__main__":
    main()

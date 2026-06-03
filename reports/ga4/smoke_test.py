"""Verify the GA4 token + property ID work — pulls last 7 days of users.

    python reports/ga4/smoke_test.py
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
    request = RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date="7daysAgo", end_date="today")],
        dimensions=[Dimension(name="date")],
        metrics=[Metric(name="totalUsers"), Metric(name="sessions"), Metric(name="screenPageViews")],
    )
    response = client.run_report(request)
    print(f"{'date':<12}{'users':>10}{'sessions':>12}{'pageviews':>12}")
    for row in response.rows:
        date = row.dimension_values[0].value
        users, sessions, views = (v.value for v in row.metric_values)
        print(f"{date:<12}{users:>10}{sessions:>12}{views:>12}")


if __name__ == "__main__":
    main()

"""Post-fix MCA traffic check. Last 21 days, MCA-relevant paths + daily trend."""
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Metric, RunReportRequest, Filter, FilterExpression,
    FilterExpressionList,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"


def credentials() -> Credentials:
    info = json.loads(TOKEN_JSON.read_text())
    creds = Credentials(
        token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"),
    )
    creds.refresh(Request())
    TOKEN_JSON.write_text(creds.to_json())
    return creds


def main() -> None:
    client = BetaAnalyticsDataClient(credentials=credentials())

    # MCA-relevant paths: course, lessons, survey, register, the landing page
    contains = lambda v: FilterExpression(filter=Filter(
        field_name="pagePath",
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.CONTAINS, value=v, case_sensitive=False)))
    path_filter = FilterExpression(or_group=FilterExpressionList(expressions=[
        contains("mini-clinical-audit"),
        contains("/register"),
        contains("anal-fissures-breaking"),
        contains("/courses/"),
        contains("survey"),
    ]))

    print("=== MCA-related page views, by path × day (last 21 days) ===")
    resp = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="21daysAgo", end_date="today")],
        dimensions=[Dimension(name="date"), Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=path_filter,
        order_bys=[{"dimension": {"dimension_name": "date"}}],
        limit=500,
    ))
    print(f"{'date':<12}{'views':>7}{'users':>7}  path")
    print("-" * 80)
    for row in resp.rows:
        date = row.dimension_values[0].value
        path = row.dimension_values[1].value
        views, users = (v.value for v in row.metric_values)
        d = f"{date[:4]}-{date[4:6]}-{date[6:8]}"
        print(f"{d:<12}{views:>7}{users:>7}  {path[:55]}")

    # Aggregate by path over the window
    print("\n=== Same paths, aggregated over 21 days ===")
    resp2 = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="21daysAgo", end_date="today")],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers"), Metric(name="sessions")],
        dimension_filter=path_filter,
        order_bys=[{"metric": {"metric_name": "screenPageViews"}, "desc": True}],
        limit=100,
    ))
    print(f"{'views':>7}{'users':>7}{'sess':>7}  path")
    print("-" * 80)
    for row in resp2.rows:
        path = row.dimension_values[0].value
        views, users, sess = (v.value for v in row.metric_values)
        print(f"{views:>7}{users:>7}{sess:>7}  {path[:58]}")

    # Event check: login + any form/signup events, daily
    print("\n=== Key events (last 21 days) ===")
    ev_filter = FilterExpression(or_group=FilterExpressionList(expressions=[
        FilterExpression(filter=Filter(field_name="eventName",
            string_filter=Filter.StringFilter(value="login"))),
        FilterExpression(filter=Filter(field_name="eventName",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.CONTAINS, value="form"))),
        FilterExpression(filter=Filter(field_name="eventName",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.CONTAINS, value="sign"))),
    ]))
    resp3 = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="21daysAgo", end_date="today")],
        dimensions=[Dimension(name="eventName")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=ev_filter,
        order_bys=[{"metric": {"metric_name": "eventCount"}, "desc": True}],
        limit=50,
    ))
    print(f"{'count':>9}  event")
    print("-" * 40)
    for row in resp3.rows:
        print(f"{row.metric_values[0].value:>9}  {row.dimension_values[0].value}")


if __name__ == "__main__":
    main()

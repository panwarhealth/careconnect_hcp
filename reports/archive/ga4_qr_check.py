"""Check whether the FY27 sample-bag QR campaign has hit GA4 yet.

Queries today's traffic filtered to source=sample-bag / campaign=fy27-sample-bags-conference.
"""
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    FilterExpressionList,
    Metric,
    RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"

CAMPAIGN = "fy27-sample-bags-conference"
SOURCE = "sample-bag"
MEDIUM = "qr"


def credentials() -> Credentials:
    info = json.loads(TOKEN_JSON.read_text())
    creds = Credentials(
        token=info.get("token"),
        refresh_token=info["refresh_token"],
        token_uri=info["token_uri"],
        client_id=info["client_id"],
        client_secret=info["client_secret"],
        scopes=info.get("scopes"),
    )
    creds.refresh(Request())
    TOKEN_JSON.write_text(creds.to_json())
    return creds


def eq(name: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=name, string_filter=Filter.StringFilter(value=value)))


def main() -> None:
    client = BetaAnalyticsDataClient(credentials=credentials())

    print(f"Looking for source={SOURCE} / medium={MEDIUM} / campaign={CAMPAIGN}\n")

    print("--- Strict match (source AND medium AND campaign), today ---")
    strict = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="today", end_date="today")],
        dimensions=[
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="screenPageViews")],
        dimension_filter=FilterExpression(and_group=FilterExpressionList(expressions=[
            eq("sessionSource", SOURCE),
            eq("sessionMedium", MEDIUM),
            eq("sessionCampaignName", CAMPAIGN),
        ])),
    ))
    if not strict.rows:
        print("  (no rows — strict match found nothing)")
    for row in strict.rows:
        src, med, cmp = (v.value for v in row.dimension_values)
        sess, usr, pv = (v.value for v in row.metric_values)
        print(f"  {src:<14} {med:<6} {cmp:<40} sessions={sess} users={usr} pv={pv}")

    print("\n--- Loose match (campaign only), last 2 days ---")
    loose = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="2daysAgo", end_date="today")],
        dimensions=[
            Dimension(name="date"),
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=eq("sessionCampaignName", CAMPAIGN),
    ))
    if not loose.rows:
        print("  (no rows — campaign name not seen in last 2 days)")
    for row in loose.rows:
        date, src, med, cmp = (v.value for v in row.dimension_values)
        sess, usr = (v.value for v in row.metric_values)
        print(f"  {date}  {src:<14} {med:<6} {cmp:<40} sessions={sess} users={usr}")

    print("\n--- Sanity: ALL sources/mediums seen today (top 20 by sessions) ---")
    today = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="today", end_date="today")],
        dimensions=[
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
            Dimension(name="sessionCampaignName"),
        ],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        limit=20,
    ))
    if not today.rows:
        print("  (no traffic at all today — API may be lagged; try again in a few minutes)")
    for row in today.rows:
        src, med, cmp = (v.value for v in row.dimension_values)
        sess, usr = (v.value for v in row.metric_values)
        print(f"  {src:<20} {med:<12} {cmp:<40} sessions={sess} users={usr}")


if __name__ == "__main__":
    main()

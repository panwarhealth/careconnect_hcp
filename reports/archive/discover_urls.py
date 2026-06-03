"""Discovery script: pull top landing pages + top all-pageview pages so we can
inspect what video / factsheet / resource URLs actually look like before writing
section regexes."""
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Metric, OrderBy, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT        = Path(__file__).resolve().parent.parent
TOKEN_JSON  = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"
START, END  = "2025-04-01", "2026-03-31"


def creds():
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(
        token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"),
    )
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return c


def main():
    ga = BetaAnalyticsDataClient(credentials=creds())

    print("=== Top 200 landing pages (FY26, all channels) ===")
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=200,
    ))
    for r in resp.rows:
        path = r.dimension_values[0].value
        sess = int(r.metric_values[0].value)
        print(f"  {sess:>5,}  {path}")

    print("\n=== Top 100 pages by pageviews (FY26) - to find video/factsheet URLs ===")
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=200,
    ))
    for r in resp.rows:
        path = r.dimension_values[0].value
        pv   = int(r.metric_values[0].value)
        print(f"  {pv:>6,}  {path}")


if __name__ == "__main__":
    main()

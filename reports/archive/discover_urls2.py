"""Targeted discovery: pull all landing pages matching resource / video / factsheet
patterns to see what individual pages exist beyond the hub URLs."""
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, Metric, OrderBy, RunReportRequest,
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


def query(ga, regex, label):
    print(f"\n=== Pages matching {label}: {regex} ===")
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews"), Metric(name="sessions")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                value=regex,
            ),
        )),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=200,
    ))
    if not resp.rows:
        print("  (no matches)")
        return
    for r in resp.rows:
        path = r.dimension_values[0].value
        pv = int(r.metric_values[0].value)
        sess = int(r.metric_values[1].value)
        print(f"  pv={pv:>6,} sess={sess:>5,}  {path}")


def main():
    ga = BetaAnalyticsDataClient(credentials=creds())
    query(ga, r"^/resources", "/resources*")
    query(ga, r"^/videos", "/videos*")
    query(ga, r"^/factsheet", "/factsheet*")
    query(ga, r"^/patient-resource", "/patient-resource*")
    query(ga, r"^/clinical-paper", "/clinical-paper*")
    query(ga, r"^/guide", "/guide*")
    query(ga, r"wp-content/uploads", "wp-content/uploads (PDFs etc)")
    query(ga, r"^/tools-and-videos", "/tools-and-videos*")
    query(ga, r"video", "any path containing 'video'")


if __name__ == "__main__":
    main()

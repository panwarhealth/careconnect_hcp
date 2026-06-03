"""Hit GA4's Realtime API. Realtime API has limited dims — no source/medium/campaign.
Just confirms whether GA4 currently sees an active user on the site.
"""
import json
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    Dimension,
    Metric,
    RunRealtimeReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"


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


def main() -> None:
    client = BetaAnalyticsDataClient(credentials=credentials())

    print("=== Realtime: total active users (last 30 min) ===")
    rt = client.run_realtime_report(RunRealtimeReportRequest(
        property=f"properties/{PROPERTY_ID}",
        metrics=[Metric(name="activeUsers")],
    ))
    if not rt.rows:
        print("  zero active users")
    for row in rt.rows:
        print(f"  active users: {row.metric_values[0].value}")

    print("\n=== Realtime: active users by page (last 30 min) ===")
    rt2 = client.run_realtime_report(RunRealtimeReportRequest(
        property=f"properties/{PROPERTY_ID}",
        dimensions=[Dimension(name="unifiedScreenName")],
        metrics=[Metric(name="activeUsers"), Metric(name="screenPageViews")],
    ))
    if not rt2.rows:
        print("  (none)")
    for row in rt2.rows:
        page = row.dimension_values[0].value
        users, pv = (v.value for v in row.metric_values)
        print(f"  users={users:<3} pv={pv:<3}  page={page!r}")

    print("\n=== Realtime: by minutes-ago ===")
    rt3 = client.run_realtime_report(RunRealtimeReportRequest(
        property=f"properties/{PROPERTY_ID}",
        dimensions=[Dimension(name="minutesAgo")],
        metrics=[Metric(name="activeUsers")],
    ))
    if not rt3.rows:
        print("  (none)")
    for row in sorted(rt3.rows, key=lambda r: int(r.dimension_values[0].value)):
        ago = row.dimension_values[0].value
        users = row.metric_values[0].value
        print(f"  {ago:>3} min ago: {users} users")


if __name__ == "__main__":
    main()

"""
Audit / sanity-check Sonnet's CAPH0062 topline numbers.

Checks:
  A. Per-day sessions both windows (spot anomalies, weekend effect, etc.)
  B. Landing-page reconciliation (sessions don't appear to sum)
  C. All campaigns active in baseline window (was it really a clean baseline?)
  D. Normalised per-day uplift (post 6d vs pre 7d)
  E. New vs returning user split in post window
"""
import json
from datetime import date, timedelta
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT        = Path(__file__).resolve().parent.parent
TOKEN_JSON  = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"

SEND_DATE   = "2026-05-22"
TODAY       = date.today().isoformat()
PRE_START   = (date.fromisoformat(SEND_DATE) - timedelta(days=7)).isoformat()
PRE_END     = (date.fromisoformat(SEND_DATE) - timedelta(days=1)).isoformat()


def ga():
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"))
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return BetaAnalyticsDataClient(credentials=c)


def main():
    c = ga()

    print("\n" + "="*72)
    print("AUDIT — CAPH0062 numbers sanity check")
    print("="*72)
    print(f"Post window: {SEND_DATE} → {TODAY}  ({(date.fromisoformat(TODAY) - date.fromisoformat(SEND_DATE)).days + 1} days)")
    print(f"Pre window:  {PRE_START} → {PRE_END}  (7 days)")

    # ----------------------------------------------------------------
    # A. Per-day site-wide sessions, both windows
    # ----------------------------------------------------------------
    print("\n--- A. Per-day site-wide sessions ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=PRE_START, end_date=TODAY)],
        dimensions=[Dimension(name="date")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="newUsers")],
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"))],
    ))
    print(f"  {'date':<12}{'dow':<5}{'sessions':>10}{'users':>8}{'new':>6}  marker")
    for r in resp.rows:
        d = r.dimension_values[0].value
        dt = date.fromisoformat(f"{d[:4]}-{d[4:6]}-{d[6:]}")
        dow = dt.strftime("%a")
        s = r.metric_values[0].value
        u = r.metric_values[1].value
        nu = r.metric_values[2].value
        marker = "← SEND" if d == SEND_DATE.replace("-","") else ""
        print(f"  {dt.isoformat():<12}{dow:<5}{s:>10}{u:>8}{nu:>6}  {marker}")

    # ----------------------------------------------------------------
    # B. Per-day CAPH0062 sessions
    # ----------------------------------------------------------------
    print("\n--- B. Per-day CAPH0062 sessions ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=PRE_START, end_date=TODAY)],
        dimensions=[Dimension(name="date")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="newUsers")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="sessionCampaignName",
            string_filter=Filter.StringFilter(value="CAPH0062"))),
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"))],
    ))
    for r in resp.rows:
        d = r.dimension_values[0].value
        dt = date.fromisoformat(f"{d[:4]}-{d[4:6]}-{d[6:]}")
        dow = dt.strftime("%a")
        s = r.metric_values[0].value
        u = r.metric_values[1].value
        nu = r.metric_values[2].value
        print(f"  {dt.isoformat()}  {dow}  sessions={s}  users={u}  new={nu}")

    # ----------------------------------------------------------------
    # C. Landing page reconciliation — totals must sum
    # ----------------------------------------------------------------
    print("\n--- C. Landing-page reconciliation (post-send, CAPH0062 only) ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="sessionCampaignName",
            string_filter=Filter.StringFilter(value="CAPH0062"))),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
    ))
    total_lp = 0
    print(f"  {'landingPage':<70}{'sessions':>10}")
    for r in resp.rows:
        lp = r.dimension_values[0].value
        s = int(r.metric_values[0].value)
        total_lp += s
        print(f"  {lp[:70]:<70}{s:>10}")
    print(f"  {'SUM':<70}{total_lp:>10}")

    # ----------------------------------------------------------------
    # D. All campaigns active in baseline window (was it clean?)
    # ----------------------------------------------------------------
    print("\n--- D. Campaigns active in baseline week (top 15) ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=PRE_START, end_date=PRE_END)],
        dimensions=[Dimension(name="sessionCampaignName"), Dimension(name="sessionMedium")],
        metrics=[Metric(name="sessions")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=15,
    ))
    for r in resp.rows:
        camp = r.dimension_values[0].value
        med = r.dimension_values[1].value
        s = r.metric_values[0].value
        print(f"  camp={camp:<25}  medium={med:<10}  sessions={s}")

    # ----------------------------------------------------------------
    # E. All medium splits for CAPH0062 (check it really is 'email')
    # ----------------------------------------------------------------
    print("\n--- E. CAPH0062 by source × medium ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        dimensions=[Dimension(name="sessionSource"), Dimension(name="sessionMedium")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="newUsers")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="sessionCampaignName",
            string_filter=Filter.StringFilter(value="CAPH0062"))),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
    ))
    total = 0
    for r in resp.rows:
        src = r.dimension_values[0].value
        med = r.dimension_values[1].value
        s = int(r.metric_values[0].value)
        u = r.metric_values[1].value
        nu = r.metric_values[2].value
        total += s
        print(f"  src={src:<28}  med={med:<8}  sessions={s:>4}  users={u:>4}  new={nu:>4}")
    print(f"  TOTAL sessions: {total}")

    # ----------------------------------------------------------------
    # F. Engagement check — did the campaign traffic actually engage?
    # ----------------------------------------------------------------
    print("\n--- F. Engagement metrics for CAPH0062 ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        metrics=[
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="averageSessionDuration"),
            Metric(name="screenPageViewsPerSession"),
            Metric(name="bounceRate"),
        ],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="sessionCampaignName",
            string_filter=Filter.StringFilter(value="CAPH0062"))),
    ))
    if resp.rows:
        r = resp.rows[0]
        sessions  = int(r.metric_values[0].value)
        engaged   = int(r.metric_values[1].value)
        avg_dur   = float(r.metric_values[2].value)
        pps       = float(r.metric_values[3].value)
        bounce    = float(r.metric_values[4].value)
        print(f"  sessions:        {sessions}")
        print(f"  engaged:         {engaged} ({engaged/sessions*100:.0f}%)")
        print(f"  avg duration:    {avg_dur:.1f}s  ({avg_dur/60:.2f} min)")
        print(f"  pages/session:   {pps:.2f}")
        print(f"  bounce rate:     {bounce*100:.0f}%")

    # ----------------------------------------------------------------
    # G. Did anyone actually hit /register/ or course pages?
    # ----------------------------------------------------------------
    print("\n--- G. Page-paths visited (not just landings), CAPH0062 only ---")
    resp = c.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="sessionCampaignName",
            string_filter=Filter.StringFilter(value="CAPH0062"))),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=20,
    ))
    for r in resp.rows:
        path = r.dimension_values[0].value
        v = r.metric_values[0].value
        u = r.metric_values[1].value
        print(f"  {path[:70]:<70}  views={v}  users={u}")


if __name__ == "__main__":
    main()

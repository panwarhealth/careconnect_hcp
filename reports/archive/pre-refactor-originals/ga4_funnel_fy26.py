"""
FY26 (Apr 2025 – Mar 2026) funnel data for QBR.
1. Channel summary
2. Email-channel pageviews by section
3. Top 15 pages, Email channel
"""
import csv
import json
import re
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
START, END  = "2025-04-01", "2026-03-31"
OUT         = ROOT / "reports" / "out"

WINDOWS_DL  = Path("/mnt/c/Users/User/Downloads")


def creds() -> Credentials:
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(
        token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"),
    )
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return c


def client() -> BetaAnalyticsDataClient:
    return BetaAnalyticsDataClient(credentials=creds())


def email_filter() -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name="sessionDefaultChannelGroup",
        string_filter=Filter.StringFilter(value="Email"),
    ))


# --- URL → section bucket ---------------------------------------------------
SECTION_RULES = [
    # MCA = RACGP Mini Clinical Audit specifically (under /courses/mini-clinical-audit/)
    ("MCA",          r"^/courses/mini-clinical-audit|^/mini-clinical-audit|/retrospective|/complete-mini"),
    # Sample ordering
    ("Sample order", r"^/order-samples|^/order-patient-demo|^/product-demo"),
    # Downloadable resources / PDFs
    ("Resources",    r"^/resources|/factsheet|/patient-resource|/clinical-paper|/guide|wp-content/uploads.*\.pdf"),
    # All blog + standalone long-form content pages
    ("Blog/Article", r"^/blog|^/nasal-and-sinus-health|^/topics/"),
    # Brand / product info pages
    ("Brands",       r"^/brands/"),
    # Account, login, registration, welcome flows
    ("Account/Auth", r"^/log-in|^/login|^/register|^/edit-your-profile|^/my-account|^/dashboard|^/profile|^/welcome|^/forgot-password"),
    # Homepage
    ("Homepage",     r"^/$"),
    # Interactive tools and demos
    ("Tools",        r"^/allergy-analyser|^/interactive-model|^/rectogesic-3d-model|^/fess-demonstration|^/interactivetools|^/section\d|^/tip\d|^/option\d|^/bake$|^/clues$|^/chart$|^/detection$|^/mist$"),
    # Other CPD courses (not MCA) e.g. anal fissures course
    ("Courses/LMS",  r"^/courses|^/lessons|^/quizzes|^/quiz|^/certificate|^/anal-fissures-breaking"),
]

def bucket(path: str) -> str:
    if not path or path.strip() in ("(not set)", ""):
        return "(not set)"
    p = path.split("?")[0].rstrip("/") or "/"
    for label, pattern in SECTION_RULES:
        if re.search(pattern, p, re.IGNORECASE):
            return label
    return "Other"


def normalise_path(path: str) -> str:
    """Strip trailing slash, keep root as /."""
    p = path.split("?")[0]
    return p.rstrip("/") or "/"


# --- 1. Channel summary -----------------------------------------------------
def channel_summary(ga: BetaAnalyticsDataClient) -> list[dict]:
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="sessionDefaultChannelGroup")],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="screenPageViews"),
        ],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
    ))
    rows = []
    for r in resp.rows:
        ch = r.dimension_values[0].value
        users, sessions, pv = (int(v.value) for v in r.metric_values)
        # Bucket into Email / Direct / Unassigned / Other
        if ch == "Email":
            bucket_ch = "Email"
        elif ch == "Direct":
            bucket_ch = "Direct"
        elif ch in ("Unassigned", "(not set)"):
            bucket_ch = "Unassigned"
        else:
            bucket_ch = "Other"
        rows.append({"channel_raw": ch, "channel": bucket_ch,
                     "users": users, "sessions": sessions, "pageviews": pv})
    # Collapse into 4 buckets
    buckets: dict[str, dict] = {}
    for r in rows:
        b = r["channel"]
        if b not in buckets:
            buckets[b] = {"channel": b, "users": 0, "sessions": 0, "pageviews": 0}
        buckets[b]["users"]    += r["users"]
        buckets[b]["sessions"] += r["sessions"]
        buckets[b]["pageviews"]+= r["pageviews"]
    order = ["Email", "Direct", "Unassigned", "Other"]
    result = [buckets[b] for b in order if b in buckets]
    total_sess = sum(r["sessions"] for r in result)
    total_pv   = sum(r["pageviews"] for r in result)
    total_users= sum(r["users"] for r in result)
    for r in result:
        r["sessions_pct"]  = f"{r['sessions']/total_sess*100:.1f}%"
        r["pageviews_pct"] = f"{r['pageviews']/total_pv*100:.1f}%"
    result.append({"channel": "TOTAL", "users": total_users,
                   "sessions": total_sess, "pageviews": total_pv,
                   "sessions_pct": "100%", "pageviews_pct": "100%"})
    return result


# --- 2. Email pageviews by section ------------------------------------------
def email_by_section(ga: BetaAnalyticsDataClient) -> list[dict]:
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath"), Dimension(name="pageTitle")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=email_filter(),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=500,
    ))
    # Aggregate by section
    sections: dict[str, dict] = {}
    total_pv = 0
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        pv   = int(r.metric_values[0].value)
        usr  = int(r.metric_values[1].value)
        sec  = bucket(path)
        total_pv += pv
        if sec not in sections:
            sections[sec] = {"section": sec, "pageviews": 0, "users": 0}
        sections[sec]["pageviews"] += pv
        sections[sec]["users"]     += usr
    result = sorted(sections.values(), key=lambda x: x["pageviews"], reverse=True)
    for r in result:
        r["pv_pct"] = f"{r['pageviews']/total_pv*100:.1f}%"
    return result


# --- 3. Top 15 email pages --------------------------------------------------
def top_email_pages(ga: BetaAnalyticsDataClient) -> list[dict]:
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath"), Dimension(name="pageTitle")],
        metrics=[Metric(name="screenPageViews"), Metric(name="totalUsers")],
        dimension_filter=email_filter(),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True)],
        limit=200,
    ))
    # Normalise + consolidate trailing-slash variants
    consolidated: dict[str, dict] = {}
    for r in resp.rows:
        raw_path = r.dimension_values[0].value
        title    = r.dimension_values[1].value
        path     = normalise_path(raw_path)
        pv       = int(r.metric_values[0].value)
        usr      = int(r.metric_values[1].value)
        if path not in consolidated:
            consolidated[path] = {"path": path, "title": title,
                                  "section": bucket(path), "pageviews": 0, "users": 0}
        consolidated[path]["pageviews"] += pv
        consolidated[path]["users"]     += usr
    sorted_pages = sorted(consolidated.values(), key=lambda x: x["pageviews"], reverse=True)
    return sorted_pages[:15]


def write_csv(rows: list[dict], filename: str) -> None:
    if not rows:
        return
    path = OUT / filename
    with path.open("w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        writer.writeheader()
        writer.writerows(rows)
    print(f"  Written: {path}")
    dl = WINDOWS_DL / filename
    import shutil
    shutil.copy(path, dl)
    print(f"  Copied:  {dl}")


def main() -> None:
    ga = client()

    print("=== 1. Channel summary (FY26) ===")
    ch = channel_summary(ga)
    write_csv(ch, "fy26_channel_summary.csv")
    print(f"\n{'Channel':<14} {'Users':>8} {'Sessions':>10} {'Sess%':>8} {'Pageviews':>12} {'PV%':>8}")
    print("-" * 62)
    for r in ch:
        print(f"{r['channel']:<14} {r['users']:>8,} {r['sessions']:>10,} {r['sessions_pct']:>8} {r['pageviews']:>12,} {r['pageviews_pct']:>8}")

    print("\n=== 2. Email pageviews by section ===")
    sec = email_by_section(ga)
    write_csv(sec, "fy26_email_by_section.csv")
    print(f"\n{'Section':<16} {'Pageviews':>10} {'PV%':>8} {'Users':>8}")
    print("-" * 44)
    for r in sec:
        print(f"{r['section']:<16} {r['pageviews']:>10,} {r['pv_pct']:>8} {r['users']:>8,}")

    print("\n=== 3. Top 15 pages, Email channel ===")
    pages = top_email_pages(ga)
    write_csv(pages, "fy26_email_top_pages.csv")
    print(f"\n{'Section':<14} {'PV':>6} {'Users':>6}  {'Path'}")
    print("-" * 72)
    for r in pages:
        print(f"{r['section']:<14} {r['pageviews']:>6,} {r['users']:>6,}  {r['path'][:50]}")

    print("\n=== URL bucketing rules used ===")
    for label, pattern in SECTION_RULES:
        print(f"  {label:<14}  {pattern}")


if __name__ == "__main__":
    main()

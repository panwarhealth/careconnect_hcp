"""Per-URL merge of Mailchimp campaign click-details with GA4 attribution.

For every Mailchimp campaign in the window:
  - Pulls the click-details list (one row per tracked URL in the email).
  - For UTM-tagged campaigns, pulls matching GA4 sessions/users by
    (sessionCampaignName, landingPagePlusQueryString) and merges per URL.
  - For untagged campaigns, records Mailchimp click-details only (the only
    signal we have).

Discrepancies are surfaced. GA4 takes precedence on conflicts (bot inflation
on the Mailchimp side is a known issue; GA4 only counts actual page loads).

Outputs:
  reports/out/mailchimp/mc_ga4_url_merge_<slug>.csv   one row per (campaign, URL)
  reports/out/mailchimp/mc_ga4_summary_<slug>.csv     one row per campaign (totals + status)

    python reports/mailchimp/url_merge.py            # default window 2025-01-01..2026-05-01
    python reports/mailchimp/url_merge.py --period FY26
    python reports/mailchimp/url_merge.py --start 2025-01-01 --end 2026-05-01
"""
from __future__ import annotations

import argparse
import os
import re
import sys
import time
from collections import defaultdict
from pathlib import Path
from urllib.parse import urlparse

import requests
from dotenv import load_dotenv
from requests.auth import HTTPBasicAuth

from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Metric,
    RunReportRequest,
)

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

OUT = io.out_dir("mailchimp")

# Default custom window (non-FY); --period / --start/--end override it.
DEFAULT_WINDOW_START = "2025-01-01"
DEFAULT_WINDOW_END = "2026-05-01"
PORTAL_HOSTS = {"hcp.carepharma.com.au", "www.hcp.carepharma.com.au"}

# This script talks to the raw Mailchimp HTTP API (not the SDK) because it uses
# field-filtering and raw click-details shapes the SDK wrapper doesn't expose
# cleanly. Credentials come from .env — the same vars lib.mailchimp uses.
load_dotenv(io.PROJECT_ROOT / ".env")
MC_DC = os.environ["MAILCHIMP_DC"]
MC_BASE = f"https://{MC_DC}.api.mailchimp.com/3.0"
MC_AUTH = HTTPBasicAuth("anystring", os.environ["MAILCHIMP_API_KEY"])


def mc_get(path: str, params: dict | None = None) -> dict:
    r = requests.get(MC_BASE + path, auth=MC_AUTH, params=params, timeout=60)
    r.raise_for_status()
    return r.json()


def fetch_campaigns(window_start: str, window_end: str) -> list[dict]:
    out: list[dict] = []
    offset = 0
    fields = (
        "campaigns.id,"
        "campaigns.send_time,"
        "campaigns.settings.title,"
        "campaigns.settings.subject_line,"
        "campaigns.recipients.recipient_count,"
        "campaigns.tracking,"
        "campaigns.report_summary,"
        "total_items"
    )
    while True:
        d = mc_get(
            "/campaigns",
            {
                "since_send_time": f"{window_start}T00:00:00Z",
                "before_send_time": f"{window_end}T00:00:00Z",
                "count": 100,
                "offset": offset,
                "status": "sent",
                "fields": fields,
            },
        )
        out.extend(d.get("campaigns", []))
        offset += 100
        total = d.get("total_items", 0)
        if offset >= total or not d.get("campaigns"):
            break
    return out


def fetch_click_details(campaign_id: str) -> list[dict]:
    """One row per tracked URL in the email."""
    d = mc_get(f"/reports/{campaign_id}/click-details", {"count": 1000})
    return d.get("urls_clicked", [])


def fetch_ga4_by_campaign_url(client, window_start: str, window_end: str) -> dict:
    """Return {(cleaned_campaign, url_path): {sessions, users, new_users}}."""
    out: dict[tuple[str, str], dict[str, int]] = defaultdict(
        lambda: {"sessions": 0, "users": 0, "new_users": 0}
    )
    LIST_PREFIX = re.compile(r"^[0-9a-f]{10}-")

    request = RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=window_start, end_date=window_end)],
        dimensions=[
            Dimension(name="sessionCampaignName"),
            Dimension(name="landingPagePlusQueryString"),
        ],
        metrics=[
            Metric(name="sessions"),
            Metric(name="totalUsers"),
            Metric(name="newUsers"),
        ],
        limit=100000,
    )
    r = client.run_report(request)
    for row in r.rows:
        camp = row.dimension_values[0].value or ""
        path = row.dimension_values[1].value or ""
        cleaned = LIST_PREFIX.sub("", camp)
        path_stripped = path.split("?", 1)[0].rstrip("/").lower()
        key = (cleaned, path_stripped)
        out[key]["sessions"] += int(row.metric_values[0].value)
        out[key]["users"] += int(row.metric_values[1].value)
        out[key]["new_users"] += int(row.metric_values[2].value)
    return out


def normalize_url_for_match(url: str) -> tuple[str, str]:
    """Return (host, path_lower_no_qs_no_trailing_slash)."""
    p = urlparse(url)
    host = (p.netloc or "").lower()
    path = (p.path or "").rstrip("/").lower()
    return host, path


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default=None)
    args = ap.parse_args()
    if args.period or args.start or args.end:
        p = periods.from_args(args)
        window_start, window_end, slug = p.start, p.end, p.slug
    else:
        window_start, window_end = DEFAULT_WINDOW_START, DEFAULT_WINDOW_END
        slug = f"{window_start}_{window_end}"

    print(f"Window: {window_start} → {window_end}")
    print("Fetching Mailchimp campaigns…", flush=True)
    campaigns = fetch_campaigns(window_start, window_end)
    print(f"  {len(campaigns)} campaigns", flush=True)

    print("Fetching GA4 (sessionCampaignName × landingPage)…", flush=True)
    ga4_data = fetch_ga4_by_campaign_url(ga4.client(), window_start, window_end)
    print(f"  {len(ga4_data)} (campaign, url) pairs", flush=True)

    url_rows: list[dict] = []
    summary_rows: list[dict] = []

    for i, c in enumerate(campaigns, 1):
        cid = c["id"]
        title = c.get("settings", {}).get("title", "") or ""
        subject = c.get("settings", {}).get("subject_line", "") or ""
        send_dt = c.get("send_time", "") or ""
        send_date = send_dt[:10]
        recipients = c.get("recipients", {}).get("recipient_count", 0)
        rs = c.get("report_summary") or {}
        utm = (c.get("tracking") or {}).get("google_analytics", "") or ""

        try:
            urls = fetch_click_details(cid)
        except requests.HTTPError as e:
            print(f"  [{i}/{len(campaigns)}] {cid} click-details FAILED: {e}", flush=True)
            urls = []
        time.sleep(0.05)

        # Build a lookup of GA4 paths for this campaign's UTM tag (if any)
        ga4_for_campaign: dict[str, dict[str, int]] = {}
        if utm:
            for (camp, path), m in ga4_data.items():
                if camp == utm:
                    ga4_for_campaign[path] = m

        # Per-URL rows
        if urls:
            for u in urls:
                full_url = u.get("url", "")
                host, path = normalize_url_for_match(full_url)
                mc_clicks = u.get("total_clicks", 0)
                mc_unique = u.get("unique_clicks", 0)

                ga = ga4_for_campaign.get(path, {}) if utm else {}
                ga_sessions = ga.get("sessions", 0)
                ga_users = ga.get("users", 0)

                if not utm:
                    status = "untagged_mc_only"
                elif host and host not in PORTAL_HOSTS:
                    status = "external_url"
                elif ga_users == 0 and mc_unique > 0:
                    status = "mailchimp_only"
                elif ga_users > 0 and mc_unique == 0:
                    status = "ga4_only"
                else:
                    delta = ga_users - mc_unique
                    if mc_unique == 0:
                        ratio = None
                    else:
                        ratio = ga_users / mc_unique
                    if ratio is None:
                        status = "ga4_only"
                    elif ratio >= 1.10:
                        status = "ga4_higher"
                    elif ratio >= 0.85:
                        status = "match"
                    elif ratio >= 0.50:
                        status = "acceptable_loss"
                    else:
                        status = "mc_likely_inflated"

                url_rows.append({
                    "send_date": send_date,
                    "campaign_id": cid,
                    "title": title,
                    "subject": subject,
                    "utm_tag": utm,
                    "url": full_url,
                    "url_host": host,
                    "url_path": path,
                    "mc_total_clicks": mc_clicks,
                    "mc_unique_clickers": mc_unique,
                    "ga4_sessions": ga_sessions,
                    "ga4_users": ga_users,
                    "delta_ga4_minus_mc_unique": (ga_users - mc_unique) if utm else "",
                    "ga4_pct_of_mc_unique": (
                        round(100 * ga_users / mc_unique, 1) if (utm and mc_unique) else ""
                    ),
                    "status": status,
                })
        elif utm and ga4_for_campaign:
            # MC click-details empty (the Tatersall scenario) — output a synthetic row per GA4 path
            for path, ga in ga4_for_campaign.items():
                url_rows.append({
                    "send_date": send_date,
                    "campaign_id": cid,
                    "title": title,
                    "subject": subject,
                    "utm_tag": utm,
                    "url": f"(GA4 only) {path}",
                    "url_host": "",
                    "url_path": path,
                    "mc_total_clicks": "",
                    "mc_unique_clickers": "",
                    "ga4_sessions": ga.get("sessions", 0),
                    "ga4_users": ga.get("users", 0),
                    "delta_ga4_minus_mc_unique": "",
                    "ga4_pct_of_mc_unique": "",
                    "status": "ga4_only_mc_missing",
                })

        # Summary row per campaign
        ga4_total_users = sum(m["users"] for m in ga4_for_campaign.values()) if utm else 0
        ga4_total_sessions = sum(m["sessions"] for m in ga4_for_campaign.values()) if utm else 0
        ratio_str = ""
        if utm and rs.get("subscriber_clicks"):
            ratio_str = f"{round(100 * ga4_total_users / rs['subscriber_clicks'], 1)}%"
        summary_rows.append({
            "send_date": send_date,
            "campaign_id": cid,
            "title": title,
            "subject": subject,
            "recipients": recipients,
            "utm_tag": utm,
            "mc_unique_opens": rs.get("unique_opens", 0),
            "mc_open_rate_pct": round((rs.get("open_rate", 0) or 0) * 100, 2),
            "mc_unique_clickers": rs.get("subscriber_clicks", 0),
            "mc_click_rate_pct": round((rs.get("click_rate", 0) or 0) * 100, 2),
            "mc_url_count": len(urls),
            "ga4_sessions_total": ga4_total_sessions,
            "ga4_users_total": ga4_total_users,
            "ga4_pct_of_mc_clickers": ratio_str,
            "status": (
                "untagged" if not utm else
                "ga4_only_mc_missing" if (not urls and ga4_for_campaign) else
                "mc_only_ga4_missing" if (urls and not ga4_for_campaign) else
                "both" if (urls and ga4_for_campaign) else
                "no_clicks"
            ),
        })

        if i % 10 == 0:
            print(f"  processed {i}/{len(campaigns)}", flush=True)

    # Write CSVs
    url_rows.sort(key=lambda r: (r["send_date"], r["campaign_id"]), reverse=True)
    summary_rows.sort(key=lambda r: r["send_date"], reverse=True)

    url_csv = OUT / f"mc_ga4_url_merge_{slug}.csv"
    sum_csv = OUT / f"mc_ga4_summary_{slug}.csv"

    io.write_dicts(url_csv, url_rows)
    io.write_dicts(sum_csv, summary_rows)

    print()
    print(f"Wrote {len(url_rows)} URL rows → {url_csv}")
    print(f"Wrote {len(summary_rows)} campaign rows → {sum_csv}")
    print()
    print("Status counts (URL-level):")
    counts: dict[str, int] = defaultdict(int)
    for r in url_rows:
        counts[r["status"]] += 1
    for k, v in sorted(counts.items(), key=lambda kv: -kv[1]):
        print(f"  {k:<25} {v}")


if __name__ == "__main__":
    main()

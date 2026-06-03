"""Channel + section funnel for a quarterly review.

    python reports/ga4/funnel.py --period FY26

1. Channel summary (Email / Direct / Unassigned / Other)
2. Email-channel pageviews bucketed by site section
3. Top 15 email-channel pages

Writes CSVs to reports/out/ga4/ and copies them to Windows Downloads.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

# --- URL → section bucket ---------------------------------------------------
SECTION_RULES = [
    ("MCA",          r"^/courses/mini-clinical-audit|^/mini-clinical-audit|/retrospective|/complete-mini"),
    ("Sample order", r"^/order-samples|^/order-patient-demo|^/product-demo"),
    ("Resources",    r"^/resources|/factsheet|/patient-resource|/clinical-paper|/guide|wp-content/uploads.*\.pdf"),
    ("Blog/Article", r"^/blog|^/nasal-and-sinus-health|^/topics/"),
    ("Brands",       r"^/brands/"),
    ("Account/Auth", r"^/log-in|^/login|^/register|^/edit-your-profile|^/my-account|^/dashboard|^/profile|^/welcome|^/forgot-password"),
    ("Homepage",     r"^/$"),
    ("Tools",        r"^/allergy-analyser|^/interactive-model|^/rectogesic-3d-model|^/fess-demonstration|^/interactivetools|^/section\d|^/tip\d|^/option\d|^/bake$|^/clues$|^/chart$|^/detection$|^/mist$"),
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
    p = path.split("?")[0]
    return p.rstrip("/") or "/"


def channel_summary(c, p: periods.Period) -> list[dict]:
    resp = ga4.run_report(c, dims=["sessionDefaultChannelGroup"],
                          metrics=["totalUsers", "sessions", "screenPageViews"],
                          start=p.start, end=p.end, order_by=[ga4.metric_order("sessions")])
    buckets: dict[str, dict] = {}
    for r in resp.rows:
        ch = r.dimension_values[0].value
        users, sessions, pv = (int(v.value) for v in r.metric_values)
        b = ch if ch in ("Email", "Direct") else ("Unassigned" if ch in ("Unassigned", "(not set)") else "Other")
        buckets.setdefault(b, {"channel": b, "users": 0, "sessions": 0, "pageviews": 0})
        buckets[b]["users"] += users
        buckets[b]["sessions"] += sessions
        buckets[b]["pageviews"] += pv
    order = ["Email", "Direct", "Unassigned", "Other"]
    result = [buckets[b] for b in order if b in buckets]
    total_sess = sum(r["sessions"] for r in result)
    total_pv = sum(r["pageviews"] for r in result)
    total_users = sum(r["users"] for r in result)
    for r in result:
        r["sessions_pct"] = f"{r['sessions']/total_sess*100:.1f}%"
        r["pageviews_pct"] = f"{r['pageviews']/total_pv*100:.1f}%"
    result.append({"channel": "TOTAL", "users": total_users, "sessions": total_sess,
                   "pageviews": total_pv, "sessions_pct": "100%", "pageviews_pct": "100%"})
    return result


def email_by_section(c, p: periods.Period) -> list[dict]:
    resp = ga4.run_report(c, dims=["pagePath", "pageTitle"],
                          metrics=["screenPageViews", "totalUsers"], start=p.start, end=p.end,
                          dimension_filter=ga4.channel_filter("Email"),
                          order_by=[ga4.metric_order("screenPageViews")], limit=500)
    sections: dict[str, dict] = {}
    total_pv = 0
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        pv, usr = int(r.metric_values[0].value), int(r.metric_values[1].value)
        sec = bucket(path)
        total_pv += pv
        sections.setdefault(sec, {"section": sec, "pageviews": 0, "users": 0})
        sections[sec]["pageviews"] += pv
        sections[sec]["users"] += usr
    result = sorted(sections.values(), key=lambda x: x["pageviews"], reverse=True)
    for r in result:
        r["pv_pct"] = f"{r['pageviews']/total_pv*100:.1f}%" if total_pv else "0%"
    return result


def top_email_pages(c, p: periods.Period) -> list[dict]:
    resp = ga4.run_report(c, dims=["pagePath", "pageTitle"],
                          metrics=["screenPageViews", "totalUsers"], start=p.start, end=p.end,
                          dimension_filter=ga4.channel_filter("Email"),
                          order_by=[ga4.metric_order("screenPageViews")], limit=200)
    consolidated: dict[str, dict] = {}
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        pv, usr = int(r.metric_values[0].value), int(r.metric_values[1].value)
        consolidated.setdefault(path, {"path": path, "title": r.dimension_values[1].value,
                                        "section": bucket(path), "pageviews": 0, "users": 0})
        consolidated[path]["pageviews"] += pv
        consolidated[path]["users"] += usr
    return sorted(consolidated.values(), key=lambda x: x["pageviews"], reverse=True)[:15]


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    out = io.out_dir("ga4")
    c = ga4.client()
    print(f"GA4 funnel — {p.label}  ({p.start} .. {p.end})")

    io.copy_to_downloads(io.write_dicts(out / f"channel_summary_{p.slug}.csv", channel_summary(c, p)))
    io.copy_to_downloads(io.write_dicts(out / f"email_by_section_{p.slug}.csv", email_by_section(c, p)))
    io.copy_to_downloads(io.write_dicts(out / f"email_top_pages_{p.slug}.csv", top_email_pages(c, p)))


if __name__ == "__main__":
    main()

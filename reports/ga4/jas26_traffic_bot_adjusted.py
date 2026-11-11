"""Whole-site traffic with automated (scanner) arrivals removed, like for like across quarters.

    reports/.venv/bin/python reports/ga4/jas26_traffic_bot_adjusted.py

Arrivals = session_start events on the prod host, by minute x source x campaign x
landing page. The same rule as jas26_media_quality.py classifies an arrival as
automated only on behaviour: it is one of 10+ arrivals from the same source and
campaign in the same minute (burst), or it never loaded a page (no page).
Location is never a test: a human anywhere in the world counts as human, and a
session inside a burst minute that behaves like a person (phone/tablet, 3s+
engaged, 3+ pages, or a human-only event such as a pop-up dismissed or a PDF
downloaded) is counted as human, not burst. Human sessions are split into proven
(same signals) and unprovable (counted as human anyway).
Shares are applied to the headline GA4 totals; automated sessions carry ~0
engaged time, so engaged time is kept whole and divided by real users only.
"""
from __future__ import annotations

import collections
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from google.analytics.data_v1beta.types import Filter, FilterExpression, FilterExpressionList

from lib import ga4, io

BURST_PER_MINUTE = 10
HUMAN_EVENTS = ("video_start", "video_complete", "file_download", "form_submit", "conversion_event_signup", "login",
                "popup_cta_click", "popup_dismissed", "quiz_start", "quiz_answer", "quiz_complete", "click")
PERIODS = {"JAS25": ("2025-07-01", "2025-09-30"), "AMJ26": ("2026-04-01", "2026-06-30"), "JAS26": ("2026-07-01", "2026-09-30")}
WEEKS = [date(2026, 7, 6) + timedelta(days=7 * i) for i in range(13)]  # Mon 6 Jul .. Mon 28 Sep


def _eq(field: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, string_filter=Filter.StringFilter(value=value)))


PROD = _eq("hostName", "hcp.carepharma.com.au")


def events(c, start: str, end: str, event: str) -> list[tuple]:
    flt = FilterExpression(and_group=FilterExpressionList(expressions=[PROD, _eq("eventName", event)]))
    r = ga4.run_report(c, dims=["dateHourMinute", "sessionSource", "sessionCampaignName", "landingPage"],
                       metrics=["eventCount", "totalUsers"], start=start, end=end, dimension_filter=flt, limit=250000)
    return [tuple(d.value for d in x.dimension_values) + tuple(float(m.value) for m in x.metric_values) for x in r.rows]


def signal_rows(c, start: str, end: str) -> tuple[list[tuple], dict]:
    """Session rows by minute x source x campaign x device with engagement, plus human-only event counts per minute/source/campaign."""
    r = ga4.run_report(c, dims=["dateHourMinute", "sessionSource", "sessionCampaignName", "deviceCategory"],
                       metrics=["sessions", "userEngagementDuration", "screenPageViews"], start=start, end=end, dimension_filter=PROD, limit=250000)
    rows = [tuple(d.value for d in x.dimension_values) + tuple(float(m.value) for m in x.metric_values) for x in r.rows]
    flt = FilterExpression(and_group=FilterExpressionList(expressions=[PROD, FilterExpression(
        filter=Filter(field_name="eventName", in_list_filter=Filter.InListFilter(values=list(HUMAN_EVENTS))))]))
    r = ga4.run_report(c, dims=["dateHourMinute", "sessionSource", "sessionCampaignName"], metrics=["eventCount"], start=start, end=end, dimension_filter=flt, limit=250000)
    events = {tuple(d.value for d in x.dimension_values): int(x.metric_values[0].value) for x in r.rows}
    return rows, events


def proven_shares(rows: list[tuple], events: dict, per_minute: collections.Counter, threshold: int) -> tuple[float, float]:
    """(share of burst-minute sessions that show a person, share of non-burst sessions that show a person)."""
    tot = collections.Counter(); prov = collections.Counter()
    by_key = collections.defaultdict(lambda: [0.0, 0.0])
    for minute, src, camp, dev, sess, secs, views in rows:
        k = (minute, src, camp)
        by_key[k][0] += sess
        if dev in ("mobile", "tablet") or secs / sess >= 3 or views / sess >= 3:
            by_key[k][1] += sess
    for k, (sess, p) in by_key.items():
        p = min(sess, p + events.get(k, 0))
        band = "burst" if per_minute[k] >= threshold else "rest"
        tot[band] += sess; prov[band] += p
    return (prov["burst"] / tot["burst"] if tot["burst"] else 0.0, prov["rest"] / tot["rest"] if tot["rest"] else 0.0)


def classify(arrivals: list[tuple], views: list[tuple], shares_fn, threshold: int = BURST_PER_MINUTE) -> dict[str, collections.Counter]:
    """Burst minutes come from arrivals; page views in the same minute/source/campaign are classed with them."""
    per_minute = collections.Counter()
    for minute, src, camp, _, n, _ in arrivals:
        per_minute[(minute, src, camp)] += n

    def kind(minute, src, camp, landing):
        if per_minute[(minute, src, camp)] >= threshold:
            return "burst"
        return "no_page" if landing == "(not set)" else "human"

    out = {k: collections.Counter() for k in ("burst", "no_page", "human")}
    for minute, src, camp, landing, n, users in arrivals:
        out[kind(minute, src, camp, landing)]["arrivals"] += n
        out[kind(minute, src, camp, landing)]["users"] += users
    for minute, src, camp, landing, n, _ in views:
        out[kind(minute, src, camp, landing)]["views"] += n
    back, rest = shares_fn(per_minute, threshold)
    moved = {key: out["burst"][key] * back for key in ("arrivals", "users", "views")}
    for key, m in moved.items():
        out["burst"][key] -= m
        out["human"][key] += m
    out["burst"]["human_share_returned"] = back
    out["human"]["proven_share"] = ((moved["arrivals"] + (out["human"]["arrivals"] - moved["arrivals"]) * rest) / out["human"]["arrivals"]) if out["human"]["arrivals"] else 0
    return out


def headline(c, start: str, end: str) -> dict[str, float]:
    r = ga4.run_report(c, dims=[], metrics=["activeUsers", "sessions", "screenPageViews", "userEngagementDuration"],
                       start=start, end=end, dimension_filter=PROD)
    return dict(zip(("users", "sessions", "views", "engaged_secs"), (float(m.value) for m in r.rows[0].metric_values)))


def adjusted(c, label: str, start: str, end: str, threshold: int = BURST_PER_MINUTE, _cache: dict = {}) -> dict:
    key = (start, end)
    if key not in _cache:
        _cache[key] = (events(c, start, end, "session_start"), events(c, start, end, "page_view"), signal_rows(c, start, end), headline(c, start, end))
    arr, views, (srows, evs), h = _cache[key]
    k = classify(arr, views, lambda per_minute, t: proven_shares(srows, evs, per_minute, t), threshold)
    tot = sum(v["arrivals"] for v in k.values())
    tot_users = sum(v["users"] for v in k.values())
    tot_views = sum(v["views"] for v in k.values())
    auto_share = 1 - k["human"]["arrivals"] / tot
    auto_user_share = 1 - k["human"]["users"] / tot_users
    auto_view_share = 1 - k["human"]["views"] / tot_views
    real_users = h["users"] * (1 - auto_user_share)
    real_sessions = h["sessions"] * (1 - auto_share)
    return {
        "period": label, "start": start, "end": end, "burst_threshold": threshold,
        "users": round(h["users"]), "sessions": round(h["sessions"]), "page_views": round(h["views"]),
        "engaged_time_per_user": round(h["engaged_secs"] / h["users"]),
        "automated_share_of_sessions": f"{auto_share * 100:.0f}%",
        "burst": round(h["sessions"] * k["burst"]["arrivals"] / tot), "burst_human_share_returned": f"{k['burst']['human_share_returned'] * 100:.0f}%",
        "no_page": round(h["sessions"] * k["no_page"]["arrivals"] / tot),
        "real_users": round(real_users), "real_sessions": round(real_sessions),
        "proven_human_sessions": round(real_sessions * k["human"]["proven_share"]), "unprovable_sessions": round(real_sessions * (1 - k["human"]["proven_share"])),
        "real_page_views": round(h["views"] * (1 - auto_view_share)),
        "real_engaged_time_per_user": round(h["engaged_secs"] / real_users),
    }


def main() -> None:
    c = ga4.client()
    rows = [adjusted(c, label, a, b) for label, (a, b) in PERIODS.items()]
    weeks = [adjusted(c, f"week of {d.strftime('%-d %b')}", d.isoformat(), (d + timedelta(days=6)).isoformat()) for d in WEEKS]
    outdir = io.out_dir("JAS26")
    io.write_dicts(outdir / "jas26_traffic_bot_adjusted.csv", rows)
    io.write_dicts(outdir / "jas26_weekly_bot_adjusted.csv", weeks)
    sens = [adjusted(c, label, a, b, t) for t in (5, 10, 20) for label, (a, b) in PERIODS.items()]
    io.write_dicts(outdir / "jas26_traffic_bot_threshold_sensitivity.csv", sens)
    for r in rows + weeks:
        print(r)


if __name__ == "__main__":
    main()

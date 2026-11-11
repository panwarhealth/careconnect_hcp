"""JAS26 media quality: automated (non-human) eDM clicks per publisher, publisher value, asset-type engagement.

    reports/.venv/bin/python reports/ga4/jas26_media_quality.py [iqvia_gp_clicks.json iqvia_oph_clicks.json]

Automated arrivals are classified from GA4 session_start events per send
(one row per minute x country x city x landing page):
  - burst:    10+ arrivals from the same send in the same minute (email-security
              link checking on delivery; no reader population clicks that fast)
  - no page:  session started without a page view ((not set) landing page)
Location is never a test: a human anywhere counts as human, and a session inside a
burst minute that behaves like a person (phone/tablet, 3s+ engaged, 3+ pages) is
counted as human, not burst.
Location is never a test: a human anywhere counts as human, and a session inside a
burst minute that behaves like a person (phone/tablet, 3s+ engaged, 3+ pages) is
counted as human, not burst.
Shares are applied to the GA4 sessions metric, because scanners can fire
session_start several times inside one GA4 session.

The optional IQVIA click logs (Graph workbook dumps of the publisher's raw click
export) are audited on the publisher side: a subscriber whose clicks include the
unsubscribe link or 2+ different links within 10 seconds is an automated clicker.
"""
from __future__ import annotations

import collections
import csv
import json
import sys
from datetime import datetime, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from google.analytics.data_v1beta.types import Filter, FilterExpression, FilterExpressionList

from jas26_edm_reconciliation import Q_END, Q_START, SENDS
from lib import ga4, io

BURST_PER_MINUTE = 10
HUMAN_EVENTS = ("video_start", "video_complete", "file_download", "form_submit", "conversion_event_signup", "login",
                "popup_cta_click", "popup_dismissed", "quiz_start", "quiz_answer", "quiz_complete", "click")

# Media cost ex GST and ex planning fee, from the FY27 reconciliation / publisher reports.
MEDIA_COST = {
    "TMR Solus eDM - Clinical Audit": 13000,
    "MT Solus eDM - Hydralyte Clinical Bites": 9810,
    "TMR Solus eDM - Hydralyte Clinical Bites": 12000,
    "mivision newsletter banners - Zaditen": 9900,
    "AJP Solus eDM - FESS": None,
    "IQVIA Solus eDM - Zaditen (GP + ophthal)": 8417,
    "Insight Solus eDM - Zaditen": 4829,
    "TMR Solus eDM - FESS Clinical Bites": 12000,
    "MT Solus eDM - FESS Clinical Bites": 9890,
}
PUBLISHER = {"TMR": "The Medical Republic", "MT ": "Medicine Today", "mivision": "mivision", "AJP": "AJP",
             "IQVIA": "IQVIA", "Insight": "Insight"}
# Delivered + unique opens as reported (IQVIA final report, both lists incl. reminder).
OPENS = {
    "TMR Solus eDM - Clinical Audit": (15697, 5856),
    "MT Solus eDM - Hydralyte Clinical Bites": (15831, 7727),
    "TMR Solus eDM - Hydralyte Clinical Bites": (15731, 6870),
    "mivision newsletter banners - Zaditen": (14900, None),
    "AJP Solus eDM - FESS": (28137, 10769),
    "IQVIA Solus eDM - Zaditen (GP + ophthal)": (27969, 9597),
    "Insight Solus eDM - Zaditen": (7794, None),
    "TMR Solus eDM - FESS Clinical Bites": (15748, None),
    "MT Solus eDM - FESS Clinical Bites": (15711, 7299),
}
# How each publisher's clicks reach the site: tracker evidence from the reports / eDM source.
CLICK_PATH = {
    "IQVIA": "Salesforce Marketing Cloud redirect (click.mkthealthcare.com); raw click log supplied",
    "Medicine Today": "Mailchimp redirect (report is a Mailchimp export); reports unique clickers",
    "AJP": "Mailchimp redirect (Mailchimp auto-tags the UTM campaign)",
    "mivision": "Campaign Monitor redirect (createsend1.com links in the web version)",
    "Insight": "Own redirect page (transport.primecreativemedia.com.au seen as referrer)",
    "The Medical Republic": "Own click map export; tracker not identified",
}

ASSETS = [
    # (type, label, page paths, indicative production cost, cost note)
    ("Articles", "Spot the allergy eye (Zaditen)", ["/blog/can-you-spot-the-allergy-eye/"], 1800, "FY27 article rate"),
    ("Articles", "GI tolerability on anti-obesity meds", ["/blog/how-gps-can-support-gi-tolerability-for-patients-taking-anti-obesity-medications/"], 1800, "FY27 article rate"),
    ("Articles", "Sip to stand: hydration in POTS", ["/blog/sip-to-stand-why-hydration-is-essential-in-pots/"], 1800, "FY27 article rate"),
    ("Articles", "Paediatric URTIs (FESS)", ["/blog/rethink-your-approach-to-paediatric-urtis/"], 1800, "CAPH0111 actual"),
    ("Interactives", "Guess the Glucose quiz", ["/blog/guess-the-glucose-challenge/"], 3600, "CAPH0125 quote"),
    ("Interactives", "Allergy Analyser", ["/allergy-analyser/", "/allergy-analyser"], None, "built before FY27"),
    ("Videos", "Hydralyte Clinical Bites (5 eps)",
     ["/clinical-bites/", "/clinical-bites", "/video/why-sick-day-planning-is-important-in-diabetes/",
      "/video/what-goes-in-a-sick-day-plan-for-people-with-diabetes/", "/video/medication-management-during-sick-days/",
      "/video/dehydration-during-sick-days-the-role-of-ors/", "/video/preparing-a-sick-day-management-kit/"], 11840, "FY27 budget"),
    ("Videos", "FESS Clinical Bites (3 eps)",
     ["/allergic-rhinitis-clinical-bites/", "/allergic-rhinitis-clinical-bites", "/video/not-another-sinus-infection/",
      "/video/are-steroids-safe-for-my-child/", "/video/expecting-and-congested/"], 9269, "CAPH0123 actual"),
    ("Datasheets", "AC Treatments Chart (Zaditen)", ["/allergic-conjunctivitis-chart/"], None, "print + trade insert job"),
    ("Datasheets", "AR Treatments Chart", ["/allergic-rhinitis-chart/"], None, "built before FY27"),
    ("Datasheets", "FESS patient leaflet", ["/fess-patient-leaflet/"], 8838, "CAPH0109 actual (print + digital)"),
    ("Datasheets", "Hydralyte sick-day plan", ["/hydralyte-sick-days/"], None, "built before FY27"),
    ("Datasheets", "Hydralyte patient leaflet", ["/hydralyte-patient-leaflet/"], None, "built before FY27"),
]


def _eq(field: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, string_filter=Filter.StringFilter(value=value)))


def _and(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


def publisher_of(label: str) -> str:
    return next(v for k, v in PUBLISHER.items() if label.startswith(k))


def human_signals(c, flt, start: str) -> dict[str, dict]:
    """Per minute: sessions, and how many of them show a person (phone/tablet, 3s+ engaged, 3+ pages, or a human-only event)."""
    r = ga4.run_report(c, dims=["dateHourMinute", "eventName"], metrics=["eventCount"], start=start, end=Q_END, dimension_filter=flt, limit=100000)
    events = collections.Counter()
    for x in r.rows:
        if x.dimension_values[1].value in HUMAN_EVENTS:
            events[x.dimension_values[0].value] += int(x.metric_values[0].value)
    r = ga4.run_report(c, dims=["dateHourMinute", "deviceCategory", "landingPage", "city", "browser"],
                       metrics=["sessions", "userEngagementDuration", "screenPageViews"], start=start, end=Q_END, dimension_filter=flt, limit=100000)
    out = collections.defaultdict(lambda: {"sessions": 0.0, "proven": 0.0})
    rows = [(x.dimension_values[0].value, x.dimension_values[1].value, *[float(m.value) for m in x.metric_values]) for x in r.rows]
    for minute, dev, sess, secs, views in rows:
        out[minute]["sessions"] += sess
        if dev in ("mobile", "tablet") or secs / sess >= 3 or views / sess >= 3:
            out[minute]["proven"] += sess
    for minute, d in out.items():
        d["proven"] = min(d["sessions"], d["proven"] + events.get(minute, 0))
    return out


def classify_send(c, send_date: str, campaign: str, source: str) -> dict:
    base = _and(_eq("sessionCampaignName", campaign), _eq("sessionSource", source))
    r = ga4.run_report(c, dims=["dateHourMinute", "landingPage"], metrics=["eventCount"],
                       start=send_date, end=Q_END, dimension_filter=_and(base, _eq("eventName", "session_start")), limit=100000)
    rows = [(x.dimension_values[0].value, x.dimension_values[1].value, int(x.metric_values[0].value)) for x in r.rows]
    per_minute = collections.Counter()
    for minute, _, n in rows:
        per_minute[minute] += n
    burst_minutes = {m for m, n in per_minute.items() if n >= BURST_PER_MINUTE}
    out = collections.Counter()
    for minute, landing, n in rows:
        if minute in burst_minutes:
            out["burst"] += n
        elif landing == "(not set)":
            out["no_page"] += n
        else:
            out["human"] += n
    sig = human_signals(c, base, send_date)
    b_tot = sum(d["sessions"] for m, d in sig.items() if m in burst_minutes)
    b_prov = sum(d["proven"] for m, d in sig.items() if m in burst_minutes)
    n_tot = sum(d["sessions"] for m, d in sig.items() if m not in burst_minutes)
    n_prov = sum(d["proven"] for m, d in sig.items() if m not in burst_minutes)
    back = out["burst"] * (b_prov / b_tot if b_tot else 0)
    out["burst"] -= back
    out["human"] += back
    out["burst_humans_returned"] = round(back)
    # of the human arrivals, how many are proven vs unprovable (shares measured on sessions in the same minutes)
    out["proven_share_of_human"] = ((back + (out["human"] - back) * (n_prov / n_tot if n_tot else 0)) / out["human"]) if out["human"] else 0
    out["arrivals"] = sum(n for *_, n in rows)
    r = ga4.run_report(c, dims=[], metrics=["sessions"], start=send_date, end=Q_END, dimension_filter=base)
    out["sessions"] = int(r.rows[0].metric_values[0].value) if r.rows else 0
    return out


def _iqvia_time(serial: float) -> datetime:
    """IQVIA's export stores dd/mm dates misread as mm/dd, in US Central time; return Sydney time."""
    t = datetime(1899, 12, 30) + timedelta(days=serial)
    if t.month != 9 and t.day <= 12:
        t = t.replace(month=t.day, day=t.month)
    return t + timedelta(hours=16)


def iqvia_audit(paths: list[str]) -> list[dict]:
    """Classify every IQVIA clicker on click behaviour alone (publisher-side, no GA4). Exact duplicate rows are dropped."""
    rows = []
    for p in paths:
        raw = json.load(open(p))["Raw data"]
        recs, seen = [], set()
        for r in raw[1:]:
            if not r or not r[0]:
                continue
            rec = dict(zip(raw[0], r))
            key = (rec["SubscriberKey"], rec["URL"], round(float(rec["EventDate"]) * 86400))
            if key in seen:
                continue
            seen.add(key)
            rec["t"] = _iqvia_time(float(rec["EventDate"]))
            recs.append(rec)
        by_sub = collections.defaultdict(list)
        for rec in recs:
            by_sub[rec["SubscriberKey"]].append(rec)
        cats = collections.Counter()
        for clicks in by_sub.values():
            clicks.sort(key=lambda r: r["t"])
            urls = {r["URL"].split("?")[0] for r in clicks}
            same_gaps = [(b["t"] - a["t"]).total_seconds() for a, b in zip(clicks, clicks[1:]) if a["URL"] == b["URL"]]
            diff_fast = any((b["t"] - a["t"]).total_seconds() <= 10 for a, b in zip(clicks, clicks[1:]) if a["URL"] != b["URL"])
            if any("unsub" in u for u in urls):
                k = "unsubscribe link fetched"
            elif len(urls) >= 4:
                k = "4+ different links"
            elif len(urls) >= 2 and diff_fast:
                k = "2-3 links within 10s"
            elif any(20 <= g <= 90 for g in same_gaps):
                k = "same link re-fetched 20-90s later"
            elif len(clicks) >= 5:
                k = "same link 5+ times"
            elif len(clicks) == 1:
                k = "one click, one link"
            else:
                k = "2-4 clicks, same link, other spacing"
            cats[k] += 1
        software = sum(v for k, v in cats.items() if k not in ("one click, one link", "2-4 clicks, same link, other spacing"))
        rows.append({"list": recs[0]["Delivery Label"].split(" - ")[1], "unique_clickers": len(by_sub),
                     **{"clickers_" + k.replace(" ", "_").replace(",", "").replace("-", "_").replace("+", "plus"): v for k, v in cats.items()},
                     "software_behaviour": software, "human_or_unprovable": len(by_sub) - software,
                     "software_share": f"{software / len(by_sub) * 100:.0f}%"})
    return rows


def asset_rows(c) -> list[dict]:
    out = []
    for kind, label, paths, cost, note in ASSETS:
        flt = FilterExpression(filter=Filter(field_name="pagePath", in_list_filter=Filter.InListFilter(values=paths)))
        r = ga4.run_report(c, dims=[], metrics=["totalUsers", "userEngagementDuration", "screenPageViews"],
                           start=Q_START, end=Q_END, dimension_filter=flt)
        users, secs, views = (int(float(m.value)) for m in r.rows[0].metric_values) if r.rows else (0, 0, 0)
        out.append({"type": kind, "asset": label, "unique_users": users, "page_views": views,
                    "avg_engaged_secs_per_user": round(secs / users) if users else 0,
                    "production_cost": cost or "", "cost_note": note,
                    "cost_per_unique_user": round(cost / users) if cost and users else ""})
    return out


def main() -> None:
    c = ga4.client()
    with open(io.out_dir("JAS26") / "jas26_edm_reconciliation_by_send.csv", newline="") as f:
        recon = {r["send"]: r for r in csv.DictReader(f)}
    sends = []
    for send_date, _, label, campaign, source, total_clicks, unique_clicks in SENDS:
        k = classify_send(c, send_date, campaign, source)
        share = {x: k[x] / k["arrivals"] if k["arrivals"] else 0 for x in ("burst", "no_page", "human")}
        delivered, uopens = OPENS[label]
        sends.append({
            "send_date": send_date, "publisher": publisher_of(label), "send": label,
            "publisher_total_clicks": total_clicks or "", "publisher_unique_clicks": unique_clicks or "",
            "ga4_sessions": k["sessions"], "ga4_arrivals": k["arrivals"],
            "automated_burst": round(k["sessions"] * share["burst"]), "burst_humans_returned": k.get("burst_humans_returned", 0),
            "proven_human_sessions": round(k["sessions"] * share["human"] * k["proven_share_of_human"]),
            "unprovable_sessions": round(k["sessions"] * share["human"] * (1 - k["proven_share_of_human"])),
            "automated_no_page": round(k["sessions"] * share["no_page"]), "human_sessions": round(k["sessions"] * share["human"]),
            "automated_share": f"{(1 - share['human']) * 100:.0f}%",
            "delivered": delivered, "unique_opens": uopens or "",
            "media_cost": MEDIA_COST[label] or "", "ga4_tagged_signups": recon[label]["ga4_tagged_signups"],
        })
    pubs = {}
    for s in sends:
        p = pubs.setdefault(s["publisher"], collections.Counter())
        for k in ("ga4_sessions", "automated_burst", "automated_no_page", "human_sessions", "delivered", "proven_human_sessions", "unprovable_sessions"):
            p[k] += s[k]
        p["sends"] += 1
        p["publisher_clicks"] += int(s["publisher_unique_clicks"] or s["publisher_total_clicks"] or 0)
        p["unique_opens"] += int(s["unique_opens"] or 0)
        p["media_cost"] += int(s["media_cost"] or 0)
        p["cost_known"] += 1 if s["media_cost"] else 0
        p["tagged_signups"] += int(s["ga4_tagged_signups"])
    pub_rows = []
    for name, p in pubs.items():
        auto = p["ga4_sessions"] - p["human_sessions"]
        pub_rows.append({
            "publisher": name, "sends": p["sends"], "delivered": p["delivered"], "publisher_clicks": p["publisher_clicks"],
            "ga4_sessions": p["ga4_sessions"], "human_sessions": p["human_sessions"], "automated_sessions": auto,
            "automated_share": f"{auto / p['ga4_sessions'] * 100:.0f}%" if p["ga4_sessions"] else "",
            "burst": p["automated_burst"], "no_page": p["automated_no_page"],
            "proven_human_sessions": p["proven_human_sessions"], "unprovable_sessions": p["unprovable_sessions"],
            "human_per_1000_delivered": round(p["human_sessions"] / p["delivered"] * 1000, 1),
            "human_vs_publisher_clicks": f"{p['human_sessions'] / p['publisher_clicks'] * 100:.0f}%" if p["publisher_clicks"] else "",
            "media_cost": p["media_cost"] if p["cost_known"] == p["sends"] else "",
            "cost_per_human_session": round(p["media_cost"] / p["human_sessions"]) if p["cost_known"] == p["sends"] and p["human_sessions"] else "",
            "ga4_tagged_signups": p["tagged_signups"], "click_path": CLICK_PATH[name],
        })
    outdir = io.out_dir("JAS26")
    io.write_dicts(outdir / "jas26_bot_by_send.csv", sends)
    io.write_dicts(outdir / "jas26_bot_by_publisher.csv", pub_rows)
    io.write_dicts(outdir / "jas26_asset_types.csv", asset_rows(c))
    if len(sys.argv) > 1:
        io.write_dicts(outdir / "jas26_iqvia_click_audit.csv", iqvia_audit(sys.argv[1:]))
    for r in pub_rows:
        print(r)


if __name__ == "__main__":
    main()

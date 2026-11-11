"""JAS26 eDM reconciliation — publisher clicks vs GA4 capture vs sign-ups.

    reports/.venv/bin/python reports/ga4/jas26_edm_reconciliation.py

GA4 sessions per send are isolated by campaign + source tag (send day to
quarter end). Sign-ups are estimated per campaign GROUP, not per send: sends
from mid-Aug overlap so tightly that per-send windows double-count, and the
AMJ26 rolling 28-day baseline has no clean days left. Instead each group's
14-day window (from its first send) is measured against one quiet-period
baseline (6 Jul - 16 Aug, no sends). Group estimate = max(GA4-tagged sign-ups,
DB spike).
Publisher figures are transcribed from the publisher reports on the Trello
card; blanks mean the publisher didn't report it.
"""
from __future__ import annotations

import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from google.analytics.data_v1beta.types import Filter, FilterExpression, FilterExpressionList

from lib import ga4, io, prod_db

Q_START, Q_END = "2026-07-01", "2026-09-30"
WINDOW_DAYS = 14
QUIET_START, QUIET_END = date(2026, 7, 6), date(2026, 8, 16)

# (send date, group, label, utm_campaign, utm_source, publisher total clicks, publisher unique clicks)
SENDS = [
    ("2026-07-01", "Rectogesic Clinical Audit", "TMR Solus eDM - Clinical Audit", "CAPH0062", "MedicalRepublic", 509, 367),
    ("2026-08-20", "Hydralyte Clinical Bites", "MT Solus eDM - Hydralyte Clinical Bites", "CAPH0126", "MedicineToday", 912, 210),
    ("2026-08-25", "Hydralyte Clinical Bites", "TMR Solus eDM - Hydralyte Clinical Bites", "CAPH0126", "TheMedicalRepublic", 883, 601),
    ("2026-08-31", "Zaditen", "mivision newsletter banners - Zaditen", "caph0152", "mivision", 619, None),
    ("2026-09-02", "FESS (AJP)", "AJP Solus eDM - FESS", "9445dc6c42-EMAIL_CAMPAIGN_2026_08_25_07_42", "AJP Sponsored", 325, 290),
    ("2026-09-08", "Zaditen", "IQVIA Solus eDM - Zaditen (GP + ophthal)", "caph0132-zaditen", "iqvia", None, 1140),
    ("2026-09-08", "Zaditen", "Insight Solus eDM - Zaditen", "caph0152-zaditen", "insight", 533, None),
    ("2026-09-16", "FESS Clinical Bites", "TMR Solus eDM - FESS Clinical Bites", "CAPH0122", "TheMedicalRepublic", 656, 529),
    ("2026-09-17", "FESS Clinical Bites", "MT Solus eDM - FESS Clinical Bites", "CAPH0122", "MedicineToday", 517, 156),
]

EXCLUDE = "user_email NOT REGEXP '@(carepharma|tbstdigital|panwarhealth|rnafiel|rscottdigital)\\\\.com\\\\.au$'"


def _eq(field: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, string_filter=Filter.StringFilter(value=value)))


def ga4_send(c, campaign: str, source: str, start: str) -> tuple[int, int, int]:
    """(sessions, users, tagged sign-up events) for one send, send day to quarter end."""
    flt = FilterExpression(and_group=FilterExpressionList(expressions=[
        _eq("sessionCampaignName", campaign), _eq("sessionSource", source)]))
    r = ga4.run_report(c, dims=[], metrics=["sessions", "totalUsers"], start=start, end=Q_END, dimension_filter=flt)
    sess, users = (int(r.rows[0].metric_values[0].value), int(r.rows[0].metric_values[1].value)) if r.rows else (0, 0)
    flt_su = FilterExpression(and_group=FilterExpressionList(expressions=[
        _eq("sessionCampaignName", campaign), _eq("sessionSource", source), _eq("eventName", "conversion_event_signup")]))
    r = ga4.run_report(c, dims=[], metrics=["totalUsers"], start=start, end=Q_END, dimension_filter=flt_su)
    tagged = int(r.rows[0].metric_values[0].value) if r.rows else 0
    return sess, users, tagged


def db_daily() -> dict[date, int]:
    rows = prod_db.query(f"""
        SELECT DATE(CONVERT_TZ(user_registered, '+00:00', '+10:00')) d, COUNT(*) n
        FROM tbstwp_users WHERE {EXCLUDE}
          AND user_registered >= '2026-05-01' AND user_registered < '2026-10-15'
        GROUP BY d;""")
    return {date.fromisoformat(r["d"]): int(r["n"]) for r in rows}


def main() -> None:
    c = ga4.client()
    daily = db_daily()
    quiet_days = (QUIET_END - QUIET_START).days + 1
    base_daily = sum(daily.get(QUIET_START + timedelta(days=i), 0) for i in range(quiet_days)) / quiet_days

    rows, by_group = [], {}
    for send_date, group, label, campaign, source, total_clicks, unique_clicks in SENDS:
        sess, users, tagged = ga4_send(c, campaign, source, send_date)
        g = by_group.setdefault(group, {"d0": date.fromisoformat(send_date), "tagged": 0, "unique_clicks": 0, "sessions": 0})
        g["tagged"] += tagged
        g["unique_clicks"] += unique_clicks or 0
        g["sessions"] += sess
        rows.append({
            "send_date": send_date, "group": group, "send": label, "utm": f"{campaign} / {source}",
            "publisher_total_clicks": total_clicks or "", "publisher_unique_clicks": unique_clicks or "",
            "ga4_sessions": sess, "ga4_users": users,
            "click_capture_pct": f"{sess / total_clicks * 100:.0f}%" if total_clicks else "",
            "ga4_tagged_signups": tagged,
        })

    group_rows = []
    for group, g in by_group.items():
        window = sum(daily.get(g["d0"] + timedelta(days=i), 0) for i in range(WINDOW_DAYS))
        spk = max(0.0, window - base_daily * WINDOW_DAYS)
        est = max(g["tagged"], round(spk))
        group_rows.append({
            "group": group, "window": f"{g['d0']} + 13 days", "ga4_sessions": g["sessions"],
            "db_signups_in_window": window, "quiet_baseline_14d": round(base_daily * WINDOW_DAYS, 1),
            "db_spike": round(spk, 1), "ga4_tagged_signups": g["tagged"], "est_signups": est,
            "conversion_rate_vs_unique_clicks": f"{est / g['unique_clicks'] * 100:.1f}%" if g["unique_clicks"] else "",
        })

    outdir = io.out_dir("JAS26")
    io.write_dicts(outdir / "jas26_edm_reconciliation_by_send.csv", rows)
    io.write_dicts(outdir / "jas26_edm_signups_by_campaign.csv", group_rows)
    print(f"quiet baseline {base_daily:.2f}/day")
    for r in rows:
        print(f"{r['send_date']}  {r['send'][:42]:42} sess {r['ga4_sessions']:>5}  tagged {r['ga4_tagged_signups']:>2}  capture {r['click_capture_pct']:>4}")
    for r in group_rows:
        print(r)


if __name__ == "__main__":
    main()

"""UTM QR code reporting: scans, engagement and sign-ups per client QR code.

One row per code in QR_CODES (matched on utm_source + utm_campaign, medium=qr),
split by quarter, plus a month view and every individual visit. Sign-ups are
GA4 Form_submissions on /register, cross-checked against prod registrations
in the same Sydney hour.

Usage:
    python reports/ga4/qr_codes.py                 # live date of each code -> yesterday
    python reports/ga4/qr_codes.py --end 2026-10-25
"""
from __future__ import annotations

import argparse
import sys
from collections import defaultdict
from datetime import date, datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo

from google.analytics.data_v1beta.types import Filter, FilterExpression, FilterExpressionList
from openpyxl import Workbook
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, prod_db  # noqa: E402
from lib.io import copy_to_downloads, out_dir  # noqa: E402

SYDNEY = ZoneInfo("Australia/Sydney")

QR_CODES = [
    {"qr": "QR 1", "name": "CAPH0075 Sick Day Man Plan Sharon CWH PPT Slide",
     "source": "cwh", "campaign": "caph0075-sick-day-man-plan", "content": "ppt-slide",
     "lands_on": "/hydralyte-sick-days/", "delivered": "2026-09-03"},
    {"qr": "QR 2", "name": "CAPH0075 Deb Diabetes Videos Sharon CWH PPT Slide",
     "source": "cwh", "campaign": "caph0075-deb-diabetes-videos", "content": "ppt-slide",
     "lands_on": "/clinical-bites/", "delivered": "2026-09-03"},
    {"qr": "QR 3", "name": "Sick Day Man Plan Sharon BLOOMS Beyond the Dispensary",
     "source": "blooms", "campaign": "sick-day-man-plan", "content": "beyond-the-dispensary",
     "lands_on": "/hydralyte-sick-days/", "delivered": "2026-09-03"},
    {"qr": "QR 4", "name": "Deb Diabetes Videos Sharon BLOOMS Beyond the Dispensary",
     "source": "blooms", "campaign": "deb-diabetes-videos", "content": "beyond-the-dispensary",
     "lands_on": "/clinical-bites/", "delivered": "2026-09-03"},
    {"qr": "QR 5", "name": "FY27 Sample Bags (Conference)",
     "source": "sample-bag", "campaign": "fy27-sample-bags-conference", "content": "",
     "lands_on": "/ (home page)", "delivered": "2026-05-04"},
]

# Care Pharma FY27 quarters (Apr-Mar FY), clipped to the report end date.
QUARTERS = [("AMJ26", "2026-04-01", "2026-06-30"),
            ("JAS26", "2026-07-01", "2026-09-30"),
            ("OND26", "2026-10-01", "2026-12-31")]

METRICS = ["totalUsers", "sessions", "engagedSessions", "userEngagementDuration", "screenPageViews"]
ACTION_EVENTS = {"video_start": "video play", "file_download": "PDF download", "login": "logged in",
                 "Form_submissions": "form submitted", "popup_cta_click": "pop-up CTA click"}

HEAD_FILL = PatternFill("solid", fgColor="1F4E79")
HEAD_FONT = Font(bold=True, color="FFFFFF")


def _f(field: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, string_filter=Filter.StringFilter(value=value)))


def _and(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


def code_filter(code: dict) -> FilterExpression:
    return _and(_f("sessionMedium", "qr"), _f("sessionSource", code["source"]),
                _f("sessionCampaignName", code["campaign"]))


def rows(c, dims, metrics, start, end, flt):
    resp = ga4.run_report(c, dims=dims, metrics=metrics, start=start, end=end, dimension_filter=flt)
    return [([v.value for v in r.dimension_values], [float(v.value) for v in r.metric_values]) for r in resp.rows]


def totals(c, code, start, end) -> dict:
    if start > end:
        return {}
    got = rows(c, [], METRICS, start, end, code_filter(code))
    return dict(zip(METRICS, got[0][1])) if got else {m: 0 for m in METRICS}


def signups(c, code, start, end) -> list[dict]:
    """Form_submissions on /register for this code, matched to prod registrations that hour."""
    flt = _and(code_filter(code), _f("eventName", "Form_submissions"),
               FilterExpression(filter=Filter(field_name="pagePath", string_filter=Filter.StringFilter(
                   value="/register", match_type=Filter.StringFilter.MatchType.BEGINS_WITH))))
    out = []
    for (date_hour,), (n,) in rows(c, ["dateHour"], ["eventCount"], start, end, flt):
        local = datetime.strptime(date_hour, "%Y%m%d%H").replace(tzinfo=SYDNEY)
        utc_from = local.astimezone(ZoneInfo("UTC")).strftime("%Y-%m-%d %H:%M:%S")
        utc_to = (local + timedelta(hours=1)).astimezone(ZoneInfo("UTC")).strftime("%Y-%m-%d %H:%M:%S")
        regs = prod_db.query(
            "SELECT LEFT(m.meta_value, 3) AS prof FROM tbstwp_users u "
            "LEFT JOIN tbstwp_usermeta m ON m.user_id = u.ID AND m.meta_key = 'ahpra_id' "
            f"WHERE u.user_registered >= '{utc_from}' AND u.user_registered < '{utc_to}'")
        out.append({"when": local, "registrations_that_hour": len(regs),
                    "professions": ", ".join(r["prof"] or "?" for r in regs)})
    return out


def visits(c, code, start, end) -> list[list]:
    """One row per session hour + device + landing page, with the actions taken."""
    flt = code_filter(code)
    base = rows(c, ["dateHour", "deviceCategory", "landingPage"], METRICS, start, end, flt)
    actions = defaultdict(set)
    for (dh, ev), _ in rows(c, ["dateHour", "eventName"], ["eventCount"], start, end, flt):
        if ev in ACTION_EVENTS:
            actions[dh].add(ACTION_EVENTS[ev])
    has_page = {(dh, device) for (dh, device, landing), _ in base if landing != "(not set)"}
    out = []
    for (dh, device, landing), m in sorted(base):
        if landing == "(not set)" and (dh, device) in has_page:
            continue  # GA4's page-less twin of a session already counted on its landing row
        when = datetime.strptime(dh, "%Y%m%d%H")
        note = "day the code was delivered" if when.date().isoformat() == code["delivered"] else ""
        out.append([code["qr"], when.strftime("%a %d %b %Y"), when.strftime("%H:00"), device, landing,
                    int(m[4]), round(m[3]), ", ".join(sorted(actions.pop(dh, []))), note])
    return out


def style_sheet(ws, widths):
    for cell in ws[1]:
        cell.fill, cell.font = HEAD_FILL, HEAD_FONT
        cell.alignment = Alignment(wrap_text=True, vertical="center")
    ws.freeze_panes = "A2"
    for i, w in enumerate(widths, 1):
        ws.column_dimensions[get_column_letter(i)].width = w
    for row in ws.iter_rows(min_row=2):
        for cell in row:
            cell.alignment = Alignment(wrap_text=True, vertical="top")


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--end", default=(date.today() - timedelta(days=1)).isoformat())
    end = ap.parse_args().end
    c = ga4.client()
    end_label = datetime.strptime(end, "%Y-%m-%d").strftime("%d %b %Y")
    quarters = [(q + (" (to date)" if e > end else ""), s, min(e, end)) for q, s, e in QUARTERS if s <= end]

    wb = Workbook()
    summary = wb.active
    summary.title = "Summary"
    summary.append(["QR code", "Name for reporting", "Lands on", "UTM tag (source / campaign / content)",
                    "Code delivered"]
                   + [f"Users {q}" for q, _, _ in quarters]
                   + [f"Total users to {end_label}", "Engaged visits", "Avg engaged time per user (sec)",
                      "Pages viewed", "Sign-ups", "Notes"])
    monthly = wb.create_sheet("By month")
    monthly.append(["QR code", "Name for reporting", "Month", "Users", "Visits", "Pages viewed"])
    visit_ws = wb.create_sheet("Every visit")
    visit_ws.append(["QR code", "Date", "Hour (Sydney)", "Device", "Landed on", "Pages viewed",
                     "Engaged time (sec)", "Actions", "Note"])

    for code in QR_CODES:
        start = QUARTERS[0][1]
        t = totals(c, code, start, end)
        per_q = [int(totals(c, code, s, e).get("totalUsers", 0)) for _, s, e in quarters]
        sign = signups(c, code, start, end)
        notes = []
        for s in sign:
            match = (f"matches 1 prod registration that hour ({s['professions']})"
                     if s["registrations_that_hour"] == 1
                     else f"{s['registrations_that_hour']} prod registrations that hour, can't pin to one")
            notes.append(f"Sign-up form submitted {s['when']:%d %b %H:00}: {match}")
        if not t.get("totalUsers"):
            notes.append("No scans recorded")
        users = int(t.get("totalUsers", 0))
        summary.append([code["qr"], code["name"], code["lands_on"],
                        " / ".join(p for p in (code["source"], code["campaign"], code["content"]) if p),
                        datetime.strptime(code["delivered"], "%Y-%m-%d").strftime("%d %b %Y")]
                       + per_q
                       + [users, int(t.get("engagedSessions", 0)),
                          round(t["userEngagementDuration"] / users) if users else 0,
                          int(t.get("screenPageViews", 0)),
                          sum(1 for s in sign if s["registrations_that_hour"] == 1), "; ".join(notes)])

        for (ym,), m in sorted(rows(c, ["yearMonth"], METRICS, start, end, code_filter(code))):
            monthly.append([code["qr"], code["name"], datetime.strptime(ym, "%Y%m").strftime("%b %Y"),
                            int(m[0]), int(m[1]), int(m[4])])
        for v in visits(c, code, start, end):
            visit_ws.append(v)

    n_q = len(quarters)
    style_sheet(summary, [8, 34, 20, 34, 12] + [11] * n_q + [12, 10, 12, 10, 9, 50])
    style_sheet(monthly, [8, 44, 10, 8, 8, 10])
    style_sheet(visit_ws, [8, 16, 10, 9, 22, 10, 12, 30, 24])

    about = wb.create_sheet("How to read this")
    for line in [
        f"Source: Google Analytics (GA4), visits arriving with utm_medium=qr, 1 Apr 2026 to {end_label}.",
        "Users = unique people (GA4 counts each phone or browser once). Visits = sessions.",
        "Engaged visit = stayed 10+ seconds, viewed 2+ pages or took an action (GA4 definition).",
        "Sign-ups: GA4 can't name who registered, so each sign-up form submission is matched to the "
        "prod registrations in the same hour. Only a one-to-one match is counted.",
        "No automated (bot) traffic found: no bursts of arrivals, and QR scans don't pass through "
        "email security scanners, so headline figures need no adjustment.",
        "Visits on the day a code was delivered are flagged: they may include the team checking the code.",
        "A scan that opens the page but leaves before it loads isn't recorded, so these are minimums.",
    ]:
        about.append([line])
    about.column_dimensions["A"].width = 120

    path = out_dir("QR") / f"CAPH0107 QR code reporting to {end}.xlsx"
    wb.save(path)
    print(f"wrote {path}")
    copy_to_downloads(path)


if __name__ == "__main__":
    main()

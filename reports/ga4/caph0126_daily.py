"""
CAPH0126 daily view pattern since the first Hydralyte eDM (20 Aug 2026).

Daily pageviews for the Tools & Videos hub, the Clinical Bites landing page and
the five episode pages, plus daily video starts/completes per episode.

A second eDM landed mid-window (25-26 Aug), so days from the 25th carry both
sends. This reports the pattern, it does not attribute it.

    reports/.venv/bin/python reports/ga4/caph0126_daily.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta.types import (
    Filter,
    FilterExpression,
    FilterExpressionList,
)

START = "2026-08-20"
END = "today"

# GA4 records both slashed and unslashed variants; keys here are canonical.
PAGES = [
    ("/tools-and-videos", "Tools & Videos hub"),
    ("/clinical-bites", "Clinical Bites landing"),
    ("/video/why-sick-day-planning-is-important-in-diabetes", "Ep 1 - Why Sick Day Planning is Important"),
    ("/video/what-goes-in-a-sick-day-plan-for-people-with-diabetes", "Ep 2 - What Goes in a Sick Day Plan"),
    ("/video/medication-management-during-sick-days", "Ep 3 - Medication Management"),
    ("/video/dehydration-during-sick-days-the-role-of-ors", "Ep 4 - Dehydration: The Role of ORS"),
    ("/video/preparing-a-sick-day-management-kit", "Ep 5 - Preparing a Sick Day Kit"),
]
LABEL = dict(PAGES)
ORDER = [p for p, _ in PAGES]

VIDEO_EVENTS = ["video_start", "video_complete"]

# The two sends share utm_campaign=CAPH0126 and are only separable by source.
SENDS = {"MedicineToday": "Medicine Today (20 Aug)", "TheMedicalRepublic": "Medical Republic (25 Aug)"}


def path_filter() -> FilterExpression:
    return FilterExpression(
        or_group=FilterExpressionList(
            expressions=[
                FilterExpression(
                    filter=Filter(
                        field_name="pagePath",
                        string_filter=Filter.StringFilter(value=p),
                    )
                )
                for p in ORDER
            ]
            + [
                FilterExpression(
                    filter=Filter(
                        field_name="pagePath",
                        string_filter=Filter.StringFilter(value=p + "/"),
                    )
                )
                for p in ORDER
            ]
        )
    )


def event_filter() -> FilterExpression:
    return FilterExpression(
        or_group=FilterExpressionList(
            expressions=[
                FilterExpression(
                    filter=Filter(
                        field_name="eventName",
                        string_filter=Filter.StringFilter(value=e),
                    )
                )
                for e in VIDEO_EVENTS
            ]
        )
    )


def fmt(d: str) -> str:
    return f"{d[:4]}-{d[4:6]}-{d[6:8]}"


def main() -> None:
    c = ga4.client()
    outdir = io.out_dir("ga4")

    # --- daily pageviews per page -------------------------------------
    resp = ga4.run_report(
        c,
        dims=["date", "pagePath"],
        metrics=["screenPageViews", "totalUsers"],
        start=START,
        end=END,
        dimension_filter=path_filter(),
    )
    views: dict[str, dict[str, int]] = {}
    users: dict[str, dict[str, int]] = {}
    dates: set[str] = set()
    for date, path, pv, tu in ga4.rows_of(resp):
        key = path.rstrip("/") or path
        d = fmt(date)
        dates.add(d)
        views.setdefault(key, {}).setdefault(d, 0)
        users.setdefault(key, {}).setdefault(d, 0)
        views[key][d] += int(pv)
        users[key][d] += int(tu)

    days = sorted(dates)
    rows = []
    for p in ORDER:
        r = [LABEL[p], p]
        r += [views.get(p, {}).get(d, 0) for d in days]
        r.append(sum(views.get(p, {}).values()))
        rows.append(r)
    total_row = ["TOTAL", ""]
    total_row += [sum(views.get(p, {}).get(d, 0) for p in ORDER) for d in days]
    total_row.append(sum(sum(views.get(p, {}).values()) for p in ORDER))
    rows.append(total_row)
    io.write_csv(
        outdir / "caph0126_daily_pageviews.csv",
        ["page", "path"] + days + ["total"],
        rows,
    )

    urows = []
    for p in ORDER:
        r = [LABEL[p], p]
        r += [users.get(p, {}).get(d, 0) for d in days]
        urows.append(r)
    io.write_csv(
        outdir / "caph0126_daily_users.csv", ["page", "path"] + days, urows
    )

    # --- daily video starts / completes --------------------------------
    vresp = ga4.run_report(
        c,
        dims=["date", "eventName", "videoTitle"],
        metrics=["eventCount", "totalUsers"],
        start=START,
        end=END,
        dimension_filter=event_filter(),
    )
    vid: dict[tuple[str, str], dict[str, int]] = {}
    vtitles: set[str] = set()
    for date, ev, title, cnt, tu in ga4.rows_of(vresp):
        d = fmt(date)
        vtitles.add(title)
        vid.setdefault((title, ev), {}).setdefault(d, 0)
        vid[(title, ev)][d] += int(cnt)

    vrows = []
    for title in sorted(vtitles):
        for ev in VIDEO_EVENTS:
            series = vid.get((title, ev), {})
            if not series:
                continue
            r = [title, ev]
            r += [series.get(d, 0) for d in days]
            r.append(sum(series.values()))
            vrows.append(r)
    io.write_csv(
        outdir / "caph0126_daily_video_events.csv",
        ["video", "event"] + days + ["total"],
        vrows,
    )

    # --- daily pageviews split by send ---------------------------------
    sresp = ga4.run_report(
        c,
        dims=["date", "pagePath", "sessionSource"],
        metrics=["screenPageViews"],
        start=START,
        end=END,
        dimension_filter=path_filter(),
    )
    split: dict[tuple[str, str], dict[str, int]] = {}
    for date, path, src, pv in ga4.rows_of(sresp):
        key = path.rstrip("/") or path
        label = SENDS.get(src, "Other / direct")
        d = fmt(date)
        split.setdefault((key, label), {}).setdefault(d, 0)
        split[(key, label)][d] += int(pv)

    srows = []
    for p in ORDER:
        for label in list(SENDS.values()) + ["Other / direct"]:
            series = split.get((p, label), {})
            if not series:
                continue
            r = [LABEL[p], label]
            r += [series.get(d, 0) for d in days]
            r.append(sum(series.values()))
            srows.append(r)
    io.write_csv(
        outdir / "caph0126_daily_pageviews_by_send.csv",
        ["page", "send"] + days + ["total"],
        srows,
    )

    # --- console view ---------------------------------------------------
    w = max(len(LABEL[p]) for p in ORDER) + 2
    print()
    print("DAILY PAGEVIEWS")
    print(f"{'page':<{w}}" + "".join(f"{d[5:]:>8}" for d in days) + f"{'total':>8}")
    for r in rows:
        print(f"{r[0]:<{w}}" + "".join(f"{v:>8}" for v in r[2:]))
    print()
    print("DAILY PAGEVIEWS BY SEND")
    for r in srows:
        print(f"{r[0]:<{w}}{r[1]:<28}" + "".join(f"{v:>7}" for v in r[2:]))
    print()
    print("DAILY VIDEO EVENTS")
    for r in vrows:
        print(f"{r[0][:46]:<48}{r[1]:<16}" + "".join(f"{v:>7}" for v in r[2:]))


if __name__ == "__main__":
    main()

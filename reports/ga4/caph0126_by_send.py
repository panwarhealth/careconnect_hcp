"""
CAPH0126 Hydralyte Diabetes Solus eDM — Medicine Today vs The Medical Republic.

The two sends share utm_campaign=CAPH0126 and are only separable by traffic
source, so every figure here is cut by sessionSource and confined to that
send's own first five days.

    reports/.venv/bin/python reports/ga4/caph0126_by_send.py
"""
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta.types import (
    Filter,
    FilterExpression,
    FilterExpressionList,
)

CAMPAIGN = "CAPH0126"

SENDS = [
    ("MedicineToday", "Medicine Today", "2026-08-20", "2026-08-24"),
    ("TheMedicalRepublic", "The Medical Republic", "2026-08-25", "2026-08-29"),
]

CTA_LABEL = {
    "watchnow-videos": "Watch Now (videos)",
    "download-sickdayplan": "Download Sick Day Plan",
    "quiz": "Take the Quiz",
    "takethequiz": "Take the Quiz",
    "download-patientleaflet": "Download Patient Leaflet",
    "ordersamples": "Order Samples",
    "order-samples": "Order Samples",
}


def campaign_filter(source=None):
    exprs = [
        FilterExpression(
            filter=Filter(
                field_name="sessionCampaignName",
                string_filter=Filter.StringFilter(value=CAMPAIGN),
            )
        )
    ]
    if source:
        exprs.append(
            FilterExpression(
                filter=Filter(
                    field_name="sessionSource",
                    string_filter=Filter.StringFilter(value=source),
                )
            )
        )
    return FilterExpression(and_group=FilterExpressionList(expressions=exprs))


def event_filter(names, source):
    ev = FilterExpression(
        or_group=FilterExpressionList(
            expressions=[
                FilterExpression(
                    filter=Filter(
                        field_name="eventName",
                        string_filter=Filter.StringFilter(value=n),
                    )
                )
                for n in names
            ]
        )
    )
    return FilterExpression(
        and_group=FilterExpressionList(expressions=[campaign_filter(source), ev])
    )


def totals(c, source, start, end):
    r = ga4.run_report(
        c,
        dims=[],
        metrics=[
            "sessions",
            "totalUsers",
            "newUsers",
            "engagedSessions",
            "screenPageViews",
            "userEngagementDuration",
        ],
        start=start,
        end=end,
        dimension_filter=campaign_filter(source),
    )
    rows = ga4.rows_of(r)
    vals = [int(v) for v in rows[0]] if rows else [0] * 6
    keys = ["sessions", "users", "new_users", "engaged", "pageviews", "engagement_sec"]
    return dict(zip(keys, vals))


def site_totals(c, start, end):
    r = ga4.run_report(
        c, dims=[], metrics=["sessions", "totalUsers"], start=start, end=end
    )
    rows = ga4.rows_of(r)
    return [int(v) for v in rows[0]] if rows else [0, 0]


def ctas(c, source, start, end):
    r = ga4.run_report(
        c,
        dims=["sessionManualAdContent"],
        metrics=["sessions", "totalUsers", "newUsers", "engagedSessions"],
        start=start,
        end=end,
        dimension_filter=campaign_filter(source),
        order_by=[ga4.metric_order("sessions")],
    )
    out = []
    for content, s, u, nu, es in ga4.rows_of(r):
        out.append(
            {
                "utm_content": content,
                "cta": CTA_LABEL.get(content, content),
                "sessions": int(s),
                "users": int(u),
                "new_users": int(nu),
                "engaged": int(es),
            }
        )
    return out


def videos(c, source, start, end):
    r = ga4.run_report(
        c,
        dims=["eventName", "videoTitle"],
        metrics=["eventCount", "totalUsers"],
        start=start,
        end=end,
        dimension_filter=event_filter(["video_start", "video_complete"], source),
    )
    data = defaultdict(dict)
    for ev, title, cnt, users in ga4.rows_of(r):
        data[title][ev] = (int(cnt), int(users))
    return data


def signups(c, source, start, end):
    r = ga4.run_report(
        c,
        dims=[],
        metrics=["eventCount"],
        start=start,
        end=end,
        dimension_filter=event_filter(["conversion_event_signup"], source),
    )
    rows = ga4.rows_of(r)
    return int(rows[0][0]) if rows else 0


def daily(c, source, start, end):
    r = ga4.run_report(
        c,
        dims=["date"],
        metrics=["sessions"],
        start=start,
        end=end,
        dimension_filter=campaign_filter(source),
        order_by=[ga4.dimension_order("date")],
    )
    return [(f"{d[:4]}-{d[4:6]}-{d[6:8]}", int(s)) for d, s in ga4.rows_of(r)]


def main():
    c = ga4.client()
    outdir = io.out_dir("ga4")

    trows, crows, vrows, drows = [], [], [], []
    for source, label, start, end in SENDS:
        t = totals(c, source, start, end)
        t["signups_ga4"] = signups(c, source, start, end)
        site_s, site_u = site_totals(c, start, end)
        t.update(send=label, source=source, start=start, end=end,
                 site_sessions=site_s, site_users=site_u)
        trows.append(t)

        for row in ctas(c, source, start, end):
            crows.append({"send": label, **row})

        for title, evs in sorted(videos(c, source, start, end).items()):
            starts, viewers = evs.get("video_start", (0, 0))
            comps = evs.get("video_complete", (0, 0))[0]
            vrows.append(
                {
                    "send": label,
                    "video": title,
                    "starts": starts,
                    "viewers": viewers,
                    "completions": comps,
                    "completion_rate": f"{round(comps / starts * 100)}%" if starts else "n/a",
                }
            )

        for d, s in daily(c, source, start, end):
            drows.append({"send": label, "date": d, "sessions": s})

    io.write_dicts(outdir / "caph0126_by_send_totals.csv", trows)
    io.write_dicts(outdir / "caph0126_by_send_ctas.csv", crows)
    io.write_dicts(outdir / "caph0126_by_send_videos.csv", vrows)
    io.write_dicts(outdir / "caph0126_by_send_daily.csv", drows)

    for t in trows:
        print(f"\n{t['send']}  ({t['start']} to {t['end']})")
        for k in ("sessions", "users", "new_users", "engaged", "pageviews",
                  "engagement_sec", "signups_ga4", "site_sessions"):
            print(f"  {k:<16}{t[k]}")
    print("\nCTAs")
    for r in crows:
        print(f"  {r['send']:<22}{r['cta']:<28}{r['sessions']:>5}{r['users']:>5}{r['new_users']:>5}")
    print("\nVIDEO")
    for r in vrows:
        print(f"  {r['send']:<22}{r['video'][:50]:<52}{r['starts']:>5}{r['viewers']:>5}{r['completions']:>5}  {r['completion_rate']}")
    print("\nDAILY")
    for r in drows:
        print(f"  {r['send']:<22}{r['date']}{r['sessions']:>6}")


if __name__ == "__main__":
    main()

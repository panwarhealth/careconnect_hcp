"""JAS26 Clinical Bites video series analytics (Hydralyte diabetes + FESS allergic rhinitis).

    reports/.venv/bin/python reports/ga4/jas26_clinical_bites.py

Video events come from hcp-videos/assets/video-analytics.js (Vimeo -> GA4
video_start / video_progress / video_complete, fired on the episode pages).
GA4's standard API has no per-viewer event history, so "watched every
episode" can't be counted directly. Reported instead: distinct viewers across
the series, average episodes per viewer, and a ceiling (nobody can have
finished the series more often than its least-watched episode was finished).

Outputs to reports/out/JAS26/:
  - jas26_clinical_bites_episodes.csv   per-episode views, completions, completion rate
  - jas26_clinical_bites_series.csv     series viewers, episodes/viewer, sessions, ceiling
  - jas26_clinical_bites_paths.csv      where episode + resource page views came from
  - jas26_clinical_bites_daily.csv      daily video starts per series since launch
  - jas26_clinical_bites_sources.csv    traffic source of sessions that touched the series
  - jas26_clinical_bites_timing.csv     plays by time of day / weekday / device, plus site sessions by time of day
"""
from __future__ import annotations

import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from google.analytics.data_v1beta.types import Filter, FilterExpression, FilterExpressionList

from lib import ga4, io

START, END = "2026-07-01", "2026-09-30"

SERIES = {
    "Hydralyte - Diabetes Sick Day Management": {
        "landing": "/clinical-bites/",
        "episodes": [
            "/video/why-sick-day-planning-is-important-in-diabetes/",
            "/video/what-goes-in-a-sick-day-plan-for-people-with-diabetes/",
            "/video/medication-management-during-sick-days/",
            "/video/dehydration-during-sick-days-the-role-of-ors/",
            "/video/preparing-a-sick-day-management-kit/",
        ],
    },
    "FESS - Allergic Rhinitis Rounds": {
        "landing": "/allergic-rhinitis-clinical-bites/",
        "episodes": [
            "/video/not-another-sinus-infection/",
            "/video/are-steroids-safe-for-my-child/",
            "/video/expecting-and-congested/",
        ],
    },
}


def _f(field: str, value: str, match=Filter.StringFilter.MatchType.EXACT) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, string_filter=Filter.StringFilter(value=value, match_type=match)))


def _in(field: str, values: list[str]) -> FilterExpression:
    return FilterExpression(filter=Filter(field_name=field, in_list_filter=Filter.InListFilter(values=values)))


def _and(*e: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(e)))


def report(c, dims, metrics, flt, limit=10000):
    r = ga4.run_report(c, dims=dims, metrics=metrics, start=START, end=END, dimension_filter=flt, limit=limit)
    return [([d.value for d in x.dimension_values], [int(float(m.value)) for m in x.metric_values]) for x in r.rows]


def total(c, metrics, flt) -> list[int]:
    rows = report(c, [], metrics, flt)
    return rows[0][1] if rows else [0] * len(metrics)


BLOCKS = [("Before 9am", range(0, 9)), ("9am-12pm", range(9, 12)), ("12-2pm", range(12, 14)),
          ("2-5pm", range(14, 17)), ("5-8pm", range(17, 20)), ("8pm-midnight", range(20, 24))]
DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"]


def timing_rows(c, name, flt, metric) -> list[dict]:
    """Share of plays (or sessions) by time-of-day block, weekday and device. Hours are property time (Sydney)."""
    out = []
    hours = {int(h): n for (h,), (n,) in report(c, ["hour"], [metric], flt)}
    tot = sum(hours.values()) or 1
    for label, rng in BLOCKS:
        n = sum(hours.get(h, 0) for h in rng)
        out.append({"series": name, "dimension": "time of day", "bucket": label, "count": n, "pct": round(n / tot * 100)})
    for dim, gdim, order in (("weekday", "dayOfWeekName", DAYS), ("device", "deviceCategory", None)):
        vals = {k: n for (k,), (n,) in report(c, [gdim], [metric], flt)}
        tot = sum(vals.values()) or 1
        for k in order or sorted(vals, key=lambda x: -vals[x]):
            out.append({"series": name, "dimension": dim, "bucket": k, "count": vals.get(k, 0),
                        "pct": round(vals.get(k, 0) / tot * 100)})
    return out


def bucket_referrer(ref: str, s: dict) -> str:
    path = "/" + ref.split("://", 1)[-1].split("/", 1)[-1].split("?")[0] if "://" in ref else ref
    if not ref:
        return "No referrer (direct / email link)"
    if "hcp.carepharma.com.au" not in ref:
        return "External site (incl. webmail eDM clicks)"
    if path == s["landing"]:
        return "Series landing page"
    if path.startswith("/tools-and-videos"):
        return "Tools & Videos page"
    if path in s["episodes"]:
        return "Another episode in the series"
    if path == "/":
        return "Homepage"
    return "Other Care Connect page"


def main() -> None:
    c = ga4.client()
    episodes, series_rows, paths, daily_rows, source_rows, timing = [], [], [], [], [], []

    for name, s in SERIES.items():
        eps = s["episodes"]
        per_ep_viewers, per_ep_completers = [], []
        for i, ep in enumerate(eps, 1):
            pv = total(c, ["screenPageViews", "totalUsers"], _and(_f("eventName", "page_view"), _f("pagePath", ep)))
            st = total(c, ["eventCount", "totalUsers"], _and(_f("eventName", "video_start"), _f("pagePath", ep)))
            cp = total(c, ["totalUsers"], _and(_f("eventName", "video_complete"), _f("pagePath", ep)))
            per_ep_viewers.append(st[1])
            per_ep_completers.append(cp[0])
            episodes.append({"series": name, "episode": i, "page": ep, "page_views": pv[0], "page_users": pv[1],
                             "video_plays": st[0], "viewers_pressed_play": st[1], "viewers_watched_to_end": cp[0],
                             "completion_rate": f"{cp[0] / st[1] * 100:.0f}%" if st[1] else ""})

        viewers, sessions, plays = total(c, ["totalUsers", "sessions", "eventCount"],
                                         _and(_f("eventName", "video_start"), _in("pagePath", eps)))
        completers = total(c, ["totalUsers"], _and(_f("eventName", "video_complete"), _in("pagePath", eps)))[0]
        landing = total(c, ["screenPageViews", "totalUsers"], _and(_f("eventName", "page_view"), _f("pagePath", s["landing"])))
        series_rows.append({
            "series": name, "episodes": len(eps),
            "landing_page_views": landing[0], "landing_page_users": landing[1],
            "distinct_viewers_any_episode": viewers,
            "episodes_started_per_viewer": round(sum(per_ep_viewers) / viewers, 2) if viewers else "",
            "sessions_with_a_play": sessions,
            "plays_per_session": round(plays / sessions, 2) if sessions else "",
            "distinct_viewers_finished_any_episode": completers,
            "ceiling_watched_every_episode_to_end": min(per_ep_completers),
            "ceiling_started_every_episode": min(per_ep_viewers),
        })

        # Where episode views and resource-page views came from (page_view x pageReferrer).
        agg = defaultdict(int)
        for (page, ref), (pv,) in report(c, ["pagePath", "pageReferrer"], ["screenPageViews"],
                                        _and(_f("eventName", "page_view"), _in("pagePath", eps))):
            agg[bucket_referrer(ref, s)] += pv
        for k, v in sorted(agg.items(), key=lambda x: -x[1]):
            paths.append({"series": name, "what": "Episode page views", "came_from": k, "page_views": v})
        clicks = defaultdict(int)
        for (page, ref), (pv,) in report(c, ["pagePath", "pageReferrer"], ["screenPageViews"],
                                        _and(_f("eventName", "page_view"),
                                             _f("pageReferrer", s["landing"], Filter.StringFilter.MatchType.CONTAINS))):
            if page != s["landing"] and page not in eps:
                clicks[page.rstrip("/") + "/"] += pv
        for page, pv in sorted(clicks.items(), key=lambda x: -x[1]):
            paths.append({"series": name, "what": "Clicked from landing page to", "came_from": page, "page_views": pv})
        downloads = defaultdict(int)
        for (page, fname, url), (n,) in report(c, ["pagePath", "customEvent:file_name", "linkUrl"], ["eventCount"],
                                              _and(_f("eventName", "file_download"), _in("pagePath", eps + [s["landing"]]))):
            if "hcp.carepharma.com.au" in url:
                where = "landing page" if page == s["landing"] else "episode page"
                downloads[(where, fname.rsplit("/", 1)[-1])] += n
        for (where, fname), n in sorted(downloads.items(), key=lambda x: -x[1]):
            paths.append({"series": name, "what": f"Resource downloaded from {where}", "came_from": fname, "page_views": n})

        timing += timing_rows(c, name, _and(_f("eventName", "video_start"), _in("pagePath", eps)), "eventCount")

        for (d,), (n, u) in sorted(report(c, ["date"], ["eventCount", "totalUsers"],
                                          _and(_f("eventName", "video_start"), _in("pagePath", eps)))):
            daily_rows.append({"series": name, "date": f"{d[:4]}-{d[4:6]}-{d[6:]}", "video_plays": n, "viewers": u})

        for (src, med, camp), (sess, users) in sorted(
                report(c, ["sessionSource", "sessionMedium", "sessionCampaignName"], ["sessions", "totalUsers"],
                       _in("pagePath", eps + [s["landing"]])), key=lambda x: -x[1][0]):
            source_rows.append({"series": name, "source": src, "medium": med, "campaign": camp, "sessions": sess, "users": users})

    timing += timing_rows(c, "Whole site (sessions)", None, "sessions")

    outdir = io.out_dir("JAS26")
    io.write_dicts(outdir / "jas26_clinical_bites_timing.csv", timing)
    io.write_dicts(outdir / "jas26_clinical_bites_episodes.csv", episodes)
    io.write_dicts(outdir / "jas26_clinical_bites_series.csv", series_rows)
    io.write_dicts(outdir / "jas26_clinical_bites_paths.csv", paths)
    io.write_dicts(outdir / "jas26_clinical_bites_daily.csv", daily_rows)
    io.write_dicts(outdir / "jas26_clinical_bites_sources.csv", source_rows)
    for r in episodes + series_rows + paths:
        print(r)


if __name__ == "__main__":
    main()

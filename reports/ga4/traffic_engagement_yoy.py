"""YoY traffic + engagement headline for the quarterly review slide.

    python reports/ga4/traffic_engagement_yoy.py --period FY27Q1

Pulls the period and the same quarter one FY earlier, and writes a single
comparison CSV: active users, total users, sessions, pageviews, and average
engaged time per active user.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

METRICS = ["activeUsers", "totalUsers", "sessions", "screenPageViews", "userEngagementDuration"]


def fmt_mmss(seconds: float) -> str:
    m, s = divmod(round(seconds), 60)
    return f"{m}m {s:02d}s"


def totals(c, p: periods.Period) -> dict:
    r = ga4.run_report(c, dims=[], metrics=METRICS, start=p.start, end=p.end)
    vals = [float(v.value) for v in r.rows[0].metric_values]
    d = dict(zip(METRICS, vals))
    d["engaged_s_per_active_user"] = d["userEngagementDuration"] / d["activeUsers"]
    return d


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY27Q1")
    ap.add_argument("--outdir", default="AMJ26", help="subdir of reports/out/ (default: AMJ26)")
    args = ap.parse_args()
    cur = periods.from_args(args)
    prior = cur.prior_year
    c = ga4.client()

    rows = []
    for p in (prior, cur):
        t = totals(c, p)
        rows.append({
            "period": p.label, "start": p.start, "end": p.end,
            "active_users": int(t["activeUsers"]),
            "total_users": int(t["totalUsers"]),
            "sessions": int(t["sessions"]),
            "pageviews": int(t["screenPageViews"]),
            "avg_engaged_time_per_user_s": round(t["engaged_s_per_active_user"], 1),
            "avg_engaged_time_per_user": fmt_mmss(t["engaged_s_per_active_user"]),
        })

    a, b = rows
    pct = {k: f"+{(b[k] - a[k]) / a[k] * 100:.0f}%" if a[k] else ""
           for k in ("active_users", "total_users", "sessions", "pageviews", "avg_engaged_time_per_user_s")}
    rows.append({"period": "YoY change", "start": "", "end": "",
                 "active_users": pct["active_users"], "total_users": pct["total_users"],
                 "sessions": pct["sessions"], "pageviews": pct["pageviews"],
                 "avg_engaged_time_per_user_s": pct["avg_engaged_time_per_user_s"],
                 "avg_engaged_time_per_user": ""})

    path = io.write_dicts(io.out_dir(args.outdir) / f"traffic_yoy_{cur.slug}.csv", rows)
    for r in rows:
        print(r)
    return


if __name__ == "__main__":
    main()

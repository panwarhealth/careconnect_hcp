"""Headline GA4 numbers for a quarterly review.

    python reports/ga4/headline.py --period FY26Q4
    python reports/ga4/headline.py --start 2026-01-01 --end 2026-03-31

Writes to reports/out/ga4/:
  - traffic_daily_<slug>.csv        daily totals for the period
  - traffic_yoy_monthly_<slug>.csv  monthly totals, prior FY start → period end
  - top_pages_<slug>.csv            top 200 pages by views
  - channel_<slug>.csv              channel breakdown for the period
  - channel_<slug>_prioryear.csv    same window one FY earlier (YoY)
  - source_medium_<slug>.csv        source/medium breakdown
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26Q4")
    args = ap.parse_args()
    p = periods.from_args(args)
    prior = p.prior_year if p.fy else None
    out = io.out_dir("ga4")
    c = ga4.client()
    print(f"GA4 headline — {p.label}  ({p.start} .. {p.end})")

    # 1. Daily traffic for the period
    r = ga4.run_report(c, dims=["date"],
                       metrics=["totalUsers", "newUsers", "sessions", "screenPageViews",
                                "engagementRate", "averageSessionDuration"],
                       start=p.start, end=p.end, order_by=[ga4.dimension_order("date")])
    io.write_csv(out / f"traffic_daily_{p.slug}.csv",
                 ["date", "users", "new_users", "sessions", "pageviews",
                  "engagement_rate", "avg_session_duration_s"], ga4.rows_of(r))

    # 2. Monthly traffic, prior-FY start → period end (gives a YoY trend line)
    monthly_start = prior.start if prior else p.start
    r = ga4.run_report(c, dims=["yearMonth"],
                       metrics=["totalUsers", "newUsers", "sessions", "screenPageViews", "engagementRate"],
                       start=monthly_start, end=p.end)
    io.write_csv(out / f"traffic_yoy_monthly_{p.slug}.csv",
                 ["year_month", "users", "new_users", "sessions", "pageviews", "engagement_rate"],
                 sorted(ga4.rows_of(r)))

    # 3. Top pages
    r = ga4.run_report(c, dims=["pagePath", "pageTitle"],
                       metrics=["screenPageViews", "totalUsers", "averageSessionDuration", "bounceRate"],
                       start=p.start, end=p.end,
                       order_by=[ga4.metric_order("screenPageViews")], limit=200)
    io.write_csv(out / f"top_pages_{p.slug}.csv",
                 ["page_path", "page_title", "pageviews", "users", "avg_session_duration_s", "bounce_rate"],
                 ga4.rows_of(r))

    # 4. Channel breakdown for the period (+ prior-year window for YoY)
    windows = [(p.slug, p.start, p.end)]
    if prior:
        windows.append((f"{p.slug}_prioryear", prior.start, prior.end))
    for slug, start, end in windows:
        r = ga4.run_report(c, dims=["sessionDefaultChannelGroup"],
                           metrics=["totalUsers", "newUsers", "sessions", "screenPageViews"],
                           start=start, end=end, order_by=[ga4.metric_order("sessions")])
        io.write_csv(out / f"channel_{slug}.csv",
                     ["channel", "users", "new_users", "sessions", "pageviews"], ga4.rows_of(r))

    # 5. Source/medium (more granular than channel)
    r = ga4.run_report(c, dims=["sessionSource", "sessionMedium"],
                       metrics=["totalUsers", "newUsers", "sessions"],
                       start=p.start, end=p.end,
                       order_by=[ga4.metric_order("sessions")], limit=100)
    io.write_csv(out / f"source_medium_{p.slug}.csv",
                 ["source", "medium", "users", "new_users", "sessions"], ga4.rows_of(r))


if __name__ == "__main__":
    main()

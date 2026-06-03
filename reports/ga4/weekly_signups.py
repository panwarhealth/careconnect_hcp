"""Weekly real-HCP sign-ups (DB) vs GA4 totalUsers, with campaign annotations.

    python reports/ga4/weekly_signups.py                       # default Apr 2024 → Mar 2026
    python reports/ga4/weekly_signups.py --start 2025-04-01 --end 2026-03-29
    python reports/ga4/weekly_signups.py --period FY26

Two ISO-week series (Mon-aligned, Sydney tz, internal staff excluded):
  - Series 1: real-HCP sign-ups from the WordPress DB
  - Series 2: GA4 totalUsers

Writes reports/out/ga4/weekly_signups_users_<slug>.csv and prints top-5 spike weeks.
"""
from __future__ import annotations

import argparse
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import db, ga4, io, periods

DEFAULT_START, DEFAULT_END = "2024-04-01", "2026-03-29"

# ISO week start (Mon) → what was running that week.
CAMPAIGNS = {
    date(2024, 6, 17): "Mailchimp: FESS 'little airways' (21 Jun)",
    date(2024, 6, 24): "Mailchimp: 'Winter coughs and colds' (30 Jun)",
    date(2025, 5, 12): "Solus eDM: FESS cold & flu season (13 May, 17k sent)",
    date(2025, 8,  4): "Solus eDM: Rectogesic 'cake' (5 Aug, 16.6k sent)",
    date(2025, 9,  8): "Mailchimp: 'cold and flu symptoms' (8 Sep)",
    date(2025, 9, 22): "Mailchimp: Dr Tattersall (22 Sep) + Solus Zaditen (30 Sep, 19k sent)",
    date(2025, 9, 29): "Mailchimp: 'bowel habits' (28 Sep) + Solus Zaditen (30 Sep, 19k sent)",
    date(2025,10, 20): "Solus eDM: Hydralyte & Rectogesic GLP-1RA (23 Oct, 19k sent)",
    date(2025,10, 27): "Mailchimp: 'Claim FESS Samples' unconverted list (29 Oct, 60.9% open) + Solus Hydralyte/Rectogesic (23 Oct)",
    date(2025,11,  3): "TMR: Allergic rhinitis article (4 Nov, 16.2k sent)",
    date(2025,11, 10): "Mailchimp: GLP-1RA Hydralyte-Rectogesic (14 Nov)",
    date(2025,11, 17): "TMR: Rectogesic 'chocolate cake' (17 Nov, 16.2k sent) + Solus Weekend Read (22 Nov)",
    date(2025,11, 24): "Mailchimp: FESS samples follow-up unconverted (24 Nov, 53.1% open) + Solus Weekend Read (29 Nov)",
    date(2025,12,  1): "Solus Weekend Read (29 Nov continued)",
    date(2025,12,  8): "Solus Hydralyte (9 Dec, 16.1k sent) + Weekend Read (13 Dec)",
    date(2026,  3,  9): "Solus: FESS World Sleep Day (12 Mar, 15.9k sent)",
    date(2026,  3, 16): "Solus: Rectogesic 'NEW CPD 8.5hrs' (19 Mar, 15.9k sent) — MCA/RACGP audit launched",
    date(2026,  3, 23): "MCA/RACGP audit live (Solus CPD campaign running)",
}


def generate_weeks(start: date, end: date) -> list[date]:
    """Monday-aligned ISO week starts spanning [start, end]."""
    d = start - timedelta(days=start.weekday())
    weeks = []
    while d <= end:
        weeks.append(d)
        d += timedelta(7)
    return weeks


def pull_db_weekly(start: str, end: str) -> dict[date, int]:
    # Pull per-day Sydney-local sign-up counts; bucket into ISO weeks in Python.
    rows = db.query(f"""
SET time_zone = '+00:00';
SELECT DATE(CONVERT_TZ(user_registered, '+00:00', 'Australia/Sydney')) AS d, COUNT(*) AS n
FROM tbstwp_users
WHERE user_email NOT REGEXP '@(carepharma|tbstdigital|panwarhealth|rnafiel|rscottdigital)\\\\.com\\\\.au$'
GROUP BY d HAVING d >= '{start}' AND d <= '{end}' ORDER BY d;
""")
    out: dict[date, int] = {}
    for r in rows:
        d = date.fromisoformat(r["d"])
        ws = d - timedelta(days=d.weekday())
        out[ws] = out.get(ws, 0) + int(r["n"])
    return out


def pull_ga4_weekly(c, start: str, end: str, weeks: list[date]) -> dict[date, int]:
    resp = ga4.run_report(c, dims=["date"], metrics=["totalUsers"], start=start, end=end,
                          order_by=[ga4.dimension_order("date")], limit=800)
    out: dict[date, int] = {w: 0 for w in weeks}
    for row in resp.rows:
        raw = row.dimension_values[0].value  # YYYYMMDD
        d = date(int(raw[:4]), int(raw[4:6]), int(raw[6:8]))
        ws = d - timedelta(days=d.weekday())
        if ws in out:
            out[ws] += int(row.metric_values[0].value)
    return out


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--start", default=DEFAULT_START, help=f"start YYYY-MM-DD (default {DEFAULT_START})")
    ap.add_argument("--end", default=DEFAULT_END, help=f"end YYYY-MM-DD (default {DEFAULT_END})")
    ap.add_argument("--period", help="FY period (e.g. FY26) instead of --start/--end")
    args = ap.parse_args()
    if args.period:
        p = periods.resolve(args.period)
        start, end, slug = p.start, p.end, p.slug
    else:
        start, end, slug = args.start, args.end, f"{args.start}_{args.end}"

    weeks = generate_weeks(date.fromisoformat(start), date.fromisoformat(end))
    print(f"Weekly sign-ups vs users — {start} .. {end}  ({len(weeks)} weeks)")

    print("Pulling DB sign-ups...")
    db_data = pull_db_weekly(start, end)
    print(f"  {len(db_data)} weeks with sign-ups")
    print("Pulling GA4 daily users...")
    ga4_data = pull_ga4_weekly(ga4.client(), start, end, weeks)

    rows = []
    for ws in weeks:
        rows.append({"week_start": ws.isoformat(), "week_end": (ws + timedelta(6)).isoformat(),
                     "signups": db_data.get(ws, 0), "users": ga4_data.get(ws, 0)})
    out_file = io.write_dicts(io.out_dir("ga4") / f"weekly_signups_users_{slug}.csv", rows)
    io.copy_to_downloads(out_file)

    for metric, title in (("signups", "sign-up"), ("users", "user")):
        print(f"\n### Top 5 {title} weeks")
        print("| Week starting | Count | What was running |\n|---|---|---|")
        for r in sorted(rows, key=lambda x: x[metric], reverse=True)[:5]:
            ws = date.fromisoformat(r["week_start"])
            print(f"| {r['week_start']} | {r[metric]} | {CAMPAIGNS.get(ws, 'no campaign identified — investigate')} |")


if __name__ == "__main__":
    main()

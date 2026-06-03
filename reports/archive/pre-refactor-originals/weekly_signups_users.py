"""
Generate weekly_signups_users_apr24_mar26.csv
Two series, 104 ISO weeks Mon 2024-04-01 → Sun 2026-03-29:
  - Series 1: real-HCP sign-ups from WordPress DB (Sydney tz, internal-staff excluded)
  - Series 2: GA4 totalUsers (property 306115293)

Also prints top-5 spike weeks for each series with campaign annotations.
"""
import json
import subprocess
import csv
from datetime import date, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import DateRange, Dimension, Metric, RunReportRequest, OrderBy
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT         = Path(__file__).resolve().parent.parent
TOKEN_JSON   = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID  = "306115293"
SYDNEY       = ZoneInfo("Australia/Sydney")

# --- Campaign lookup (week_start → description) ----------------------------
# Maps ISO week start (Mon) to what was running that week.
# Week start is always a Monday.
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


def week_label(d: date) -> str:
    return f"{d.isoformat()} – {(d + timedelta(6)).isoformat()}"


def ga4_credentials() -> Credentials:
    info = json.loads(TOKEN_JSON.read_text())
    creds = Credentials(
        token=info.get("token"),
        refresh_token=info["refresh_token"],
        token_uri=info["token_uri"],
        client_id=info["client_id"],
        client_secret=info["client_secret"],
        scopes=info.get("scopes"),
    )
    creds.refresh(Request())
    TOKEN_JSON.write_text(creds.to_json())
    return creds


def pull_ga4_daily(start: str, end: str) -> dict[str, int]:
    """Pull totalUsers by date from GA4. Returns {YYYY-MM-DD: users}."""
    client = BetaAnalyticsDataClient(credentials=ga4_credentials())
    resp = client.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="date")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"))],
        limit=800,
    ))
    out = {}
    for row in resp.rows:
        raw = row.dimension_values[0].value          # YYYYMMDD
        d = f"{raw[:4]}-{raw[4:6]}-{raw[6:8]}"
        out[d] = int(row.metric_values[0].value)
    return out


def pull_db_weekly(env_path: Path) -> dict[date, int]:
    """
    Pull real-HCP sign-ups per ISO week from the DB.
    Returns {week_start (Mon): count}.
    """
    pw = None
    for line in env_path.read_text().splitlines():
        if line.startswith("MYSQL_ROOT_PASSWORD="):
            pw = line.split("=", 1)[1].strip()
    assert pw, "MYSQL_ROOT_PASSWORD not found in .env"

    sql = r"""
SET time_zone = '+10:00';
SELECT
  DATE_SUB(
    DATE(CONVERT_TZ(user_registered, '+00:00', 'Australia/Sydney')),
    INTERVAL (WEEKDAY(DATE(CONVERT_TZ(user_registered, '+00:00', 'Australia/Sydney')))) DAY
  ) AS week_start,
  COUNT(*) AS signups
FROM tbstwp_users
WHERE
  user_registered >= '2024-03-31 14:00:00'   -- 2024-04-01 00:00 AEDT = 2024-03-31 13:00 UTC
  AND user_registered < '2026-03-29 14:00:00' -- 2026-03-30 00:00 AEDT = 2026-03-29 13:00 UTC (AEDT=+11 in Mar)
  AND user_email NOT REGEXP '@(carepharma|tbstdigital|panwarhealth|rnafiel|rscottdigital)\\.com\\.au$'
GROUP BY week_start
ORDER BY week_start;
"""
    result = subprocess.run(
        ["docker", "compose", "exec", "-T", "db",
         "mysql", "-u", "root", f"-p{pw}", "--batch", "--skip-column-names", "hcp_care"],
        input=sql, capture_output=True, text=True, cwd=str(ROOT)
    )
    out: dict[date, int] = {}
    for line in result.stdout.strip().splitlines():
        parts = line.strip().split("\t")
        if len(parts) == 2:
            try:
                out[date.fromisoformat(parts[0])] = int(parts[1])
            except ValueError:
                pass
    return out


def generate_weeks() -> list[date]:
    """104 ISO weeks Mon 2024-04-01 → Mon 2026-03-23 (last week ends 2026-03-29)."""
    weeks = []
    d = date(2024, 4, 1)
    end = date(2026, 3, 23)
    while d <= end:
        weeks.append(d)
        d += timedelta(7)
    return weeks


def aggregate_ga4_by_week(daily: dict[str, int], weeks: list[date]) -> dict[date, int]:
    out: dict[date, int] = {w: 0 for w in weeks}
    for date_str, users in daily.items():
        d = date.fromisoformat(date_str)
        week_start = d - timedelta(days=d.weekday())
        if week_start in out:
            out[week_start] += users
    return out


def find_windows_downloads() -> Path | None:
    users_root = Path("/mnt/c/Users")
    if not users_root.exists():
        return None
    for user_dir in sorted(users_root.iterdir()):
        dl = user_dir / "Downloads"
        if dl.exists():
            return dl
    return None


def main() -> None:
    weeks = generate_weeks()
    print(f"Generated {len(weeks)} weeks: {weeks[0]} → {weeks[-1]+timedelta(6)}")

    print("Pulling DB sign-ups...")
    db_data = pull_db_weekly(ROOT / ".env")
    print(f"  Got {len(db_data)} weeks with sign-ups from DB")

    print("Pulling GA4 daily users...")
    ga4_daily = pull_ga4_daily("2024-04-01", "2026-03-29")
    print(f"  Got {len(ga4_daily)} days from GA4")
    ga4_data = aggregate_ga4_by_week(ga4_daily, weeks)

    # Build rows
    rows = []
    for ws in weeks:
        we = ws + timedelta(6)
        rows.append({
            "week_start": ws.isoformat(),
            "week_end":   we.isoformat(),
            "signups":    db_data.get(ws, 0),
            "users":      ga4_data.get(ws, 0),
        })

    # Write CSV
    out_dir = ROOT / "reports" / "out"
    out_file = out_dir / "weekly_signups_users_apr24_mar26.csv"
    with out_file.open("w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["week_start","week_end","signups","users"])
        writer.writeheader()
        writer.writerows(rows)
    print(f"\nCSV written: {out_file}  ({len(rows)} rows)")

    # Copy to Windows Downloads
    dl = find_windows_downloads()
    if dl:
        dest = dl / "weekly_signups_users_apr24_mar26.csv"
        import shutil
        shutil.copy(out_file, dest)
        print(f"Copied to: {dest}")
    else:
        print("Windows Downloads not found — file is at reports/out/ only")

    # --- Top 5 spike weeks ---
    print("\n### Top 5 sign-up weeks")
    print(f"| Week starting | Sign-ups | What was running |")
    print(f"|---|---|---|")
    for r in sorted(rows, key=lambda x: x["signups"], reverse=True)[:5]:
        ws = date.fromisoformat(r["week_start"])
        campaign = CAMPAIGNS.get(ws, "no campaign identified — investigate")
        print(f"| {r['week_start']} | {r['signups']} | {campaign} |")

    print("\n### Top 5 user weeks")
    print(f"| Week starting | Users | What was running |")
    print(f"|---|---|---|")
    for r in sorted(rows, key=lambda x: x["users"], reverse=True)[:5]:
        ws = date.fromisoformat(r["week_start"])
        campaign = CAMPAIGNS.get(ws, "no campaign identified — investigate")
        print(f"| {r['week_start']} | {r['users']} | {campaign} |")


if __name__ == "__main__":
    main()

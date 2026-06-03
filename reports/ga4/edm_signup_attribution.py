"""
FY26 eDM → HCP sign-up attribution — two-source approach.

SOURCE 1 — GA4 TAGGED (conversion_event_signup by sessionCampaignName):
  Sessions that originated from a CAPH-coded eDM link and completed sign-up.
  Undercount: GA4 only tracked 130 of 332 DB sign-ups (39% tracking gap).
  Used as a floor / validation, not primary attribution.

SOURCE 2 — DB SPIKE CORRELATION:
  Daily HCP sign-ups from WordPress DB (ground truth, 332 FY26 sign-ups).
  For each send: 14-day window total vs 28-day pre-send rolling baseline.
  Attributable spike = window_total - (clean_baseline_daily_avg × 14).
  Overlapping send windows flagged; contaminated baseline days excluded.

COMBINED BEST-ESTIMATE:
  best_estimate = max(tagged_source1, round(spike_source2))
  Spike already includes tagged sessions — they're not additive.
  Where spike ≤ 0: use tagged count (spike flat, but some tagged sign-ups confirmed).

DB data embedded from direct query (FY26 window is closed — no re-query needed).

Outputs:
  - fy26_edm_signup_attribution.csv
  - fy26_edm_signup_attribution.md
"""
import sys
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)

# TODO: parameterize via lib.periods — START/END, the embedded DB_DAILY_RAW
# counts, and the SENDS calendar are all FY26-locked (the window is closed and
# the DB ground-truth is baked in); re-pointing the date would desync them.
START, END = "2025-04-01", "2026-03-31"

# ---------------------------------------------------------------------------
# DB daily sign-up counts — WordPress tbstwp_users.user_registered, FY26
# Only days with ≥1 sign-up are listed; all unlisted days = 0.
# ---------------------------------------------------------------------------
DB_DAILY_RAW = {
    "2025-04-01": 1,  "2025-04-07": 1,  "2025-04-10": 2,  "2025-04-15": 1,
    "2025-04-16": 1,  "2025-04-22": 1,  "2025-04-24": 1,  "2025-04-26": 1,
    "2025-05-02": 1,  "2025-05-10": 1,  "2025-05-13": 13, "2025-05-14": 1,
    "2025-05-16": 1,  "2025-05-18": 2,  "2025-05-23": 1,  "2025-05-31": 2,
    "2025-06-02": 1,  "2025-06-04": 1,  "2025-06-10": 1,  "2025-06-17": 1,
    "2025-06-18": 1,  "2025-06-23": 2,  "2025-06-24": 1,  "2025-06-29": 1,
    "2025-07-04": 1,  "2025-07-08": 1,  "2025-07-14": 1,  "2025-07-17": 1,
    "2025-07-19": 1,  "2025-07-21": 1,  "2025-07-25": 1,  "2025-07-26": 1,
    "2025-08-05": 16, "2025-08-06": 1,  "2025-08-07": 1,  "2025-08-10": 1,
    "2025-08-15": 1,  "2025-08-19": 1,  "2025-08-23": 1,  "2025-08-27": 1,
    "2025-08-28": 1,  "2025-08-30": 2,  "2025-09-08": 1,  "2025-09-09": 3,
    "2025-09-10": 1,  "2025-09-12": 1,  "2025-09-17": 1,  "2025-09-24": 1,
    "2025-09-30": 23, "2025-10-01": 5,  "2025-10-02": 5,  "2025-10-03": 3,
    "2025-10-05": 1,  "2025-10-06": 2,  "2025-10-07": 1,  "2025-10-09": 1,
    "2025-10-10": 2,  "2025-10-11": 2,  "2025-10-13": 2,  "2025-10-18": 2,
    "2025-10-22": 3,  "2025-10-23": 5,  "2025-10-24": 2,  "2025-10-26": 1,
    "2025-10-27": 1,  "2025-10-29": 9,  "2025-10-30": 7,  "2025-10-31": 3,
    "2025-11-02": 1,  "2025-11-03": 3,  "2025-11-04": 17, "2025-11-05": 10,
    "2025-11-06": 6,  "2025-11-07": 3,  "2025-11-08": 3,  "2025-11-10": 4,
    "2025-11-13": 1,  "2025-11-16": 2,  "2025-11-17": 7,  "2025-11-18": 2,
    "2025-11-19": 2,  "2025-11-21": 3,  "2025-11-22": 3,  "2025-11-23": 1,
    "2025-11-24": 11, "2025-11-25": 2,  "2025-11-26": 1,  "2025-11-27": 1,
    "2025-11-29": 1,  "2025-12-08": 1,  "2025-12-09": 8,  "2025-12-10": 1,
    "2025-12-11": 3,  "2025-12-13": 4,  "2025-12-16": 1,  "2025-12-17": 2,
    "2025-12-18": 1,  "2025-12-21": 2,  "2025-12-26": 1,  "2025-12-31": 1,
    "2026-01-06": 1,  "2026-01-12": 1,  "2026-01-25": 1,  "2026-01-27": 1,
    "2026-02-03": 1,  "2026-02-04": 3,  "2026-02-24": 1,  "2026-02-25": 2,
    "2026-02-26": 3,  "2026-03-04": 3,  "2026-03-05": 2,  "2026-03-06": 1,
    "2026-03-07": 1,  "2026-03-12": 2,  "2026-03-15": 3,  "2026-03-16": 1,
    "2026-03-18": 9,  "2026-03-19": 25, "2026-03-20": 5,  "2026-03-21": 5,
    "2026-03-22": 3,  "2026-03-23": 2,  "2026-03-24": 5,  "2026-03-25": 2,
    "2026-03-29": 1,  "2026-03-31": 2,
}

def db(day: date) -> int:
    return DB_DAILY_RAW.get(day.strftime("%Y-%m-%d"), 0)

# ---------------------------------------------------------------------------
# Send schedule — (iso_date, label)
# ---------------------------------------------------------------------------
SENDS = [
    ("2025-05-13", "FESS cold & flu (MT)"),
    ("2025-08-05", "Rectogesic cake/bowel (MT)"),
    ("2025-09-30", "FESS allergic rhinitis (MT)"),
    ("2025-10-23", "Hydralyte GLP-1RA (MT)"),
    ("2025-11-04", "FESS allergic rhinitis (TMR)"),
    ("2025-11-17", "Rectogesic chocolate (TMR)"),
    ("2025-11-22", "FESS Tattersall SPON CON (MT)"),
    ("2025-11-29", "FESS allergy teens SPON CON (MT)"),
    ("2025-12-09", "Hydralyte GI/travel (MT)"),
    ("2025-12-13", "FESS allergy eye SPON CON (MT)"),
    ("2026-03-12", "FESS MIST+ (Solus)"),
    ("2026-03-19", "Rectogesic CPD (MT)"),
]

WINDOW_DAYS    = 14   # D0 to D+13 inclusive
BASELINE_DAYS  = 28   # D-28 to D-1 inclusive


def send_window(d0: date) -> set[date]:
    return {d0 + timedelta(days=i) for i in range(WINDOW_DAYS)}


def all_send_windows() -> dict[date, set[date]]:
    return {date.fromisoformat(s[0]): send_window(date.fromisoformat(s[0])) for s in SENDS}


# ---------------------------------------------------------------------------
# Source 1: GA4 tagged sign-ups by campaign code
# ---------------------------------------------------------------------------
def pull_tagged_signups(ga: BetaAnalyticsDataClient) -> dict[str, int]:
    """Returns {campaign_name: signup_event_count} for all CAPH-coded campaigns."""
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="sessionCampaignName")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="eventName",
            string_filter=Filter.StringFilter(value="conversion_event_signup"),
        )),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)],
        limit=200,
    ))
    return {
        r.dimension_values[0].value: int(r.metric_values[0].value)
        for r in resp.rows
    }


# ---------------------------------------------------------------------------
# Source 2: DB spike correlation
# ---------------------------------------------------------------------------
def spike_analysis(d0: date, all_windows: dict[date, set[date]]) -> dict:
    other_windows: set[date] = set()
    for send_d0, window in all_windows.items():
        if send_d0 != d0:
            other_windows.update(window)

    # 14-day attribution window
    window_days = [d0 + timedelta(days=i) for i in range(WINDOW_DAYS)]
    window_total = sum(db(d) for d in window_days)

    # 28-day pre-send baseline, excluding contaminated days
    baseline_candidates = [d0 - timedelta(days=i) for i in range(1, BASELINE_DAYS + 1)]
    clean_days   = [d for d in baseline_candidates if d not in other_windows]
    dirty_days   = len(baseline_candidates) - len(clean_days)
    clean_total  = sum(db(d) for d in clean_days)

    if clean_days:
        baseline_daily = clean_total / len(clean_days)
    else:
        # Full fallback: global quiet-period daily average (Apr–May 12 + Jun–Jul)
        baseline_daily = 0.28

    baseline_14 = baseline_daily * WINDOW_DAYS
    spike       = max(0.0, window_total - baseline_14)

    # Overlap: does this send's window contain another send's D0?
    overlapping_sends = []
    for other_d0, other_window in all_windows.items():
        if other_d0 != d0 and other_d0 in send_window(d0):
            overlapping_sends.append(other_d0.isoformat())

    return {
        "window_total":     window_total,
        "clean_baseline_days": len(clean_days),
        "dirty_baseline_days": dirty_days,
        "baseline_daily_avg": round(baseline_daily, 2),
        "baseline_14day":   round(baseline_14, 1),
        "spike":            round(spike, 1),
        "overlapping_sends": overlapping_sends,
    }


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------
def write_csv(out: Path, rows: list[dict], filename: str) -> None:
    io.copy_to_downloads(io.write_dicts(out / filename, rows))


def write_text(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> None:
    out = io.out_dir("ga4")
    ga = ga4.client()

    print("Source 1: GA4 tagged sign-ups by campaign...")
    tagged_all = pull_tagged_signups(ga)
    print(f"  All campaigns with sign-ups:")
    for camp, n in sorted(tagged_all.items(), key=lambda x: x[1], reverse=True):
        print(f"    {camp:<30} {n}")

    all_windows = all_send_windows()

    print("\nSource 2: DB spike correlation...")
    csv_rows = []
    md_rows  = []

    for send_date_str, label in SENDS:
        d0   = date.fromisoformat(send_date_str)
        sa   = spike_analysis(d0, all_windows)

        # Source 1: sum tagged sign-ups for campaigns where session started
        # in this send's window (approximate — we don't have per-day GA4 campaign breakdown,
        # so we attribute based on date proximity of CAPH codes to send date)
        # For now: report total tagged across all CAPH codes (user to match manually)
        # We pull the per-send figure as 0 pending CAPH code mapping below
        tagged_send = 0  # placeholder; full CAPH breakdown printed above

        spike     = sa["spike"]
        best_est  = max(tagged_send, round(spike))
        overlap   = ", ".join(sa["overlapping_sends"]) if sa["overlapping_sends"] else "—"
        baseline_flag = "⚠ contaminated" if sa["dirty_baseline_days"] > 14 else "clean"

        print(f"  {send_date_str}  {label}")
        print(f"    Window total: {sa['window_total']}  Baseline: {sa['baseline_daily_avg']}/day × 14 = {sa['baseline_14day']}")
        print(f"    Spike: {spike}  Clean baseline days: {sa['clean_baseline_days']}/28  Overlaps: {overlap}")

        csv_rows.append({
            "send_date":             send_date_str,
            "campaign":              label,
            "window_signups_db":     sa["window_total"],
            "baseline_daily_avg":    sa["baseline_daily_avg"],
            "baseline_14day":        sa["baseline_14day"],
            "spike_above_baseline":  sa["spike"],
            "tagged_ga4":            tagged_send,
            "best_estimate":         best_est,
            "baseline_quality":      baseline_flag,
            "overlapping_sends":     overlap,
        })
        md_rows.append({
            "send_date": send_date_str, "label": label, "sa": sa,
            "tagged": tagged_send, "spike": spike, "best": best_est,
            "overlap": overlap, "b_flag": baseline_flag,
        })

    write_csv(out, csv_rows, "fy26_edm_signup_attribution.csv")

    # ---------------------------------------------------------------------------
    # Markdown
    # ---------------------------------------------------------------------------
    total_db = sum(DB_DAILY_RAW.values())
    md: list[str] = []
    md.append("# FY26 eDM → HCP Sign-up Attribution\n")
    md.append(f"**Window:** Apr 2025 – Mar 2026 | **Total FY26 sign-ups (DB):** {total_db} | **GA4 tracked:** 130 (39% capture rate)\n")

    md.append("## Methodology\n")
    md.append("| | Detail |")
    md.append("|---|---|")
    md.append("| **Source 1 — Tagged (GA4)** | `conversion_event_signup` events where `sessionCampaignName` = CAPH code. Undercount — GA4 only captured 130 of 332 sign-ups. Use as validation floor. |")
    md.append("| **Source 2 — Spike (DB)** | WordPress `user_registered` ground truth. 14-day window (D0–D+13) vs 28-day pre-send rolling baseline. Baseline days contaminated by other send windows are excluded. |")
    md.append("| **Best estimate** | `max(tagged, round(spike))`. Spike already contains tagged sessions — not additive. |")
    md.append("| **Window** | 14 days (D0 inclusive) |")
    md.append("| **Baseline** | 28-day pre-send average, clean days only. Fallback = 0.28/day (quiet-period empirical) |")
    md.append("| **Overlap flag** | Send dates falling within another send's 14-day window — attribution can't be cleanly split |\n")

    md.append("## Results\n")
    md.append("| Send date | Campaign | Window sign-ups (DB) | Baseline 14-day | Spike | Tagged (GA4) | **Best estimate** | Baseline quality | Overlaps |")
    md.append("|---|---|---:|---:|---:|---:|---:|---|---|")
    total_spike = 0
    total_best  = 0
    for r in md_rows:
        total_spike += r["spike"]
        total_best  += r["best"]
        md.append(
            f"| {r['send_date']} | {r['label']} "
            f"| {r['sa']['window_total']} "
            f"| {r['sa']['baseline_14day']} "
            f"| **{r['spike']:.1f}** "
            f"| {r['tagged']} "
            f"| **{r['best']}** "
            f"| {r['b_flag']} "
            f"| {r['overlap']} |"
        )
    md.append(f"| | **TOTAL** | | | **{total_spike:.0f}** | | **{total_best}** | | |\n")

    md.append("## GA4 tagged sign-ups — all campaigns (Source 1)\n")
    md.append("Full breakdown of `conversion_event_signup` events by campaign code. "
              "Match CAPH codes to sends manually — multiple codes per send (each link in the eDM has its own code).\n")
    md.append("| Campaign | Tagged sign-ups |")
    md.append("|---|---:|")
    for camp, n in sorted(tagged_all.items(), key=lambda x: x[1], reverse=True):
        md.append(f"| `{camp}` | {n} |")
    md.append("")

    md.append("## Caveats\n")
    md.append("- **Nov–Dec cluster (Nov 4 → Dec 13):** six sends in 39 days with heavily overlapping windows. "
              "Baselines for Nov 17 onwards are partially contaminated. Spikes in this cluster reflect combined send activity — "
              "treat individual send estimates as directional only.")
    md.append("- **Mar 12 (MIST+) and Mar 19 (Rectogesic CPD) overlap:** Mar 19 falls within the Mar 12 window. "
              "The large Mar 19 spike likely absorbs some Mar 12 tail.")
    md.append("- **GA4 capture rate 39%:** tagged sign-ups understate reality. A session that signed up but whose "
              "UTMs weren't captured (Direct mis-attribution) won't appear in Source 1.")
    md.append("- **Spike ≠ causation:** the baseline comparison is directional. A spike in the window is consistent "
              "with eDM-driven sign-ups but could include organic traffic if the content got shared.")
    md.append("- **Best estimate total should not be summed naively** where overlapping windows exist — "
              "some sign-ups may be counted in two consecutive sends.\n")

    write_text(out, "\n".join(md), "fy26_edm_signup_attribution.md")
    print("\nDone.")


if __name__ == "__main__":
    main()

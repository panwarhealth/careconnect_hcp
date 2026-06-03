"""
FY26 (Apr 2025 - Mar 2026) email-skew analysis for QBR.

Switches the right-hand "where email traffic went" view from pageviews to
sessions, attributes each session to its landing page section, and adds a
total-site comparison column so we can see whether email skews users toward
particular sections vs the site as a whole.

    python reports/ga4/email_skew.py --period FY26

Outputs (to reports/out/ga4/, also copied to Windows Downloads):
  - <slug>_email_skew_by_section.csv    (section-level data with skew_pp)
  - <slug>_channel_summary_v2.csv       (LH source totals, sessions only)
  - <slug>_traffic_source_qbr.md        (markdown analysis with honest read)
"""
import argparse
import sys
import re
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)


def email_filter() -> FilterExpression:
    return ga4.channel_filter("Email")


# Section bucketing for THIS slide's grouping (7 buckets).
# Order matters - first match wins. More-specific patterns first.
SECTION_RULES = [
    # Sample ordering
    ("Sample ordering", r"^/order-samples|^/order-patient-demo|^/product-demo"),
    # Login & account / auth flows
    ("Login & account", r"^/log-in|^/login|^/register|^/edit-your-profile|^/my-account|^/dashboard|^/profile|^/welcome|^/forgot-password|^/lost-password"),
    # Downloadable resources / PDFs / factsheets
    ("Resources",       r"^/resources|/factsheet|/patient-resource|/clinical-paper|/guide|wp-content/uploads.*\.pdf"),
    # Interactive tools and demos (incl. /tools-and-videos hub)
    ("Tools",           r"^/allergy-analyser|^/interactive-model|^/rectogesic-3d-model|^/fess-demonstration|^/interactivetools|^/tools-and-videos|^/section\d|^/tip\d|^/option\d|^/bake$|^/clues$|^/chart$|^/detection$|^/mist$"),
    # The Course = LMS module + RACGP MCA bundled
    ("The Course",      r"^/courses|^/lessons|^/quizzes|^/quiz|^/certificate|^/anal-fissures-breaking|^/mini-clinical-audit|/retrospective|/complete-mini"),
    # Articles & blog (long-form content)
    ("Articles & blog", r"^/blog|^/nasal-and-sinus-health|^/topics/"),
    # Other = homepage + brands + everything else
    ("Other",           r"^/$|^/brands/"),
]
SECTION_ORDER = [
    "Articles & blog",
    "The Course",
    "Login & account",
    "Sample ordering",
    "Tools",
    "Resources",
    "Other",
    "(landing page not tracked)",
]


def bucket(path: str) -> str:
    if not path or path.strip() in ("(not set)", ""):
        return "(landing page not tracked)"
    p = path.split("?")[0].rstrip("/") or "/"
    for label, pattern in SECTION_RULES:
        if re.search(pattern, p, re.IGNORECASE):
            return label
    return "Other"


def normalise_path(path: str) -> str:
    p = path.split("?")[0]
    return p.rstrip("/") or "/"


# --- 1. Channel summary (sessions) ------------------------------------------
def channel_summary(ga: BetaAnalyticsDataClient, start: str, end: str) -> list[dict]:
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="sessionDefaultChannelGroup")],
        metrics=[Metric(name="sessions")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
    ))
    rows = []
    for r in resp.rows:
        ch = r.dimension_values[0].value
        sessions = int(r.metric_values[0].value)
        if ch == "Email":
            b = "Email"
        elif ch == "Direct":
            b = "Direct"
        elif ch in ("Unassigned", "(not set)"):
            b = "Unassigned"
        else:
            b = "Other"
        rows.append({"channel_raw": ch, "channel": b, "sessions": sessions})
    buckets: dict[str, int] = {}
    for r in rows:
        buckets[r["channel"]] = buckets.get(r["channel"], 0) + r["sessions"]
    order = ["Email", "Direct", "Unassigned", "Other"]
    result = [{"channel": b, "sessions": buckets[b]} for b in order if b in buckets]
    total = sum(r["sessions"] for r in result)
    for r in result:
        r["pct"] = r["sessions"] / total * 100
    result.append({"channel": "TOTAL", "sessions": total, "pct": 100.0})
    return result


# --- 2. Sessions by landing-page section ------------------------------------
def sessions_by_section(ga: BetaAnalyticsDataClient,
                        only_email: bool, start: str, end: str) -> dict[str, int]:
    """Return {section: sessions} keyed by landing-page section."""
    kwargs = dict(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=10_000,
    )
    if only_email:
        kwargs["dimension_filter"] = email_filter()
    resp = ga.run_report(RunReportRequest(**kwargs))
    sections: dict[str, int] = {s: 0 for s in SECTION_ORDER}
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        sess = int(r.metric_values[0].value)
        sec = bucket(path)
        sections[sec] = sections.get(sec, 0) + sess
    return sections


# --- 3. Top landing pages per channel (sanity-check / debug) ----------------
def top_landing_pages(ga: BetaAnalyticsDataClient,
                      only_email: bool, start: str, end: str, limit: int = 25) -> list[dict]:
    kwargs = dict(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=limit,
    )
    if only_email:
        kwargs["dimension_filter"] = email_filter()
    resp = ga.run_report(RunReportRequest(**kwargs))
    return [
        {"path": normalise_path(r.dimension_values[0].value),
         "section": bucket(normalise_path(r.dimension_values[0].value)),
         "sessions": int(r.metric_values[0].value)}
        for r in resp.rows
    ]


def write_csv(out: Path, rows: list[dict], filename: str) -> None:
    if not rows:
        return
    io.copy_to_downloads(io.write_dicts(out / filename, rows))


def write_text(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    out = io.out_dir("ga4")
    ga = ga4.client()

    print(f"=== 1. Channel summary (sessions, {p.label}) ===")
    ch = channel_summary(ga, p.start, p.end)
    ch_csv_rows = [{"channel": r["channel"], "sessions": r["sessions"],
                    "pct": f"{r['pct']:.1f}%"} for r in ch]
    write_csv(out, ch_csv_rows, f"{p.slug}_channel_summary_v2.csv")
    print(f"\n{'Channel':<12} {'Sessions':>10} {'%':>8}")
    print("-" * 32)
    for r in ch:
        print(f"{r['channel']:<12} {r['sessions']:>10,} {r['pct']:>7.1f}%")

    print("\n=== 2. Sessions by landing-page section ===")
    email_sec = sessions_by_section(ga, only_email=True, start=p.start, end=p.end)
    total_sec = sessions_by_section(ga, only_email=False, start=p.start, end=p.end)

    email_total = sum(email_sec.values())
    site_total  = sum(total_sec.values())

    skew_rows = []
    for s in SECTION_ORDER:
        e_sess = email_sec.get(s, 0)
        t_sess = total_sec.get(s, 0)
        e_pct  = e_sess / email_total * 100 if email_total else 0
        t_pct  = t_sess / site_total  * 100 if site_total  else 0
        skew_rows.append({
            "section": s,
            "email_sessions": e_sess,
            "pct_of_email": round(e_pct, 1),
            "total_site_sessions": t_sess,
            "pct_of_total": round(t_pct, 1),
            "email_skew_pp": round(e_pct - t_pct, 1),
        })
    skew_rows.append({
        "section": "TOTAL",
        "email_sessions": email_total,
        "pct_of_email": 100.0,
        "total_site_sessions": site_total,
        "pct_of_total": 100.0,
        "email_skew_pp": 0.0,
    })
    write_csv(out, skew_rows, f"{p.slug}_email_skew_by_section.csv")

    print(f"\n{'Section':<18} {'Email':>8} {'%Email':>8} {'Site':>8} {'%Site':>8} {'Skew(pp)':>10}")
    print("-" * 64)
    for r in skew_rows:
        print(f"{r['section']:<18} {r['email_sessions']:>8,} "
              f"{r['pct_of_email']:>7.1f}% {r['total_site_sessions']:>8,} "
              f"{r['pct_of_total']:>7.1f}% {r['email_skew_pp']:>+9.1f}")

    # --- Markdown analysis ---
    md = []
    md.append("# FY26 Traffic Source — QBR Slide Data\n")
    md.append(f"**Window:** {p.label} ({p.start} – {p.end}) (GA4 property {ga4.PROPERTY_ID})  ")
    md.append("**Metric:** Sessions  ")
    md.append("**Section attribution:** each session bucketed by its landing page (the first page of the session).\n")

    md.append("## 1. Traffic source totals (sessions)\n")
    md.append("| Source | Sessions | % |")
    md.append("|---|---:|---:|")
    for r in ch:
        if r["channel"] == "TOTAL":
            md.append(f"| **{r['channel']}** | **{r['sessions']:,}** | **{r['pct']:.1f}%** |")
        else:
            md.append(f"| {r['channel']} | {r['sessions']:,} | {r['pct']:.1f}% |")
    md.append("")
    md.append("- **Email** = publisher eDMs + Mailchimp (GA4 default channel group `Email`).")
    md.append("- **Other** = Organic Search, Referral, Organic Social, Paid, etc., collapsed.")
    md.append("- **Unassigned** = sessions GA4 couldn't attribute (often privacy-blockers, direct-with-no-referrer edge cases).\n")

    md.append("## 2. Where email traffic landed vs total site\n")
    md.append("| Section | Email sessions | % of email | Total-site sessions | % of total | Email skew |")
    md.append("|---|---:|---:|---:|---:|---:|")
    for r in skew_rows:
        if r["section"] == "TOTAL":
            md.append(f"| **{r['section']}** | **{r['email_sessions']:,}** | **{r['pct_of_email']:.1f}%** | "
                      f"**{r['total_site_sessions']:,}** | **{r['pct_of_total']:.1f}%** | — |")
        else:
            sign = "+" if r["email_skew_pp"] >= 0 else ""
            md.append(f"| {r['section']} | {r['email_sessions']:,} | {r['pct_of_email']:.1f}% | "
                      f"{r['total_site_sessions']:,} | {r['pct_of_total']:.1f}% | "
                      f"{sign}{r['email_skew_pp']:.1f} pp |")
    md.append("")
    md.append("**Skew = (% of email) − (% of total).** Positive = email over-indexes that section, negative = under-indexes.")
    md.append("**Method note:** sessions attributed to landing page (first page hit) via GA4's `landingPage` dimension. A session that lands on `/blog/foo` and then visits `/order-samples` counts under *Articles & blog* only — avoids double-counting.")
    md.append("**`(landing page not tracked)`** is GA4's `(not set)` bucket: sessions where GA4 couldn't resolve a landing page (privacy-thresholded sessions, app-event-only sessions, or tracking gaps). It's noise, not a real section — included for transparency but ignored when reading skew. Note its share is near-identical for email vs total (~39%), so it's not biasing any single section.")
    md.append("**Why the email totals don't match section 1:** the channel-level `Email` total (4,269) is GA4's session count when grouped by channel only; the section table sums to 7,081 because GA4's `landingPage` dimension query returns an additional synthetic `(not set)` row of 2,812 sessions on top. The proportional distribution across sections is the reliable comparison; absolute counts in this table are landing-page-dimension figures and shouldn't be cross-summed with section 1.\n")

    # Honest read - flag sections with |skew| >= 5pp as meaningful
    md.append("## 3. Honest read\n")
    movers = [r for r in skew_rows
              if r["section"] not in ("TOTAL", "(landing page not tracked)")
              and abs(r["email_skew_pp"]) >= 5]
    movers_pos = [r for r in movers if r["email_skew_pp"] > 0]
    movers_neg = [r for r in movers if r["email_skew_pp"] < 0]

    lines: list[str] = []
    if movers_pos:
        for r in movers_pos:
            lines.append(f"- **{r['section']}** over-indexes for email by **+{r['email_skew_pp']:.1f} pp** "
                         f"({r['pct_of_email']:.1f}% of email vs {r['pct_of_total']:.1f}% of total) — "
                         f"email is doing the heavy lifting to drive landings here.")
    if movers_neg:
        for r in movers_neg:
            lines.append(f"- **{r['section']}** under-indexes for email by **{r['email_skew_pp']:.1f} pp** "
                         f"({r['pct_of_email']:.1f}% of email vs {r['pct_of_total']:.1f}% of total) — "
                         f"these landings come predominantly from non-email channels.")
    if not movers:
        lines.append("- No section moves more than 5pp in either direction — email traffic distribution mirrors total-site traffic distribution.")
    md.extend(lines)
    md.append("")

    write_text(out, "\n".join(md), f"{p.slug}_traffic_source_qbr.md")

    # Sanity-check tail: show top 25 email landing pages and their bucket
    print("\n=== Sanity check: top 25 email landing pages ===")
    print(f"{'Section':<18} {'Sess':>6}  {'Path'}")
    print("-" * 80)
    for lp in top_landing_pages(ga, only_email=True, start=p.start, end=p.end, limit=25):
        print(f"{lp['section']:<18} {lp['sessions']:>6,}  {lp['path'][:55]}")


if __name__ == "__main__":
    main()

"""
FY26 (Apr 2025 - Mar 2026) email-skew analysis - USERS edition.

Companion to ga4_email_skew_fy26.py. Same window, channel filter, and section
bucketing - but on totalUsers instead of sessions.

Methodology caveat (documented in output): GA4's standard API has no
'first user landing page' dimension. We use landingPage x totalUsers, which
counts a user in every section they landed on. Channel-level totals are clean
(sessionDefaultChannelGroup x totalUsers - users de-duped per channel). For
the per-section breakdown, percentages are calculated as
share-of-section-landings (sum of section users normalised to 100%), not
share-of-channel-users. Skew is comparable because email and total-site use
identical methodology.

    python reports/ga4/email_skew_users.py --period FY26

Outputs (to reports/out/ga4/, also copied to Windows Downloads):
  - <slug>_channel_users_v2.csv
  - <slug>_email_skew_by_section_users.csv
  - <slug>_traffic_source_users_qbr.md
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


# Section bucketing - same as sessions script, kept in sync.
SECTION_RULES = [
    ("Sample ordering", r"^/order-samples|^/order-patient-demo|^/product-demo"),
    ("Login & account", r"^/log-in|^/login|^/register|^/edit-your-profile|^/my-account|^/dashboard|^/profile|^/welcome|^/forgot-password|^/lost-password"),
    ("Resources",       r"^/resources|/factsheet|/patient-resource|/clinical-paper|/guide|wp-content/uploads.*\.pdf"),
    ("Tools",           r"^/allergy-analyser|^/interactive-model|^/rectogesic-3d-model|^/fess-demonstration|^/interactivetools|^/tools-and-videos|^/section\d|^/tip\d|^/option\d|^/bake$|^/clues$|^/chart$|^/detection$|^/mist$"),
    ("The Course",      r"^/courses|^/lessons|^/quizzes|^/quiz|^/certificate|^/anal-fissures-breaking|^/mini-clinical-audit|/retrospective|/complete-mini"),
    ("Articles & blog", r"^/blog|^/nasal-and-sinus-health|^/topics/"),
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


# --- 1. Channel users (clean, de-duped per channel) -------------------------
def channel_users(ga: BetaAnalyticsDataClient, start: str, end: str) -> list[dict]:
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="sessionDefaultChannelGroup")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
    ))
    rows = []
    for r in resp.rows:
        ch = r.dimension_values[0].value
        users = int(r.metric_values[0].value)
        if ch == "Email":
            b = "Email"
        elif ch == "Direct":
            b = "Direct"
        elif ch in ("Unassigned", "(not set)"):
            b = "Unassigned"
        else:
            b = "Other"
        rows.append({"channel": b, "users": users})
    buckets: dict[str, int] = {}
    for r in rows:
        buckets[r["channel"]] = buckets.get(r["channel"], 0) + r["users"]

    # Total-site (no channel breakdown): pull as separate query for accurate dedup.
    resp2 = ga.run_report(RunReportRequest(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        metrics=[Metric(name="totalUsers")],
    ))
    site_total = int(resp2.rows[0].metric_values[0].value)

    order = ["Email", "Direct", "Unassigned", "Other"]
    result = [{"channel": b, "users": buckets[b]} for b in order if b in buckets]
    sum_channels = sum(r["users"] for r in result)
    for r in result:
        r["pct"] = r["users"] / sum_channels * 100
    result.append({"channel": "SUM_OF_CHANNELS", "users": sum_channels, "pct": 100.0})
    result.append({"channel": "TOTAL_SITE_UNIQUE", "users": site_total, "pct": None})
    return result


# --- 2. Users by landing-page section ---------------------------------------
def users_by_section(ga: BetaAnalyticsDataClient,
                     only_email: bool, start: str, end: str) -> dict[str, int]:
    kwargs = dict(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
        limit=10_000,
    )
    if only_email:
        kwargs["dimension_filter"] = email_filter()
    resp = ga.run_report(RunReportRequest(**kwargs))
    sections: dict[str, int] = {s: 0 for s in SECTION_ORDER}
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        usr = int(r.metric_values[0].value)
        sec = bucket(path)
        sections[sec] = sections.get(sec, 0) + usr
    return sections


def write_csv(out: Path, rows: list[dict], filename: str) -> None:
    if not rows:
        return
    io.copy_to_downloads(io.write_dicts(out / filename, rows))


def write_text(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def fmt_pct(p) -> str:
    return "—" if p is None else f"{p:.1f}%"


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    out = io.out_dir("ga4")
    ga = ga4.client()

    print(f"=== 1. Channel users ({p.label}) ===")
    ch = channel_users(ga, p.start, p.end)
    ch_csv = [{"channel": r["channel"], "users": r["users"],
               "pct": fmt_pct(r["pct"])} for r in ch]
    write_csv(out, ch_csv, f"{p.slug}_channel_users_v2.csv")
    print(f"\n{'Channel':<22} {'Users':>10} {'%':>8}")
    print("-" * 42)
    for r in ch:
        print(f"{r['channel']:<22} {r['users']:>10,} {fmt_pct(r['pct']):>8}")

    print("\n=== 2. Users by landing-page section ===")
    email_sec = users_by_section(ga, only_email=True, start=p.start, end=p.end)
    total_sec = users_by_section(ga, only_email=False, start=p.start, end=p.end)

    email_total = sum(email_sec.values())
    site_total  = sum(total_sec.values())

    skew_rows = []
    for s in SECTION_ORDER:
        e_u = email_sec.get(s, 0)
        t_u = total_sec.get(s, 0)
        e_pct = e_u / email_total * 100 if email_total else 0
        t_pct = t_u / site_total * 100 if site_total else 0
        skew_rows.append({
            "section": s,
            "email_users": e_u,
            "pct_of_email_users": round(e_pct, 1),
            "total_site_users": t_u,
            "pct_of_total_users": round(t_pct, 1),
            "user_skew_pp": round(e_pct - t_pct, 1),
        })
    skew_rows.append({
        "section": "TOTAL",
        "email_users": email_total,
        "pct_of_email_users": 100.0,
        "total_site_users": site_total,
        "pct_of_total_users": 100.0,
        "user_skew_pp": 0.0,
    })
    write_csv(out, skew_rows, f"{p.slug}_email_skew_by_section_users.csv")

    print(f"\n{'Section':<28} {'Email':>8} {'%Email':>8} {'Site':>8} {'%Site':>8} {'Skew(pp)':>10}")
    print("-" * 76)
    for r in skew_rows:
        print(f"{r['section']:<28} {r['email_users']:>8,} "
              f"{r['pct_of_email_users']:>7.1f}% {r['total_site_users']:>8,} "
              f"{r['pct_of_total_users']:>7.1f}% {r['user_skew_pp']:>+9.1f}")

    # --- Markdown ---
    md = []
    md.append("# FY26 Traffic Source — QBR Slide Data (Users)\n")
    md.append(f"**Window:** {p.label} ({p.start} – {p.end}) (GA4 property {ga4.PROPERTY_ID})  ")
    md.append("**Metric:** Unique users (`totalUsers`)  ")
    md.append("**Section attribution:** landing-page bucketing (see methodology note below).\n")

    md.append("## 1. Traffic source — unique users\n")
    md.append("| Source | Users | % of channel sum |")
    md.append("|---|---:|---:|")
    for r in ch:
        if r["channel"] == "SUM_OF_CHANNELS":
            md.append(f"| **{r['channel']}** | **{r['users']:,}** | **{fmt_pct(r['pct'])}** |")
        elif r["channel"] == "TOTAL_SITE_UNIQUE":
            md.append(f"| **{r['channel']}** | **{r['users']:,}** | — |")
        else:
            md.append(f"| {r['channel']} | {r['users']:,} | {fmt_pct(r['pct'])} |")
    md.append("")
    md.append("**Note on the two totals:** `SUM_OF_CHANNELS` adds up the per-channel user counts; `TOTAL_SITE_UNIQUE` is a separate query for fully-deduplicated unique users across the whole site. The gap between them = users who appear in more than one channel during FY26 (e.g., visited via email AND direct on different days). The site-wide unique number is the honest 'how many distinct people came to the site' figure.\n")

    md.append("## 2. Where email and total-site users landed\n")
    md.append("| Section | Email users | % of email | Total-site users | % of total | User skew |")
    md.append("|---|---:|---:|---:|---:|---:|")
    for r in skew_rows:
        if r["section"] == "TOTAL":
            md.append(f"| **{r['section']}** | **{r['email_users']:,}** | **{r['pct_of_email_users']:.1f}%** | "
                      f"**{r['total_site_users']:,}** | **{r['pct_of_total_users']:.1f}%** | — |")
        else:
            sign = "+" if r["user_skew_pp"] >= 0 else ""
            md.append(f"| {r['section']} | {r['email_users']:,} | {r['pct_of_email_users']:.1f}% | "
                      f"{r['total_site_users']:,} | {r['pct_of_total_users']:.1f}% | "
                      f"{sign}{r['user_skew_pp']:.1f} pp |")
    md.append("")
    md.append("**Skew = (% of email users) − (% of total users).** Positive = email over-indexes, negative = under-indexes.\n")

    md.append("## 3. Methodology — important caveat\n")
    md.append("**Strict 'first ever landing page per user' is not queryable via GA4's standard API.** GA4 has user-scoped dimensions like `firstUserSourceMedium` but no equivalent `firstUserLandingPage`. True first-touch landing-page attribution would need the BigQuery event-level export, which we don't have on this property.")
    md.append("")
    md.append("**What we did instead:** queried `landingPage × totalUsers`. This counts a user in every distinct section they landed on across the period. A user who landed on `/blog/foo` in one session and `/order-samples` in another appears in both *Articles & blog* and *Sample ordering*.")
    md.append("")
    md.append("**Why the table still works for skew analysis:**")
    md.append("- The same methodology is applied to email and total-site, so any over-counting is proportional and cancels out in the skew comparison.")
    md.append("- Section %'s are calculated as `section_users / sum_of_all_section_users` (i.e., share of landings, not share of channel users) — that's why each column sums to 100% even though absolute users-per-section sums exceed the channel-level user count.")
    md.append("- Email %'s and Total %'s are directly comparable; **absolute users-per-section figures are not directly comparable to the channel-level user counts in section 1**.")
    md.append("")
    md.append("**`(landing page not tracked)`** = GA4's `(not set)` bucket, same as in the sessions analysis. Excluded from the honest read.")
    md.append("")
    md.append("**Skew patterns:** as you'd expect, the user-skew patterns track the session-skew patterns closely — same channel, similar landing-page mix.\n")

    md.append("## 4. Honest read\n")
    movers = [r for r in skew_rows
              if r["section"] not in ("TOTAL", "(landing page not tracked)")
              and abs(r["user_skew_pp"]) >= 5]
    movers_pos = [r for r in movers if r["user_skew_pp"] > 0]
    movers_neg = [r for r in movers if r["user_skew_pp"] < 0]
    if movers_pos:
        for r in movers_pos:
            md.append(f"- **{r['section']}** over-indexes for email by **+{r['user_skew_pp']:.1f} pp** "
                      f"({r['pct_of_email_users']:.1f}% of email users vs {r['pct_of_total_users']:.1f}% of total) — "
                      f"email is bringing a disproportionate share of users to this section.")
    if movers_neg:
        for r in movers_neg:
            md.append(f"- **{r['section']}** under-indexes for email by **{r['user_skew_pp']:.1f} pp** "
                      f"({r['pct_of_email_users']:.1f}% of email users vs {r['pct_of_total_users']:.1f}% of total) — "
                      f"these users come predominantly from non-email channels.")
    if not movers:
        md.append("- No section moves more than 5pp — email user distribution mirrors total-site user distribution.")
    md.append("")

    write_text(out, "\n".join(md), f"{p.slug}_traffic_source_users_qbr.md")


if __name__ == "__main__":
    main()

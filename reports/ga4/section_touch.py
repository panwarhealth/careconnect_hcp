"""
FY26 (Apr 2025 - Mar 2026) section-TOUCH analysis for QBR.

Methodology shift from landing-page bucketing: every session/user that touched
ANY page in a section counts for that section, regardless of where they landed.
Sessions/users are double-counted across sections by design.

Per-section query: pagePath regex filter (event-scoped) combined with
sessions / totalUsers metrics (session/user-scoped). GA4 returns sessions/users
where AT LEAST ONE event matched the regex - i.e. touched the section.

"Other" is computed as NOT(union of all section regexes) so it captures
homepage, brand pages, and anything not explicitly bucketed - no hand-curated
inverse list needed.

Outputs (slug reflects the chosen period, e.g. fy26 / fy26q4):
  - <slug>_section_touch.csv
  - <slug>_traffic_source_section_touch_qbr.md

Usage: python reports/ga4/section_touch.py [--period FY26 | --start ... --end ...]
"""
import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Filter, FilterExpression, FilterExpressionList,
    Metric, RunReportRequest,
)

PROPERTY_ID = ga4.PROPERTY_ID
# Date window — set from the resolved period in main(); functions read these globals.
START, END  = "2025-04-01", "2026-03-31"
PERIOD_LABEL = "Apr 2025 – Mar 2026"


# -----------------------------------------------------------------------------
# Section definitions: name -> (human-readable URL patterns, GA4 PARTIAL_REGEXP)
# Order matters only for documentation; queries are independent.
# -----------------------------------------------------------------------------
SECTIONS: list[tuple[str, list[str], str]] = [
    (
        "Articles & blog",
        ["/blog/*", "/nasal-and-sinus-health/*", "/topics/*"],
        r"^/(blog|nasal-and-sinus-health|topics)(/|$)",
    ),
    (
        "Resources",
        ["/resources/*", "/factsheet*", "/patient-resource*", "/clinical-paper*",
         "/guide*", "/wp-content/uploads/*.pdf"],
        r"^/(resources|factsheet|patient-resource|clinical-paper|guide)|wp-content/uploads.*\.pdf",
    ),
    (
        "The Course (module + MCA)",
        ["/courses/*", "/lessons/*", "/quizzes/*", "/quiz/*", "/certificate*",
         "/anal-fissures-breaking-the-cycle-and-the-stigma*",
         "/mini-clinical-audit/*", "/retrospective*", "/complete-mini*"],
        r"^/(courses|lessons|quizzes|quiz|certificate|anal-fissures-breaking|mini-clinical-audit|retrospective|complete-mini)",
    ),
    (
        "Login & Signup",
        ["/log-in", "/login", "/register", "/edit-your-profile", "/my-account",
         "/dashboard", "/profile", "/welcome", "/forgot-password", "/lost-password"],
        r"^/(log-in|login|register|edit-your-profile|my-account|dashboard|profile|welcome|forgot-password|lost-password)(/|$)",
    ),
    (
        "Sample ordering",
        ["/order-samples", "/order-patient-demo", "/product-demo*"],
        r"^/(order-samples|order-patient-demo|product-demo)",
    ),
    (
        "Tools",
        ["/allergy-analyser-tool", "/interactive-model*", "/rectogesic-3d-model",
         "/fess-demonstration", "/interactivetools/*", "/tools-and-videos",
         "/section1*, /section2*, ... (interactive tool steps)",
         "/tip1, /tip2, ... (tool tips)", "/option1, /option2, ...",
         "/bake, /clues, /chart, /detection, /mist (allergy analyser sub-pages)"],
        r"^/(allergy-analyser|interactive-model|rectogesic-3d-model|fess-demonstration|interactivetools|tools-and-videos|section\d|tip\d|option\d|bake|clues|chart|detection|mist)",
    ),
]
SECTION_NAMES = [s[0] for s in SECTIONS]
OTHER_NAME    = "Other (homepage + brands + misc)"


# -----------------------------------------------------------------------------
# Filter builders
# -----------------------------------------------------------------------------
def page_filter(regex: str) -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name="pagePath",
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
            value=regex,
        ),
    ))


def email_filter() -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name="sessionDefaultChannelGroup",
        string_filter=Filter.StringFilter(value="Email"),
    ))


def and_(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


def or_(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(or_group=FilterExpressionList(expressions=list(exprs)))


def not_(expr: FilterExpression) -> FilterExpression:
    return FilterExpression(not_expression=expr)


def other_filter() -> FilterExpression:
    """NOT (union of all named section regexes) - captures homepage, brands, misc."""
    return not_(or_(*[page_filter(s[2]) for s in SECTIONS]))


# -----------------------------------------------------------------------------
# Query: sessions + users where filter matches at least one event in session/user
# -----------------------------------------------------------------------------
def section_metrics(ga: BetaAnalyticsDataClient,
                    section_filter: FilterExpression,
                    only_email: bool) -> tuple[int, int]:
    if only_email:
        flt = and_(section_filter, email_filter())
    else:
        flt = section_filter
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=flt,
    ))
    if not resp.rows:
        return 0, 0
    sess = int(resp.rows[0].metric_values[0].value)
    usrs = int(resp.rows[0].metric_values[1].value)
    return sess, usrs


def channel_totals(ga: BetaAnalyticsDataClient) -> tuple[int, int, int, int]:
    """(email_sessions, email_users, total_sessions, total_users) at channel level."""
    e_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=email_filter(),
    ))
    t_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
    ))
    return (
        int(e_resp.rows[0].metric_values[0].value),
        int(e_resp.rows[0].metric_values[1].value),
        int(t_resp.rows[0].metric_values[0].value),
        int(t_resp.rows[0].metric_values[1].value),
    )


def write_csv(rows: list[dict], filename: str) -> None:
    if not rows:
        return
    path = io.write_dicts(io.out_dir() / filename, rows)
    io.copy_to_downloads(path)


def write_text(content: str, filename: str) -> None:
    path = io.out_dir() / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)

    global START, END, PERIOD_LABEL
    START, END = p.start, p.end
    PERIOD_LABEL = p.label

    ga = ga4.client()

    print(f"=== Channel-level totals ({p.label}) ===")
    e_sess, e_usrs, t_sess, t_usrs = channel_totals(ga)
    print(f"  Email:      {e_sess:>6,} sessions   {e_usrs:>6,} users")
    print(f"  Total site: {t_sess:>6,} sessions   {t_usrs:>6,} users")
    print(f"  Email share of site sessions: {e_sess/t_sess*100:.1f}%")
    print(f"  Email share of site users:    {e_usrs/t_usrs*100:.1f}%\n")

    rows: list[dict] = []
    print("=== Section touch counts ===")
    print(f"{'Section':<32} {'E.Sess':>7} {'E.Usrs':>7} {'T.Sess':>7} {'T.Usrs':>7} {'E%Sect':>7}")
    print("-" * 76)

    for name, _patterns, regex in SECTIONS:
        flt = page_filter(regex)
        es, eu = section_metrics(ga, flt, only_email=True)
        ts, tu = section_metrics(ga, flt, only_email=False)
        share = es / ts * 100 if ts else 0
        rows.append({
            "section": name,
            "email_sessions": es,
            "email_users": eu,
            "total_sessions": ts,
            "total_users": tu,
            "email_share_of_section_pct": round(share, 1),
        })
        print(f"{name:<32} {es:>7,} {eu:>7,} {ts:>7,} {tu:>7,} {share:>6.1f}%")

    # Other = NOT(union of all section regexes)
    o_flt = other_filter()
    es, eu = section_metrics(ga, o_flt, only_email=True)
    ts, tu = section_metrics(ga, o_flt, only_email=False)
    share = es / ts * 100 if ts else 0
    rows.append({
        "section": OTHER_NAME,
        "email_sessions": es,
        "email_users": eu,
        "total_sessions": ts,
        "total_users": tu,
        "email_share_of_section_pct": round(share, 1),
    })
    print(f"{OTHER_NAME:<32} {es:>7,} {eu:>7,} {ts:>7,} {tu:>7,} {share:>6.1f}%")

    # Channel-level baseline row for reference
    rows.append({
        "section": "[ALL TRAFFIC - channel baseline]",
        "email_sessions": e_sess,
        "email_users": e_usrs,
        "total_sessions": t_sess,
        "total_users": t_usrs,
        "email_share_of_section_pct": round(e_sess / t_sess * 100, 1),
    })

    write_csv(rows, f"{p.slug}_section_touch.csv")

    baseline_pct = e_sess / t_sess * 100

    # --- Markdown ---
    md: list[str] = []
    md.append(f"# {p.label} Section Touch Activity — QBR Slide Data\n")
    md.append(f"**Window:** {PERIOD_LABEL} ({START} – {END}; GA4 property {PROPERTY_ID})  ")
    md.append("**Metrics:** Sessions (`sessions`), unique users (`totalUsers`)  ")
    md.append("**Methodology:** any session/user that touched at least one page in a section counts for that section, regardless of landing page or navigation path.\n")

    md.append("## URL patterns per section\n")
    md.append("Each section is defined by a `pagePath` regex applied to all events in the session. A session that hits multiple sections is counted in each.\n")
    for name, patterns, regex in SECTIONS:
        md.append(f"### {name}")
        for pat in patterns:
            md.append(f"- `{pat}`")
        md.append(f"- *Regex used:* `{regex}`")
        md.append("")
    md.append(f"### {OTHER_NAME}")
    md.append("- Catch-all: any pagePath that does **not** match any of the regexes above.")
    md.append("- In practice this is dominated by the homepage `/`, brand pages `/brands/*`, and assorted long-tail URLs (e.g. `/tools-and-videos` was moved into Tools so it's not here, but pages like `/about-careconnect`, `/contact`, EDM landing campaigns without a section-specific URL, etc. fall here).")
    md.append("- *Defined as:* `NOT (Articles & blog OR Resources OR The Course OR Login & Signup OR Sample ordering OR Tools)`\n")

    md.append("### Ambiguous calls — flagged\n")
    md.append("- **`/allergy-analyser-tool` and `/fess-demonstration`** → put in **Tools** (interactive widgets), not Resources. They're products users *use*, not documents users *download*.")
    md.append("- **`/anal-fissures-breaking-the-cycle-and-the-stigma-landing-page`** → put in **The Course (module + MCA)**. It's the marketing landing page for the CPD module, not a blog post. Reasonable people could argue it belongs in Articles & blog, but it links directly into `/courses/`, so we kept it with the course.")
    md.append("- **`/tools-and-videos`** → put in **Tools** (it's the hub page that lists the interactive tools).")
    md.append("- **`/nasal-and-sinus-health`** → kept in **Articles & blog**. It's standalone long-form clinical content, but reads like a blog article, not a tool or resource.")
    md.append("- **`/wp-content/uploads/*.pdf`** → counted in **Resources** because that's where downloadable factsheets and clinical papers live. Caveat: GA4 only tracks PDF page views if the JS fires before the file opens — depending on browser/setup, PDF traffic may be undercounted.\n")

    md.append("## Section-touch activity\n")
    md.append("| Section | Email sessions | Email users | Total-site sessions | Total-site users | Email share of section |")
    md.append("|---|---:|---:|---:|---:|---:|")
    for r in rows[:-1]:  # skip channel-baseline row in main table
        md.append(f"| {r['section']} | {r['email_sessions']:,} | {r['email_users']:,} | "
                  f"{r['total_sessions']:,} | {r['total_users']:,} | "
                  f"{r['email_share_of_section_pct']:.1f}% |")
    md.append(f"| **[All traffic — channel baseline]** | **{e_sess:,}** | **{e_usrs:,}** | "
              f"**{t_sess:,}** | **{t_usrs:,}** | **{e_sess/t_sess*100:.1f}%** |")
    md.append("")

    md.append("**Important — totals will exceed channel-level counts.** A session that touches both `/blog/foo` and `/order-samples` is counted in BOTH *Articles & blog* AND *Sample ordering*. This is by design: the table shows section activity, not session distribution. Don't sum the section rows.\n")
    md.append(f"**Email share of section** = `email_sessions / total_sessions` for that section. It tells you how reliant each section is on email as a traffic source. The channel baseline is **{baseline_pct:.1f}%** (email's share of all site sessions in this query), so anything above this means email is over-represented in that section's activity, anything below means it's under-represented.\n")

    # Honest read - find sections meaningfully above/below the email-share baseline
    movers = []
    for r in rows[:-1]:
        delta = r["email_share_of_section_pct"] - baseline_pct
        if abs(delta) >= 5:
            movers.append((r, delta))

    md.append("## Honest read\n")
    md.append(f"Site-wide baseline: **{baseline_pct:.1f}% of sessions are email**. Sections meaningfully above/below this baseline (>=5pp difference):\n")
    if not movers:
        md.append("- No section is more than 5pp away from the baseline — email's contribution is roughly even across the site.")
    else:
        for r, delta in movers:
            direction = "over-relies" if delta > 0 else "under-relies"
            md.append(f"- **{r['section']}** {direction} on email — {r['email_share_of_section_pct']:.1f}% of section sessions are email "
                      f"(baseline {baseline_pct:.1f}%, {'+' if delta>0 else ''}{delta:.1f} pp).")
    md.append("")

    write_text("\n".join(md), f"{p.slug}_traffic_source_section_touch_qbr.md")


if __name__ == "__main__":
    main()

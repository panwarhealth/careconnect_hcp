"""
FY26 (Apr 2025 – Mar 2026) section activity — both methodologies side-by-side.

Section-TOUCH: any session that touched ≥1 page in a section counts for it.
  Sessions touching multiple sections are counted in each — totals exceed
  channel baseline by design.

Landing-page: each session bucketed into exactly one section by first page.
  Sections sum to 100% (including the (not set) row).

Also: top-10 pages by GA4 file_download event count — shows where factsheet
downloads are being triggered from.

Outputs (slug reflects the chosen period, e.g. fy26 / fy26q4):
  - <slug>_section_combined.csv
  - <slug>_section_combined.md

Usage: python reports/ga4/section_combined.py [--period FY26 | --start ... --end ...]
"""
import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, FilterExpressionList,
    Metric, OrderBy, RunReportRequest,
)

PROPERTY_ID = ga4.PROPERTY_ID
# Date window — set from the resolved period in main(); functions read these globals.
START, END  = "2025-04-01", "2026-03-31"
PERIOD_LABEL = "Apr 2025 – Mar 2026"


# ---------------------------------------------------------------------------
# Section definitions.
# Each entry: (label, doc_patterns, ga4_touch_regex, python_landing_regex)
#
# ga4_touch_regex: PARTIAL_REGEXP applied to pagePath in the GA4 API.
#   Identifies sessions that touched the section (any page, not just landing).
#
# python_landing_regex: re.search() applied to normalised landingPage paths.
#   Used to bucket landing-page query rows in Python. First match wins.
#   More specific terminators on short words (bake, clues, etc.) to avoid
#   false positives against unrelated slugs.
# ---------------------------------------------------------------------------
SECTIONS: list[tuple[str, list[str], str, str]] = [
    (
        "Articles & blog",
        ["/blog/*", "/nasal-and-sinus-health/*", "/topics/*"],
        r"^/(blog|nasal-and-sinus-health|topics)(/|$)",
        r"(?i)^/(blog|nasal-and-sinus-health|topics)(/|$)",
    ),
    (
        "Resources / Factsheets",
        ["/resources/*", "/wp-content/uploads/*.pdf",
         "/factsheet*", "/patient-resource*", "/clinical-paper*", "/guide*"],
        r"^/(resources|factsheet|patient-resource|clinical-paper|guide)|^/wp-content/uploads.*\.pdf",
        r"^/(resources|factsheet|patient-resource|clinical-paper|guide)|^/wp-content/uploads.*\.pdf",
    ),
    (
        "Mini Clinical Audit Course",
        ["/courses/*", "/lessons/*", "/quizzes/*", "/quiz/*", "/certificate*",
         "/anal-fissures-breaking-the-cycle-and-the-stigma*",
         "/mini-clinical-audit/*", "/retrospective*", "/complete-mini*",
         "/pre-learning-survey*", "/post-learning-survey*"],
        r"^/(courses|lessons|quizzes|quiz|certificate|anal-fissures-breaking|mini-clinical-audit|retrospective|complete-mini|pre-learning-survey|pre-survey|post-learning-survey)",
        r"^/(courses|lessons|quizzes|quiz|certificate|anal-fissures-breaking|mini-clinical-audit|retrospective|complete-mini|pre-learning-survey|pre-survey|post-learning-survey)",
    ),
    (
        "Login & Signup",
        ["/log-in", "/login", "/register", "/edit-your-profile", "/my-account",
         "/dashboard", "/profile", "/welcome", "/forgot-password", "/lost-password"],
        r"^/(log-in|login|register|edit-your-profile|my-account|dashboard|profile|welcome|forgot-password|lost-password)(/|$)",
        r"^/(log-in|login|register|edit-your-profile|my-account|dashboard|profile|welcome|forgot-password|lost-password)(/|$|\.|-)",
    ),
    (
        "Sample pages",
        ["/order-samples", "/order-patient-demo*", "/product-demo*"],
        r"^/(order-samples|order-patient-demo|product-demo)",
        r"^/(order-samples|order-patient-demo|product-demo|ordersamples)",
    ),
    (
        "Tools & Videos",
        ["/allergy-analyser*", "/rectogesic-3d-model", "/fess-demonstration",
         "/interactive-model*", "/interactivetools/*", "/tools-and-videos",
         "/section1-4, /sec1-4 (analyser steps)",
         "/tip1-2, /option1-2 (analyser interaction pages)",
         "/bake, /clues, /chart, /detection, /mist (analyser sub-pages)",
         "/videos"],
        r"^/(allergy-analyser|interactive-model|rectogesic-3d-model|fess-demonstration|interactivetools|tools-and-videos|section\d|sec\d|tip\d|option\d|bake|clues|chart|detection|mist|videos)",
        r"^/(allergy-analyser|interactive-model|rectogesic-3d-model|fess-demonstration|interactivetools|tools-and-videos|section\d|sec\d|tip\d|option\d|bake(/|$)|clues(/|$)|chart(/|$)|detection(/|$)|mist(/|$)|videos(/|$))",
    ),
]

OTHER_LABEL   = "Other (homepage + brands + misc)"
NOT_SET_LABEL = "(landing page not tracked)"


# ---------------------------------------------------------------------------
# Filter builders
# ---------------------------------------------------------------------------
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


def event_name_exact(name: str) -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name="eventName",
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.EXACT,
            value=name,
        ),
    ))


def and_(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


def or_(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(or_group=FilterExpressionList(expressions=list(exprs)))


def not_(expr: FilterExpression) -> FilterExpression:
    return FilterExpression(not_expression=expr)


def other_touch_filter() -> FilterExpression:
    """NOT(union of all section pagePath regexes) — homepage, brands, misc."""
    return not_(or_(*[page_filter(s[2]) for s in SECTIONS]))


# ---------------------------------------------------------------------------
# Section-touch query
# Returns (sessions, users) for sessions where ≥1 event matched the filter.
# ---------------------------------------------------------------------------
def touch_metrics(
    ga: BetaAnalyticsDataClient,
    section_filter: FilterExpression,
    only_email: bool,
) -> tuple[int, int]:
    flt = and_(section_filter, email_filter()) if only_email else section_filter
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=flt,
    ))
    if not resp.rows:
        return 0, 0
    return int(resp.rows[0].metric_values[0].value), int(resp.rows[0].metric_values[1].value)


# ---------------------------------------------------------------------------
# Landing-page query — pull all landingPage rows, bucket in Python
# ---------------------------------------------------------------------------
def normalise(path: str) -> str:
    return path.split("?")[0].rstrip("/") or "/"


def bucket_landing(path: str) -> str:
    if not path or path.strip() in ("(not set)", ""):
        return NOT_SET_LABEL
    p = normalise(path)
    for label, _, _, py_regex in SECTIONS:
        if re.search(py_regex, p):
            return label
    return OTHER_LABEL


def landing_query(ga: BetaAnalyticsDataClient, only_email: bool) -> dict[str, tuple[int, int]]:
    """Pull all landingPage rows and bucket them. Returns {label: (sessions, users)}."""
    kwargs: dict = dict(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=10_000,
    )
    if only_email:
        kwargs["dimension_filter"] = email_filter()
    resp = ga.run_report(RunReportRequest(**kwargs))
    totals: dict[str, tuple[int, int]] = {}
    for row in resp.rows:
        path = normalise(row.dimension_values[0].value)
        s = int(row.metric_values[0].value)
        u = int(row.metric_values[1].value)
        lbl = bucket_landing(path)
        prev_s, prev_u = totals.get(lbl, (0, 0))
        totals[lbl] = (prev_s + s, prev_u + u)
    return totals


# ---------------------------------------------------------------------------
# Channel-level baseline — no page filter, no dimension
# ---------------------------------------------------------------------------
def channel_totals(ga: BetaAnalyticsDataClient) -> tuple[int, int, int, int]:
    e = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=email_filter(),
    ))
    t = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
    ))
    return (
        int(e.rows[0].metric_values[0].value),
        int(e.rows[0].metric_values[1].value),
        int(t.rows[0].metric_values[0].value),
        int(t.rows[0].metric_values[1].value),
    )


# ---------------------------------------------------------------------------
# Factsheet downloads — top-10 pages by file_download event count
# ---------------------------------------------------------------------------
def download_top_pages(ga: BetaAnalyticsDataClient) -> list[tuple[str, int]]:
    # Pull more rows than needed so trailing-slash duplicates don't push real pages out of top 10.
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=event_name_exact("file_download"),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)],
        limit=50,
    ))
    totals: dict[str, int] = {}
    for r in resp.rows:
        path = normalise(r.dimension_values[0].value)
        totals[path] = totals.get(path, 0) + int(r.metric_values[0].value)
    return sorted(totals.items(), key=lambda x: x[1], reverse=True)[:10]


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------
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


def fmt_int(v: object) -> str:
    return f"{v:,}" if isinstance(v, int) else "—"


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)

    global START, END, PERIOD_LABEL
    START, END = p.start, p.end
    PERIOD_LABEL = p.label

    ga = ga4.client()

    # Channel baseline
    print("Fetching channel baseline...")
    e_sess, e_usrs, t_sess, t_usrs = channel_totals(ga)
    baseline_pct = e_sess / t_sess * 100 if t_sess else 0
    print(f"  Email:      {e_sess:,} sessions  {e_usrs:,} users")
    print(f"  Total:      {t_sess:,} sessions  {t_usrs:,} users")
    print(f"  Email share: {baseline_pct:.1f}%\n")

    # Section-touch
    print("Fetching section-touch data...")
    touch: dict[str, dict] = {}
    for label, _, ga4_regex, _ in SECTIONS:
        print(f"  {label}...")
        flt = page_filter(ga4_regex)
        et_s, et_u = touch_metrics(ga, flt, only_email=True)
        tt_s, tt_u = touch_metrics(ga, flt, only_email=False)
        touch[label] = {
            "email_touch_sessions": et_s, "email_touch_users": et_u,
            "total_touch_sessions": tt_s, "total_touch_users": tt_u,
        }
    print(f"  {OTHER_LABEL}...")
    et_s, et_u = touch_metrics(ga, other_touch_filter(), only_email=True)
    tt_s, tt_u = touch_metrics(ga, other_touch_filter(), only_email=False)
    touch[OTHER_LABEL] = {
        "email_touch_sessions": et_s, "email_touch_users": et_u,
        "total_touch_sessions": tt_s, "total_touch_users": tt_u,
    }
    print()

    # Landing-page
    print("Fetching landing-page data (email)...")
    e_landed = landing_query(ga, only_email=True)
    print("Fetching landing-page data (total)...")
    t_landed = landing_query(ga, only_email=False)
    print()

    # Factsheet downloads
    print("Fetching file_download event top pages...")
    dl_pages = download_top_pages(ga)
    total_dl_events = sum(c for _, c in dl_pages)
    print(f"  {len(dl_pages)} pages returned, {total_dl_events:,} events in top 10\n")

    # Build CSV rows
    section_order = [s[0] for s in SECTIONS] + [OTHER_LABEL]
    csv_rows: list[dict] = []
    for lbl in section_order:
        td = touch.get(lbl, {})
        el_s, el_u = e_landed.get(lbl, (0, 0))
        tl_s, tl_u = t_landed.get(lbl, (0, 0))
        csv_rows.append({
            "section":               lbl,
            "email_touch_sessions":  td.get("email_touch_sessions", 0),
            "email_touch_users":     td.get("email_touch_users", 0),
            "total_touch_sessions":  td.get("total_touch_sessions", 0),
            "total_touch_users":     td.get("total_touch_users", 0),
            "email_landed_sessions": el_s,
            "email_landed_users":    el_u,
            "total_landed_sessions": tl_s,
            "total_landed_users":    tl_u,
        })
    # (not set) row — touch has no equivalent (it needs a landing page to be missing)
    el_s, el_u = e_landed.get(NOT_SET_LABEL, (0, 0))
    tl_s, tl_u = t_landed.get(NOT_SET_LABEL, (0, 0))
    csv_rows.append({
        "section":               NOT_SET_LABEL,
        "email_touch_sessions":  "",
        "email_touch_users":     "",
        "total_touch_sessions":  "",
        "total_touch_users":     "",
        "email_landed_sessions": el_s,
        "email_landed_users":    el_u,
        "total_landed_sessions": tl_s,
        "total_landed_users":    tl_u,
    })
    # Channel baseline row
    csv_rows.append({
        "section":               "[ALL TRAFFIC — channel baseline]",
        "email_touch_sessions":  e_sess,
        "email_touch_users":     e_usrs,
        "total_touch_sessions":  t_sess,
        "total_touch_users":     t_usrs,
        "email_landed_sessions": e_sess,
        "email_landed_users":    e_usrs,
        "total_landed_sessions": t_sess,
        "total_landed_users":    t_usrs,
    })

    # Console summary
    print(f"\n{'Section':<30} {'E.T':>7} {'T.T':>7} | {'E.L':>7} {'T.L':>7}   (sessions)")
    print("-" * 68)
    for r in csv_rows[:-1]:
        print(f"{r['section']:<30} {fmt_int(r['email_touch_sessions']):>7} "
              f"{fmt_int(r['total_touch_sessions']):>7} | "
              f"{fmt_int(r['email_landed_sessions']):>7} "
              f"{fmt_int(r['total_landed_sessions']):>7}")

    write_csv(csv_rows, f"{p.slug}_section_combined.csv")

    # Markdown
    md: list[str] = []
    md.append(f"# {p.label} Section Activity — Combined Methodology Report\n")
    md.append(f"**Window:** {PERIOD_LABEL} ({START} – {END}; GA4 property {PROPERTY_ID})  ")
    md.append(f"**Channel baseline:** {e_sess:,} email sessions / {t_sess:,} total sessions "
              f"({baseline_pct:.1f}% email)  ")
    md.append(f"**Email users:** {e_usrs:,} of {t_usrs:,} site users ({e_usrs/t_usrs*100:.1f}%)\n")

    md.append("## Two methodologies — what each tells you\n")
    md.append("| | Section-touch (T columns) | Landing-page (L columns) |")
    md.append("|---|---|---|")
    md.append("| **What counts** | Any session touching ≥1 page in the section | Session whose *first page* was in the section |")
    md.append("| **Double-counting** | Yes — a session touching Articles then Resources counts in both | No — each session in exactly one bucket |")
    md.append("| **Section totals vs baseline** | Will exceed baseline (by design) | Sum to baseline (including `(not set)`) |")
    md.append("| **Best for answering** | Which content types do users actually consume? | What brings users to the site? |\n")

    md.append("## Channel baseline\n")
    md.append("| | Email | Total site | Email share |")
    md.append("|---|---:|---:|---:|")
    md.append(f"| Sessions | {e_sess:,} | {t_sess:,} | {baseline_pct:.1f}% |")
    md.append(f"| Users | {e_usrs:,} | {t_usrs:,} | {e_usrs/t_usrs*100:.1f}% |\n")

    md.append("## Section activity\n")
    md.append("T = touch (any page in session). L = landed (first page of session).\n")
    md.append("| Section | Email T sess | Email L sess | Total T sess | Total L sess | Email T users | Total T users |")
    md.append("|---|---:|---:|---:|---:|---:|---:|")
    for r in csv_rows:
        if r["section"] in ("[ALL TRAFFIC — channel baseline]",):
            continue
        md.append(
            f"| {r['section']} "
            f"| {fmt_int(r['email_touch_sessions'])} "
            f"| {fmt_int(r['email_landed_sessions'])} "
            f"| {fmt_int(r['total_touch_sessions'])} "
            f"| {fmt_int(r['total_landed_sessions'])} "
            f"| {fmt_int(r['email_touch_users'])} "
            f"| {fmt_int(r['total_touch_users'])} |"
        )
    md.append(
        f"| **[Channel baseline]** "
        f"| **{e_sess:,}** "
        f"| **{e_sess:,}** "
        f"| **{t_sess:,}** "
        f"| **{t_sess:,}** "
        f"| **{e_usrs:,}** "
        f"| **{t_usrs:,}** |"
    )
    md.append("")

    md.append("**Touch totals will exceed the channel baseline** — expected. If Articles & blog shows 3,000 touch sessions and the baseline is 7,949, it means 38% of all sessions on the site touched at least one article page, regardless of what they landed on first.  ")
    md.append(f"**`(not set)` landed sessions** ({t_landed.get(NOT_SET_LABEL,(0,0))[0]:,} total) = sessions GA4 couldn't attribute to a landing page. The touch methodology has no equivalent row.\n")

    # Email-over-reliance callouts (touch methodology)
    md.append("## Email reliance by section (touch)\n")
    md.append(f"Site-wide: **{baseline_pct:.1f}%** of sessions are email-sourced. Sections ≥5pp above or below:\n")
    movers = []
    for r in csv_rows:
        if r["section"] in ("[ALL TRAFFIC — channel baseline]", NOT_SET_LABEL):
            continue
        tt_s = r.get("total_touch_sessions")
        et_s = r.get("email_touch_sessions")
        if not isinstance(tt_s, int) or not isinstance(et_s, int) or tt_s == 0:
            continue
        pct = et_s / tt_s * 100
        delta = pct - baseline_pct
        if abs(delta) >= 5:
            movers.append((r["section"], pct, delta))
    if not movers:
        md.append("- No section is more than 5pp away from the baseline.\n")
    else:
        for name, pct, delta in movers:
            direction = "over-relies on email" if delta > 0 else "under-relies on email"
            md.append(f"- **{name}** {direction} — {pct:.1f}% of touch sessions "
                      f"are email-sourced ({'+' if delta>0 else ''}{delta:.1f} pp vs {baseline_pct:.1f}% baseline).")
        md.append("")

    md.append("## File download events — top 10 trigger pages\n")
    md.append(f"GA4 `file_download` events, {p.label}. Shows which pages triggered download clicks.\n")
    if dl_pages:
        md.append("| Page | Download events |")
        md.append("|---|---:|")
        for path, count in dl_pages:
            md.append(f"| `{path}` | {count:,} |")
        md.append("")
        md.append("**Reading this table:** each row is a page the user was *on* when they clicked a download link — not the file they downloaded. "
                  "High counts on `/resources` means most downloads happen via the Resources hub; "
                  "high counts on blog pages means users are downloading factsheets embedded in articles.")
    else:
        md.append("_No `file_download` events returned. Either enhanced measurement is not tracking file downloads on this property, "
                  "or downloads are tracked under a custom event name (check GA4 Events report for the actual name)._")
    md.append("")
    md.append("**Caveat:** GA4 `file_download` only fires if the page JS loads and the user clicks a tracked link element. "
              "Direct URL access, right-click-save, and some browser download behaviours won't be captured.\n")

    md.append("## Ambiguous calls — flagged\n")
    md.append("- **`/tools-and-videos`** → **Tools & Videos** (shared hub for both interactive tools and videos; judgment call, primarily tools entry-point).")
    md.append("- **Allergy analyser sub-pages** (`/section1`, `/bake`, `/clues`, `/chart`, `/detection`, `/mist`) → **Tools & Videos**. Short slugs — Python regex uses trailing `/` or `$` terminator to avoid false positives against unrelated content slugs.")
    md.append("- **`/anal-fissures-breaking-the-cycle-and-the-stigma*`** → **Mini Clinical Audit Course** (marketing landing page for the CPD module, not an article).")
    md.append("- **`/nasal-and-sinus-health`** → **Articles & blog** (long-form clinical editorial, not a tool or resource).")
    md.append("- **PDF paths `/wp-content/uploads/*.pdf`** → **Resources / Factsheets** (touch methodology). As a landing page these are near-zero (GA4 tracking gap); treat Resources landed sessions as hub-page traffic only.\n")

    write_text("\n".join(md), f"{p.slug}_section_combined.md")
    print("\nDone.")


if __name__ == "__main__":
    main()

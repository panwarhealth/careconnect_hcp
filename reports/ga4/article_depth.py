"""
Article read-depth analysis — all 9 active posts.
Metrics: pageviews, 15s engaged sessions (started), 90% scroll (completed),
         completion %, word count (deterministic HTML extraction).

Word count method: fetch live URL, extract .entry-content div via BeautifulSoup,
strip all tags, split on whitespace, len(). No LLM. No estimation.

Usage: python reports/ga4/article_depth.py [--period FY26 | --start ... --end ...]
"""
import argparse
import re
import sys
import time
import urllib.request
from pathlib import Path

from bs4 import BeautifulSoup

from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, FilterExpressionList,
    Metric, OrderBy, RunReportRequest,
)

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods

PROP       = f"properties/{ga4.PROPERTY_ID}"
# Date window — set from the resolved period in main(); functions read these globals.
START, END = "2025-04-01", "2026-03-31"
PERIOD_LABEL = "Apr 2025–Mar 2026"

POSTS = [
    ("Tattersall – Allergic rhinitis",
     "/blog/top-4-expert-tips-to-optimise-allergic-rhinitis-management-with-dr-jessica-tattersall"),
    ("GLP-1RA / GI tolerability",
     "/blog/how-gps-can-support-gi-tolerability-for-patients-taking-anti-obesity-medications"),
    ("Cold & flu season",
     "/blog/prepare-for-a-big-cold-and-flu-season"),
    ("Chocolate / stool chart",
     "/blog/from-chocolate-to-clinical-clues"),
    ("Travellers diarrhoea",
     "/blog/travellers-diarrhoea-quick-management-guide-for-the-holiday-season"),
    ("Allergy eye",
     "/blog/can-you-spot-the-allergy-eye"),
    ("ARISE clinical trials",
     "/blog/keeping-up-with-clinical-trials-arise"),
    ("MIST+ trial (children)",
     "/blog/what-gps-need-to-know-how-the-mist-trial-is-changing-osdb-management-in-children"),
    ("Sip to stand / POTS",
     "/blog/sip-to-stand-why-hydration-is-essential-in-pots"),
]

BASE_URL = "https://hcp.carepharma.com.au"


def pull_metric_per_post(ga, event_filter=None) -> dict[str, int]:
    """
    Returns {path_prefix: count} for a given event filter (or pageviews if None).
    Uses BEGINS_WITH on landingPagePlusQueryString for pageviews,
    pagePath for events.
    """
    results = {}
    for label, path in POSTS:
        if event_filter:
            dim_filter = FilterExpression(and_group=FilterExpressionList(expressions=[
                FilterExpression(filter=Filter(
                    field_name="pagePath",
                    string_filter=Filter.StringFilter(
                        value=path,
                        match_type=Filter.StringFilter.MatchType.BEGINS_WITH))),
                FilterExpression(filter=Filter(
                    field_name="eventName",
                    string_filter=Filter.StringFilter(value=event_filter))),
            ]))
            metric = Metric(name="eventCount")
        else:
            dim_filter = FilterExpression(filter=Filter(
                field_name="pagePath",
                string_filter=Filter.StringFilter(
                    value=path,
                    match_type=Filter.StringFilter.MatchType.BEGINS_WITH)))
            metric = Metric(name="screenPageViews")

        resp = ga.run_report(RunReportRequest(
            property=PROP,
            date_ranges=[DateRange(start_date=START, end_date=END)],
            metrics=[metric],
            dimension_filter=dim_filter,
        ))
        count = int(resp.rows[0].metric_values[0].value) if resp.rows else 0
        results[path] = count
        print(f"  {label}: {count}")
    return results


def word_count(url: str) -> int | None:
    """
    Fetch URL, extract .entry-content div, strip tags, count whitespace tokens.
    Returns None if extraction fails.
    """
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        html = urllib.request.urlopen(req, timeout=15).read().decode("utf-8", errors="replace")
        soup = BeautifulSoup(html, "html.parser")

        # Try entry-content first, fall back to article tag
        content = soup.find("div", class_=re.compile(r"entry-content"))
        if not content:
            content = soup.find("article")
        if not content:
            return None

        # Remove nav, header, footer, scripts, styles that may be inside
        for tag in content.find_all(["script", "style", "nav", "header", "footer",
                                     "form", "button", "noscript"]):
            tag.decompose()

        text = content.get_text(separator=" ")
        words = text.split()
        return len(words)
    except Exception as e:
        print(f"    word count error for {url}: {e}")
        return None


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)

    global START, END, PERIOD_LABEL
    START, END = p.start, p.end
    PERIOD_LABEL = p.label

    ga = ga4.client()

    print("Pulling pageviews per post...")
    pv = pull_metric_per_post(ga)

    print("\nPulling 15 Second Page View (started reading) per post...")
    started = pull_metric_per_post(ga, "15 Second Page View")

    print("\nPulling scroll events (90% depth, completed reading) per post...")
    completed = pull_metric_per_post(ga, "scroll")

    print("\nFetching word counts from live site...")
    wc = {}
    for label, path in POSTS:
        url = BASE_URL + path + "/"
        print(f"  Fetching {label}...", end=" ", flush=True)
        time.sleep(0.5)  # polite crawl
        count = word_count(url)
        wc[path] = count
        print(f"{count} words" if count else "FAILED")

    # Build table
    rows = []
    for label, path in POSTS:
        pv_v   = pv.get(path, 0)
        st_v   = started.get(path, 0)
        cp_v   = completed.get(path, 0)
        pct    = f"{cp_v/st_v*100:.0f}%" if st_v > 0 else "—"
        wc_v   = wc.get(path)
        rows.append((label, pv_v, st_v, cp_v, pct, wc_v))

    # Sort by pageviews desc
    rows.sort(key=lambda x: -x[1])

    print("\n\n=== ARTICLE READ-DEPTH — ALL 9 POSTS ===\n")
    header = "| Post | Pageviews | Started | Completed | Completion % | Word count |"
    sep    = "|---|---|---|---|---|---|"
    print(header)
    print(sep)
    for label, pv_v, st_v, cp_v, pct, wc_v in rows:
        wc_str = str(wc_v) if wc_v is not None else "—"
        print(f"| {label} | {pv_v:,} | {st_v:,} | {cp_v:,} | {pct} | {wc_str} |")

    # Write MD
    md_lines = [
        "# Article Read-Depth — All 9 Active Posts",
        f"Source: GA4 property {ga4.PROPERTY_ID}, {PERIOD_LABEL}",
        "Word count: deterministic HTML extraction from live site (BeautifulSoup, len(text.split())),",
        "from the .entry-content div, scripts/styles/forms stripped. Not an estimate.",
        "",
        "| Post | Pageviews | Started | Completed | Completion % | Word count |",
        "|---|---|---|---|---|---|",
    ]
    for label, pv_v, st_v, cp_v, pct, wc_v in rows:
        wc_str = str(wc_v) if wc_v is not None else "— (extraction failed)"
        md_lines.append(f"| {label} | {pv_v:,} | {st_v:,} | {cp_v:,} | {pct} | {wc_str} |")

    md_lines += [
        "",
        "## Metric definitions",
        "- **Pageviews:** `screenPageViews` on any URL beginning with the post path.",
        "- **Started:** count of `15 Second Page View` custom events on post (spent 15s+ on page).",
        "- **Completed:** count of `scroll` events on post (GA4 built-in, fires at 90% page depth).",
        "- **Completion %:** Completed ÷ Started. Excludes users who never hit 15s.",
        "- **Word count:** Characters in .entry-content after stripping all HTML tags, split on whitespace.",
        "  Includes all visible text: headings, body, captions, pull-quotes. Excludes nav/footer/scripts.",
    ]

    md_path = io.out_dir() / f"article_read_depth_all9_{p.slug}.md"
    md_path.write_text("\n".join(md_lines))
    print(f"\nwrote {md_path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(md_path)


if __name__ == "__main__":
    main()

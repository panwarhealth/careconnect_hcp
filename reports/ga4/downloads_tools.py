"""
FY26 (Apr 2025 – Mar 2026) Downloads & Tools slide — three data pulls.

Pull 1: file_download events by session channel + sub-channel (custom grouping).
         Attempts PDF-only filter via customEvent:file_extension; falls back to
         all file_download events if the custom dimension isn't registered in GA4.

Pull 2: Of Direct-channel sessions that had a file_download event, how many
         had that download fire on an article page (/blog/*, /nasal-and-sinus-health/*, /topics/*)?
         Proxy for "Direct = article reader who lost referrer".

Pull 3: Video event existence check (video_start / video_play / youtube_play).
         Tool usage: sessions touching each tool by GA4 channel group.

    python reports/ga4/downloads_tools.py --period FY26

Outputs (to reports/out/ga4/, also copied to Windows Downloads):
  - <slug>_factsheet_downloads.csv + <slug>_factsheet_downloads.md
  - <slug>_video_tools.csv + <slug>_video_tools.md
"""
import argparse
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
# START/END are module globals read by the pulls; main() overwrites them from --period.
START, END = "2025-04-01", "2026-03-31"


# ---------------------------------------------------------------------------
# Filter / expression helpers
# ---------------------------------------------------------------------------
def exact(field: str, value: str) -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name=field,
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.EXACT, value=value),
    ))


def partial_re(field: str, regex: str) -> FilterExpression:
    return FilterExpression(filter=Filter(
        field_name=field,
        string_filter=Filter.StringFilter(
            match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP, value=regex),
    ))


def and_(*exprs: FilterExpression) -> FilterExpression:
    return FilterExpression(and_group=FilterExpressionList(expressions=list(exprs)))


FILE_DOWNLOAD   = exact("eventName", "file_download")
DIRECT_CHANNEL  = exact("sessionDefaultChannelGroup", "Direct")
ARTICLES_PATH   = partial_re("pagePath", r"^/(blog|nasal-and-sinus-health|topics)(/|$)")

# ---------------------------------------------------------------------------
# Custom channel grouping
# Maps (sessionDefaultChannelGroup, sessionSource, sessionMedium) → label.
# Assumptions documented; flag any unrecognised sources.
# ---------------------------------------------------------------------------
CUSTOM_CHANNELS = [
    "MED Today / HPS",
    "Direct",
    "Sister-site",
    "Mailchimp",
    "Medical Republic",
    "Rep / Conference",
    "Solus",
    "Other",
]


def map_channel(channel_group: str, source: str, medium: str) -> str:
    ch  = channel_group.lower()
    src = source.lower()
    med = medium.lower()

    if ch == "direct":
        return "Direct"

    if ch == "email" or "email" in med:
        if any(x in src for x in ["med-today", "medtoday", "med_today", "hps",
                                    "health-professional", "health_professional"]):
            return "MED Today / HPS"
        if "mailchimp" in src or src in ("mc",):
            return "Mailchimp"
        if any(x in src for x in ["medical-republic", "medicalrepublic",
                                    "med-republic", "medical_republic"]):
            return "Medical Republic"
        if "solus" in src:
            return "Solus"
        if any(x in src for x in ["rep", "conference", "conf", "detail"]):
            return "Rep / Conference"
        # Email but source not recognised — keep as "Other" but retain source for audit
        return "Other"

    if ch == "referral":
        if any(x in src for x in ["carepharma", "care-pharma", "care_pharma",
                                    "hydralyte", "daktarin", "rectogesic",
                                    "haemorrhoid", "hemorrhoid"]):
            return "Sister-site"
        return "Other"

    return "Other"


# ---------------------------------------------------------------------------
# Pull 1: file_download events by channel
# Query with sessionDefaultChannelGroup + sessionSource + sessionMedium so
# we can apply the custom channel mapping in Python.
# Also attempts a PDF-only run via customEvent:file_extension = "pdf".
# ---------------------------------------------------------------------------
def pull_downloads(ga: BetaAnalyticsDataClient) -> tuple[list[dict], list[dict], bool]:
    """
    Returns (rows_all_downloads, rows_by_raw_source, pdf_filter_worked).
    rows_all_downloads: [{custom_channel, event_count}]
    rows_by_raw_source: [{channel_group, source, medium, event_count}] for audit
    """
    # Attempt 1: PDF-only via customEvent:file_extension
    pdf_filter = and_(FILE_DOWNLOAD, exact("customEvent:file_extension", "pdf"))
    try:
        pdf_resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=START, end_date=END)],
            dimensions=[
                Dimension(name="sessionDefaultChannelGroup"),
                Dimension(name="sessionSource"),
                Dimension(name="sessionMedium"),
            ],
            metrics=[Metric(name="eventCount")],
            dimension_filter=pdf_filter,
            limit=500,
        ))
        pdf_total = sum(int(r.metric_values[0].value) for r in pdf_resp.rows)
        pdf_filter_worked = pdf_total > 0
        print(f"  PDF-filtered total: {pdf_total:,} events")
    except Exception as e:
        print(f"  customEvent:file_extension filter failed ({e}); falling back to all file_download")
        pdf_resp = None
        pdf_filter_worked = False

    # Attempt 2: all file_download events (the reliable baseline)
    all_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[
            Dimension(name="sessionDefaultChannelGroup"),
            Dimension(name="sessionSource"),
            Dimension(name="sessionMedium"),
        ],
        metrics=[Metric(name="eventCount")],
        dimension_filter=FILE_DOWNLOAD,
        limit=500,
    ))
    all_total = sum(int(r.metric_values[0].value) for r in all_resp.rows)
    print(f"  All file_download total: {all_total:,} events")

    # Use PDF rows if filter worked and totals are close; otherwise use all
    active_resp = pdf_resp if pdf_filter_worked else all_resp

    # Aggregate into custom channel buckets
    buckets: dict[str, int] = {ch: 0 for ch in CUSTOM_CHANNELS}
    raw_rows: list[dict] = []
    for row in active_resp.rows:
        ch_group = row.dimension_values[0].value
        source   = row.dimension_values[1].value
        medium   = row.dimension_values[2].value
        count    = int(row.metric_values[0].value)
        custom   = map_channel(ch_group, source, medium)
        buckets[custom] = buckets.get(custom, 0) + count
        raw_rows.append({
            "channel_group": ch_group, "source": source,
            "medium": medium, "event_count": count,
            "mapped_to": custom,
        })

    grand_total = sum(buckets.values())
    result = []
    for ch in CUSTOM_CHANNELS:
        n = buckets[ch]
        result.append({
            "channel": ch,
            "download_events": n,
            "pct_of_total": round(n / grand_total * 100, 1) if grand_total else 0,
        })
    result.append({
        "channel": "TOTAL",
        "download_events": grand_total,
        "pct_of_total": 100.0,
    })
    return result, raw_rows, pdf_filter_worked


# ---------------------------------------------------------------------------
# Pull 2: Direct-channel downloads — how many fired on an article page?
# file_download event fired on /blog/*, /nasal-and-sinus-health/*, /topics/*
# while session was Direct channel.
# ---------------------------------------------------------------------------
def pull_direct_article_downloads(ga: BetaAnalyticsDataClient) -> dict:
    # Total sessions: Direct + any file_download event
    total_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="eventCount")],
        dimension_filter=and_(FILE_DOWNLOAD, DIRECT_CHANNEL),
    ))
    total_sess = int(total_resp.rows[0].metric_values[0].value) if total_resp.rows else 0
    total_events = int(total_resp.rows[0].metric_values[1].value) if total_resp.rows else 0

    # Sessions: Direct + file_download event that fired ON an article page
    article_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        metrics=[Metric(name="sessions"), Metric(name="eventCount")],
        dimension_filter=and_(FILE_DOWNLOAD, DIRECT_CHANNEL, ARTICLES_PATH),
    ))
    article_sess = int(article_resp.rows[0].metric_values[0].value) if article_resp.rows else 0
    article_events = int(article_resp.rows[0].metric_values[1].value) if article_resp.rows else 0

    # Article page breakdown: which article pages triggered Direct downloads?
    pages_resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=and_(FILE_DOWNLOAD, DIRECT_CHANNEL, ARTICLES_PATH),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)],
        limit=10,
    ))
    top_pages = [
        (r.dimension_values[0].value, int(r.metric_values[0].value))
        for r in pages_resp.rows
    ]

    return {
        "total_direct_download_sessions": total_sess,
        "total_direct_download_events":   total_events,
        "article_page_download_sessions": article_sess,
        "article_page_download_events":   article_events,
        "top_article_pages":              top_pages,
    }


# ---------------------------------------------------------------------------
# Pull 3a: Video event check — does any video event exist in FY26?
# ---------------------------------------------------------------------------
VIDEO_EVENT_NAMES = [
    "video_start", "video_play", "video_complete", "video_progress",
    "youtube_play", "youtube_start", "video_click", "play_video",
]


def pull_video_events(ga: BetaAnalyticsDataClient) -> list[dict]:
    """Returns list of {event_name, event_count} for any video events found."""
    found = []
    for name in VIDEO_EVENT_NAMES:
        resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=START, end_date=END)],
            metrics=[Metric(name="eventCount")],
            dimension_filter=exact("eventName", name),
        ))
        count = int(resp.rows[0].metric_values[0].value) if resp.rows else 0
        if count > 0:
            found.append({"event_name": name, "event_count": count})
            print(f"  Found: {name} → {count:,} events")
        else:
            print(f"  Not found: {name}")
    return found


# Also check for embedded video clicks via click events on youtube domains
def pull_video_clicks(ga: BetaAnalyticsDataClient) -> int:
    """Count click events where link_url contains youtube (custom event param)."""
    try:
        resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=START, end_date=END)],
            metrics=[Metric(name="eventCount")],
            dimension_filter=and_(
                exact("eventName", "click"),
                partial_re("customEvent:link_url", "youtube"),
            ),
        ))
        count = int(resp.rows[0].metric_values[0].value) if resp.rows else 0
        print(f"  YouTube click events: {count:,}")
        return count
    except Exception as e:
        print(f"  YouTube click check failed: {e}")
        return -1


# ---------------------------------------------------------------------------
# Pull 3b: Tool usage — sessions per tool, broken down by GA4 channel group
# ---------------------------------------------------------------------------
TOOLS = [
    (
        "Allergy Analyser",
        r"^/(allergy-analyser|section\d|sec\d|tip\d|option\d|bake|clues|chart|detection|mist)",
    ),
    (
        "Rectogesic 3D Model",
        r"^/(rectogesic-3d-model|interactive-model)",
    ),
    (
        "FESS Demonstration",
        r"^/fess-demonstration",
    ),
]


def pull_tool_sessions(ga: BetaAnalyticsDataClient) -> list[dict]:
    rows = []
    for tool_name, regex in TOOLS:
        print(f"  {tool_name}...")
        resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=START, end_date=END)],
            dimensions=[Dimension(name="sessionDefaultChannelGroup")],
            metrics=[Metric(name="sessions")],
            dimension_filter=partial_re("pagePath", regex),
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        ))
        total_sess = sum(int(r.metric_values[0].value) for r in resp.rows)
        channel_breakdown = {
            r.dimension_values[0].value: int(r.metric_values[0].value)
            for r in resp.rows
        }
        rows.append({
            "tool": tool_name,
            "total_sessions": total_sess,
            "channel_breakdown": channel_breakdown,
        })
        print(f"    Total: {total_sess:,} sessions")
    return rows


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------
def write_csv(out: Path, rows: list[dict], filename: str) -> None:
    if not rows:
        return
    io.copy_to_downloads(io.write_dicts(out / filename, rows))


def write_text(out: Path, content: str, filename: str) -> None:
    path = out / filename
    path.write_text(content)
    print(f"wrote {path.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(path)


def fi(v: int | str) -> str:
    return f"{v:,}" if isinstance(v, int) else str(v)


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> None:
    global START, END
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    START, END = p.start, p.end
    out = io.out_dir("ga4")
    ga = ga4.client()

    # --- Pull 1: Downloads by channel ---
    print("\n=== Pull 1: file_download events by channel ===")
    dl_rows, raw_source_rows, pdf_filter_worked = pull_downloads(ga)
    write_csv(out, dl_rows, f"{p.slug}_factsheet_downloads.csv")

    # --- Pull 2: Direct downloads from article pages ---
    print("\n=== Pull 2: Direct-channel downloads fired on article pages ===")
    direct_data = pull_direct_article_downloads(ga)
    d = direct_data
    print(f"  Direct download sessions (any page):   {d['total_direct_download_sessions']:,}")
    print(f"  Direct download sessions (article page): {d['article_page_download_sessions']:,}")
    print(f"  Direct download events (article page):   {d['article_page_download_events']:,}")

    # --- Pull 3a: Video events ---
    print("\n=== Pull 3a: Video event existence check ===")
    video_found = pull_video_events(ga)
    yt_clicks = pull_video_clicks(ga)

    # --- Pull 3b: Tool sessions ---
    print("\n=== Pull 3b: Tool usage sessions ===")
    tool_rows = pull_tool_sessions(ga)

    # Write tool CSV (flattened)
    tool_csv_rows = []
    all_channels = set()
    for t in tool_rows:
        all_channels.update(t["channel_breakdown"].keys())
    all_channels_sorted = sorted(all_channels)
    for t in tool_rows:
        row = {"tool": t["tool"], "total_sessions": t["total_sessions"]}
        for ch in all_channels_sorted:
            row[f"channel_{ch.replace(' ', '_')}"] = t["channel_breakdown"].get(ch, 0)
        tool_csv_rows.append(row)
    write_csv(out, tool_csv_rows, f"{p.slug}_video_tools.csv")

    # -------------------------------------------------------------------------
    # Markdown — downloads
    # -------------------------------------------------------------------------
    total_row = next(r for r in dl_rows if r["channel"] == "TOTAL")
    grand_total = total_row["download_events"]

    md1: list[str] = []
    md1.append("# FY26 Factsheet Downloads by Channel\n")
    md1.append(f"**Window:** {p.label} ({p.start} – {p.end}) (GA4 property {PROPERTY_ID})  ")
    md1.append(f"**Event:** `file_download`  ")
    if pdf_filter_worked:
        md1.append("**Filter:** PDF only (`customEvent:file_extension = \"pdf\"`) — custom dimension registered in GA4.  ")
    else:
        md1.append("**Filter:** All `file_download` events (PDF extension filter not available — custom dimension not registered in GA4). "
                   "All downloads on this site are expected to be PDFs from `/wp-content/uploads/`.  ")
    md1.append(f"**Total download events: {grand_total:,}**\n")

    md1.append("## Downloads by channel\n")
    md1.append("| Channel | Download events | % of total |")
    md1.append("|---|---:|---:|")
    for r in dl_rows:
        if r["channel"] == "TOTAL":
            md1.append(f"| **TOTAL** | **{r['download_events']:,}** | **{r['pct_of_total']}%** |")
        else:
            md1.append(f"| {r['channel']} | {r['download_events']:,} | {r['pct_of_total']}% |")
    md1.append("")

    md1.append("**Channel mapping assumptions** (verify against actual UTM source values):\n")
    md1.append("- `Direct` → GA4 `sessionDefaultChannelGroup = Direct`")
    md1.append("- `MED Today / HPS` → Email channel + source contains `med-today`, `hps`, `health-professional`")
    md1.append("- `Mailchimp` → Email channel + source contains `mailchimp`")
    md1.append("- `Medical Republic` → Email channel + source contains `medical-republic`")
    md1.append("- `Solus` → Email channel + source contains `solus`")
    md1.append("- `Rep / Conference` → Email channel + source contains `rep`, `conference`, `conf`")
    md1.append("- `Sister-site` → Referral channel + source contains `carepharma`, `hydralyte`, `daktarin`, `rectogesic`")
    md1.append("- `Other` → anything not matched above (including Organic, Unassigned, etc.)\n")

    # Raw source audit
    other_raw = [r for r in raw_source_rows if r["mapped_to"] == "Other" and r["event_count"] > 0]
    if other_raw:
        md1.append("### Raw sources mapped to 'Other' — verify these\n")
        md1.append("| GA4 channel group | Source | Medium | Events |")
        md1.append("|---|---|---|---:|")
        for r in sorted(other_raw, key=lambda x: x["event_count"], reverse=True):
            md1.append(f"| {r['channel_group']} | {r['source']} | {r['medium']} | {r['event_count']:,} |")
        md1.append("")

    # Pull 2 in the same markdown
    md1.append("## Direct-channel downloads — article-reader proxy\n")
    md1.append("**Question:** Of Direct-channel sessions that triggered a `file_download` event, "
               "how many had that download fire on an article page (`/blog/*`, `/nasal-and-sinus-health/*`, `/topics/*`)?\n")
    md1.append("**Why this matters:** GA4 often loses the referrer for email clicks where "
               "the user's email client strips UTMs or doesn't pass a referrer header. "
               "Those sessions land as 'Direct'. If the download fired on an article page "
               "that was clearly promoted in an eDM (e.g. the traveller's diarrhoea factsheet article), "
               "it's likely an email-reader, not a true direct visitor.\n")
    md1.append("| Metric | Value |")
    md1.append("|---|---:|")
    md1.append(f"| Total Direct sessions with a `file_download` event | {d['total_direct_download_sessions']:,} |")
    md1.append(f"| Total Direct `file_download` events | {d['total_direct_download_events']:,} |")
    md1.append(f"| Direct sessions where download fired on an article page | {d['article_page_download_sessions']:,} |")
    md1.append(f"| Direct `file_download` events on article pages | {d['article_page_download_events']:,} |")
    if d['total_direct_download_events'] > 0:
        pct = d['article_page_download_events'] / d['total_direct_download_events'] * 100
        md1.append(f"| Article-page share of Direct downloads | {pct:.0f}% |")
    md1.append("")

    if d["top_article_pages"]:
        md1.append("### Top article pages triggering Direct downloads\n")
        md1.append("| Article page | Direct download events |")
        md1.append("|---|---:|")
        for path, count in d["top_article_pages"]:
            md1.append(f"| `{path}` | {count:,} |")
        md1.append("")

    write_text(out, "\n".join(md1), f"{p.slug}_factsheet_downloads.md")

    # -------------------------------------------------------------------------
    # Markdown — video + tools
    # -------------------------------------------------------------------------
    md2: list[str] = []
    md2.append("# FY26 Video Events & Tool Usage\n")
    md2.append(f"**Window:** {p.label} ({p.start} – {p.end}) (GA4 property {PROPERTY_ID})\n")

    md2.append("## Video events\n")
    if video_found:
        md2.append(f"The following video events fired in FY26:\n")
        md2.append("| Event name | Event count |")
        md2.append("|---|---:|")
        for v in video_found:
            md2.append(f"| `{v['event_name']}` | {v['event_count']:,} |")
        md2.append("")
    else:
        md2.append("**No video events found.** Checked: " + ", ".join(f"`{n}`" for n in VIDEO_EVENT_NAMES) + ".\n")
        md2.append("Recommendation for the slide: note *'Video engagement not tracked in FY26 — "
                   "GA4 enhanced video measurement to be configured in FY27.'*\n")

    if yt_clicks > 0:
        md2.append(f"**YouTube link click events:** {yt_clicks:,} `click` events where `link_url` contained 'youtube'. "
                   "These are clicks on YouTube links/embeds, not play events — actual video views are unknown.\n")
    elif yt_clicks == 0:
        md2.append("**YouTube link clicks:** 0 click events with `link_url` containing 'youtube'.\n")
    else:
        md2.append("**YouTube link click check:** could not query (custom param not registered).\n")

    md2.append("## Tool usage — sessions by channel\n")
    md2.append("Methodology: section-touch (sessions that hit ≥1 page matching the tool URL pattern).\n")
    for t in tool_rows:
        md2.append(f"### {t['tool']}\n")
        md2.append(f"**Total sessions: {t['total_sessions']:,}**\n")
        if t["channel_breakdown"]:
            md2.append("| GA4 channel group | Sessions | % of tool sessions |")
            md2.append("|---|---:|---:|")
            sorted_ch = sorted(t["channel_breakdown"].items(), key=lambda x: x[1], reverse=True)
            for ch, sess in sorted_ch:
                pct = sess / t["total_sessions"] * 100 if t["total_sessions"] else 0
                md2.append(f"| {ch} | {sess:,} | {pct:.1f}% |")
            md2.append("")
        else:
            md2.append("_No sessions found._\n")

    md2.append("**Channel groups are GA4 standard** (`sessionDefaultChannelGroup`): Email, Direct, Referral, Unassigned, etc. "
               "Not broken into sub-channels (MED Today / HPS etc.) because tool session volumes are small and "
               "sub-channel mapping adds noise without meaningful signal.\n")

    write_text(out, "\n".join(md2), f"{p.slug}_video_tools.md")
    print("\nDone.")


if __name__ == "__main__":
    main()

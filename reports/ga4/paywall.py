"""
Paywall removal pre/post analysis for hcp.carepharma.com.au /blog/ content.
Gate was removed in early December 2025.

Steps:
  1. Daily metrics Nov 1 – Dec 31 2025 to find cutover date.
  2. Identify qualifying articles (>= 30 users in both pre and post windows).
  3. Check which scroll events are tracked on /blog/ pages.
  4. Per-article metrics for both windows.
  5. Campaign confounder check.

Output: reports/out/ga4/paywall_analysis.md (also copied to Windows Downloads)

    python reports/ga4/paywall.py
"""
from __future__ import annotations

import sys
from pathlib import Path
from datetime import date, datetime

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    Metric,
    OrderBy,
    RunReportRequest,
    FilterExpressionList,
)

PROPERTY_ID = ga4.PROPERTY_ID
# TODO: parameterize via lib.periods — the pre/post analysis is keyed to the
# Dec-2025 gate-removal event (Nov–Dec cutover detection + FY26 bounds); the
# windows and confounder calendar are event-specific, not a generic FY window.


# ---------------------------------------------------------------------------
# Auth
# ---------------------------------------------------------------------------

def make_client() -> BetaAnalyticsDataClient:
    return ga4.client()


# ---------------------------------------------------------------------------
# Step 1: Daily metrics Nov 1 – Dec 31 2025 on /blog/ pages
# ---------------------------------------------------------------------------

def step1_daily(c: BetaAnalyticsDataClient) -> tuple[list[dict], str, bool]:
    """Returns (rows, cutover_date_str, signal_clear)."""
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="2025-11-01", end_date="2025-12-31")],
        dimensions=[Dimension(name="date")],
        metrics=[
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="userEngagementDuration"),
            Metric(name="totalUsers"),
        ],
        dimension_filter=FilterExpression(
            filter=Filter(
                field_name="pagePath",
                string_filter=Filter.StringFilter(
                    match_type=Filter.StringFilter.MatchType.BEGINS_WITH,
                    value="/blog/",
                ),
            )
        ),
        order_bys=[OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date"))],
        limit=200,
    )
    resp = c.run_report(req)

    rows = []
    for r in resp.rows:
        raw_date = r.dimension_values[0].value  # YYYYMMDD
        d = f"{raw_date[:4]}-{raw_date[4:6]}-{raw_date[6:8]}"
        sessions = int(r.metric_values[0].value)
        engaged = int(r.metric_values[1].value)
        duration = float(r.metric_values[2].value)
        users = int(r.metric_values[3].value)
        bounce_rate = (sessions - engaged) / sessions if sessions else 0.0
        avg_eng = duration / sessions if sessions else 0.0
        rows.append({
            "date": d,
            "sessions": sessions,
            "engaged_sessions": engaged,
            "user_engagement_duration": duration,
            "total_users": users,
            "bounce_rate": bounce_rate,
            "avg_engagement_secs": avg_eng,
        })

    # Look for inflection in December: significant drop in bounce_rate or rise in avg_eng
    # Compare rolling 7-day averages across the Nov/Dec boundary
    # Focus on Dec 1–15; find day where bounce_rate drops >= 0.10 vs prior 7-day avg
    signal_clear = False
    cutover_date = "2025-12-01"

    dec_rows = [r for r in rows if r["date"] >= "2025-12-01"]
    nov_rows = [r for r in rows if r["date"] < "2025-12-01"]

    if nov_rows and dec_rows:
        nov_avg_bounce = sum(r["bounce_rate"] for r in nov_rows) / len(nov_rows)
        nov_avg_eng = sum(r["avg_engagement_secs"] for r in nov_rows) / len(nov_rows)

        # Scan Dec days looking for the first sustained shift
        best_candidate = None
        best_delta = 0.0

        for i, row in enumerate(dec_rows[:15]):  # first 15 Dec days
            # rolling 3-day avg from this point
            window = dec_rows[i:i+3]
            if len(window) < 2:
                continue
            w_bounce = sum(r["bounce_rate"] for r in window) / len(window)
            w_eng = sum(r["avg_engagement_secs"] for r in window) / len(window)
            delta_bounce = nov_avg_bounce - w_bounce  # positive = bounce dropped
            delta_eng = w_eng - nov_avg_eng  # positive = engagement rose
            # composite signal
            composite = delta_bounce + (delta_eng / 60)  # weight engagement in minutes
            if composite > best_delta:
                best_delta = composite
                best_candidate = row["date"]

        if best_candidate and best_delta > 0.05:
            cutover_date = best_candidate
            signal_clear = True

    return rows, cutover_date, signal_clear


# ---------------------------------------------------------------------------
# Step 2: Identify qualifying articles
# ---------------------------------------------------------------------------

def pull_article_metrics(
    c: BetaAnalyticsDataClient,
    start: str,
    end: str,
) -> dict[str, dict]:
    """Pull per-article metrics. Returns {pagePath: {title, users, sessions, engaged, duration}}."""
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[
            Dimension(name="pagePath"),
            Dimension(name="pageTitle"),
        ],
        metrics=[
            Metric(name="totalUsers"),
            Metric(name="sessions"),
            Metric(name="engagedSessions"),
            Metric(name="userEngagementDuration"),
        ],
        dimension_filter=FilterExpression(
            filter=Filter(
                field_name="pagePath",
                string_filter=Filter.StringFilter(
                    match_type=Filter.StringFilter.MatchType.BEGINS_WITH,
                    value="/blog/",
                ),
            )
        ),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="totalUsers"), desc=True)],
        limit=500,
    )
    resp = c.run_report(req)

    result = {}
    for r in resp.rows:
        path = r.dimension_values[0].value
        title = r.dimension_values[1].value
        users = int(r.metric_values[0].value)
        sessions = int(r.metric_values[1].value)
        engaged = int(r.metric_values[2].value)
        duration = float(r.metric_values[3].value)

        # Exclude the /blog/ landing page and /blog itself
        if path in ("/blog/", "/blog"):
            continue
        # Must be an article path (at least one segment after /blog/)
        parts = path.rstrip("/").split("/")
        if len(parts) < 3:
            continue

        # Aggregate by path (there can be duplicates from different page titles)
        if path in result:
            result[path]["users"] += users
            result[path]["sessions"] += sessions
            result[path]["engaged"] += engaged
            result[path]["duration"] += duration
        else:
            result[path] = {
                "title": title,
                "users": users,
                "sessions": sessions,
                "engaged": engaged,
                "duration": duration,
            }
    return result


# ---------------------------------------------------------------------------
# Step 3: Check scroll events on /blog/ pages
# ---------------------------------------------------------------------------

def step3_scroll_events(c: BetaAnalyticsDataClient) -> list[dict]:
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date="2025-04-01", end_date="2026-03-31")],
        dimensions=[Dimension(name="eventName")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=FilterExpression(
            filter=Filter(
                field_name="pagePath",
                string_filter=Filter.StringFilter(
                    match_type=Filter.StringFilter.MatchType.BEGINS_WITH,
                    value="/blog/",
                ),
            )
        ),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="eventCount"), desc=True)],
        limit=20,
    )
    resp = c.run_report(req)
    rows = []
    for r in resp.rows:
        rows.append({
            "event_name": r.dimension_values[0].value,
            "event_count": int(r.metric_values[0].value),
        })
    return rows


# ---------------------------------------------------------------------------
# Step 4: Scroll event rate per article
# ---------------------------------------------------------------------------

def pull_scroll_events_per_article(
    c: BetaAnalyticsDataClient,
    scroll_event: str,
    start: str,
    end: str,
) -> dict[str, int]:
    """Returns {pagePath: scroll_event_count}."""
    req = RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=start, end_date=end)],
        dimensions=[Dimension(name="pagePath")],
        metrics=[Metric(name="eventCount")],
        dimension_filter=FilterExpression(
            and_group=FilterExpressionList(expressions=[
                FilterExpression(filter=Filter(
                    field_name="eventName",
                    string_filter=Filter.StringFilter(value=scroll_event),
                )),
                FilterExpression(filter=Filter(
                    field_name="pagePath",
                    string_filter=Filter.StringFilter(
                        match_type=Filter.StringFilter.MatchType.BEGINS_WITH,
                        value="/blog/",
                    ),
                )),
            ])
        ),
        limit=500,
    )
    resp = c.run_report(req)
    return {r.dimension_values[0].value: int(r.metric_values[0].value) for r in resp.rows}


# ---------------------------------------------------------------------------
# Step 5: Campaign confounder check
# ---------------------------------------------------------------------------

# Known campaigns with associated URL patterns (slug keywords)
# Format: (campaign_label, window, [url_keywords])
CAMPAIGNS = [
    # Pre-window
    ("Solus FESS May-13", "pre", ["fess", "nasal", "sinus", "rhinit"]),
    ("Mailchimp Sep-8", "pre", []),
    ("Mailchimp Sep-22", "pre", []),
    ("Mailchimp Sep-28", "pre", []),
    ("Solus Zaditen Sep-30", "pre", ["zaditen", "ketotifen", "allerg"]),
    ("Solus Hydralyte Oct-23", "pre", ["hydralyte", "rehydrat", "electrolyte"]),
    ("Mailchimp Oct-29", "pre", []),
    ("TMR Allergic Rhinitis Nov-4", "pre", ["rhinit", "allerg", "hayfever", "hay-fever"]),
    ("Mailchimp Nov-14", "pre", []),
    # Post-window
    ("Solus Hydralyte Dec-9", "post", ["hydralyte", "rehydrat", "electrolyte"]),
    ("Solus Weekend Read Dec-13", "post", []),
    ("Solus FESS Mar-12", "post", ["fess", "nasal", "sinus", "rhinit"]),
    ("Solus Rectogesic CPD Mar-19", "post", ["rectogesic", "anal", "fissure", "nitroglyc"]),
]


def check_campaign_confounder(path: str, title: str) -> str:
    """Return a confounder flag string if any campaign likely targeted this article."""
    path_lower = path.lower()
    title_lower = title.lower()

    pre_only = []
    post_only = []
    both = []

    for label, window, keywords in CAMPAIGNS:
        if not keywords:
            continue
        hit = any(kw in path_lower or kw in title_lower for kw in keywords)
        if hit:
            if window == "pre":
                pre_only.append(label)
            else:
                post_only.append(label)

    # If same topic appears in both windows, it's balanced
    pre_topics = {label.split()[1].lower() for label, w, _ in CAMPAIGNS if w == "pre" and any(kw in path_lower or kw in title_lower for kw in CAMPAIGNS[[c[0] for c in CAMPAIGNS].index(label)][2])} if False else set()

    flags = []
    for label in pre_only:
        flags.append(f"Pre-only campaign: {label}")
    for label in post_only:
        flags.append(f"Post-only campaign: {label}")

    return "; ".join(flags) if flags else ""


def check_confounder_v2(path: str, title: str) -> str:
    """Check if article matches any campaign keywords, noting which window."""
    path_lower = path.lower()
    title_lower = title.lower()

    matches = []
    for label, window, keywords in CAMPAIGNS:
        if not keywords:
            continue
        if any(kw in path_lower or kw in title_lower for kw in keywords):
            matches.append((label, window))

    if not matches:
        return ""

    pre_matches = [label for label, w in matches if w == "pre"]
    post_matches = [label for label, w in matches if w == "post"]

    # FESS appears in both windows — balanced, not a confounder
    pre_labels = set(label.split()[1].lower() for label in pre_matches)
    post_labels = set(label.split()[1].lower() for label in post_matches)
    shared = pre_labels & post_labels

    flags = []
    for label in pre_matches:
        topic = label.split()[1].lower()
        if topic not in shared:
            flags.append(f"Pre-only: {label}")
    for label in post_matches:
        topic = label.split()[1].lower()
        if topic not in shared:
            flags.append(f"Post-only: {label}")
    if shared:
        balanced = [label for label, w in matches if label.split()[1].lower() in shared]
        flags.append(f"Balanced (both windows): {', '.join(balanced)}")

    return "; ".join(flags)


# ---------------------------------------------------------------------------
# Formatting helpers
# ---------------------------------------------------------------------------

def fmt_secs(secs: float) -> str:
    m = int(secs) // 60
    s = int(secs) % 60
    return f"{m}m{s:02d}s"


def delta_str(before: float, after: float, fmt=None, invert=False) -> str:
    """Show change with direction arrow."""
    delta = after - before
    if invert:
        delta = -delta
    arrow = "▲" if delta > 0 else ("▼" if delta < 0 else "—")
    if fmt == "pct":
        return f"{arrow} {abs(after - before)*100:.1f}pp"
    elif fmt == "secs":
        return f"{arrow} {fmt_secs(abs(after - before))}"
    else:
        return f"{arrow} {abs(after - before):.2f}"


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    out_dir = io.out_dir("ga4")
    out_file = out_dir / "paywall_analysis.md"
    print("Connecting to GA4...", flush=True)
    c = make_client()

    # --- Step 1 ---
    print("Step 1: Pulling daily /blog/ metrics Nov-Dec 2025...", flush=True)
    daily_rows, cutover_date, signal_clear = step1_daily(c)
    print(f"  Detected cutover date: {cutover_date}  (clear signal: {signal_clear})", flush=True)

    # --- Step 2 ---
    print(f"Step 2: Pulling pre-window article metrics (2025-04-01 to {cutover_date})...", flush=True)
    pre_metrics = pull_article_metrics(c, "2025-04-01", cutover_date)
    print(f"  {len(pre_metrics)} articles found in pre window", flush=True)

    print("Step 2: Pulling post-window article metrics ({} to 2026-03-31)...".format(cutover_date), flush=True)
    post_metrics = pull_article_metrics(c, cutover_date, "2026-03-31")
    print(f"  {len(post_metrics)} articles found in post window", flush=True)

    # Qualifying articles: >= 30 users in both windows
    qualifying = []
    all_paths = set(pre_metrics) | set(post_metrics)
    for path in all_paths:
        pre = pre_metrics.get(path, {})
        post = post_metrics.get(path, {})
        pre_users = pre.get("users", 0)
        post_users = post.get("users", 0)
        if pre_users >= 30 and post_users >= 30:
            qualifying.append(path)
    qualifying.sort(key=lambda p: -(pre_metrics[p]["users"] + post_metrics[p]["users"]))
    print(f"  {len(qualifying)} qualifying articles (>= 30 users in both windows)", flush=True)

    # --- Step 3 ---
    print("Step 3: Checking scroll events on /blog/ pages...", flush=True)
    event_rows = step3_scroll_events(c)
    scroll_events = [r for r in event_rows if any(kw in r["event_name"].lower() for kw in ["scroll", "depth", "read"])]
    print(f"  Top events: {[r['event_name'] for r in event_rows[:5]]}", flush=True)
    print(f"  Scroll-like events: {scroll_events}", flush=True)

    # Determine which scroll event to use
    scroll_event = None
    for r in event_rows:
        if r["event_name"].lower() in ("scroll", "scroll_depth", "90_percent_scroll"):
            scroll_event = r["event_name"]
            break
        if "scroll" in r["event_name"].lower():
            scroll_event = r["event_name"]
            break

    # --- Step 4 ---
    pre_scroll = {}
    post_scroll = {}
    if scroll_event:
        print(f"Step 4: Pulling scroll event '{scroll_event}' counts per article...", flush=True)
        pre_scroll = pull_scroll_events_per_article(c, scroll_event, "2025-04-01", cutover_date)
        post_scroll = pull_scroll_events_per_article(c, scroll_event, cutover_date, "2026-03-31")

    # --- Build article table ---
    article_data = []
    for path in qualifying:
        pre = pre_metrics[path]
        post = post_metrics[path]

        pre_eng_per_user = pre["duration"] / pre["users"] if pre["users"] else 0.0
        post_eng_per_user = post["duration"] / post["users"] if post["users"] else 0.0
        pre_bounce = (pre["sessions"] - pre["engaged"]) / pre["sessions"] if pre["sessions"] else 0.0
        post_bounce = (post["sessions"] - post["engaged"]) / post["sessions"] if post["sessions"] else 0.0

        pre_scroll_rate = pre_scroll.get(path, 0) / pre["sessions"] if pre["sessions"] else 0.0
        post_scroll_rate = post_scroll.get(path, 0) / post["sessions"] if post["sessions"] else 0.0

        confounder = check_confounder_v2(path, pre.get("title", "") or post.get("title", ""))

        article_data.append({
            "path": path,
            "title": pre.get("title") or post.get("title", path),
            "pre_users": pre["users"],
            "post_users": post["users"],
            "pre_sessions": pre["sessions"],
            "post_sessions": post["sessions"],
            "pre_engaged": pre["engaged"],
            "post_engaged": post["engaged"],
            "pre_duration": pre["duration"],
            "post_duration": post["duration"],
            "pre_eng_per_user": pre_eng_per_user,
            "post_eng_per_user": post_eng_per_user,
            "pre_bounce": pre_bounce,
            "post_bounce": post_bounce,
            "pre_scroll_rate": pre_scroll_rate,
            "post_scroll_rate": post_scroll_rate,
            "confounder": confounder,
        })

    # --- Weighted aggregate ---
    total_pre_users = sum(a["pre_users"] for a in article_data)
    total_post_users = sum(a["post_users"] for a in article_data)
    total_pre_duration = sum(a["pre_duration"] for a in article_data)
    total_post_duration = sum(a["post_duration"] for a in article_data)
    total_pre_sessions = sum(a["pre_sessions"] for a in article_data)
    total_post_sessions = sum(a["post_sessions"] for a in article_data)
    total_pre_engaged = sum(a["pre_engaged"] for a in article_data)
    total_post_engaged = sum(a["post_engaged"] for a in article_data)

    agg_pre_eng = total_pre_duration / total_pre_users if total_pre_users else 0.0
    agg_post_eng = total_post_duration / total_post_users if total_post_users else 0.0
    agg_pre_bounce = (total_pre_sessions - total_pre_engaged) / total_pre_sessions if total_pre_sessions else 0.0
    agg_post_bounce = (total_post_sessions - total_post_engaged) / total_post_sessions if total_post_sessions else 0.0

    # --- Consistency analysis ---
    improved_eng = sum(1 for a in article_data if a["post_eng_per_user"] > a["pre_eng_per_user"])
    improved_bounce = sum(1 for a in article_data if a["post_bounce"] < a["pre_bounce"])
    n = len(article_data)

    # --- Write output ---
    lines = []
    lines.append("# Paywall Removal Pre/Post Analysis")
    lines.append(f"\n_Generated: {datetime.now().strftime('%Y-%m-%d %H:%M')} | GA4 Property: {PROPERTY_ID}_\n")

    # Step 1
    lines.append("---\n")
    lines.append("## 1. Gate Removal Date\n")
    lines.append("### Daily /blog/ metrics — Nov 1 to Dec 31 2025\n")
    lines.append("| Date | Sessions | Engaged | Users | Bounce Rate | Avg Eng (secs) |")
    lines.append("|------|----------|---------|-------|-------------|----------------|")
    for r in daily_rows:
        marker = " ← **inflection**" if r["date"] == cutover_date and signal_clear else ""
        lines.append(
            f"| {r['date']}{marker} | {r['sessions']} | {r['engaged_sessions']} | {r['total_users']} "
            f"| {r['bounce_rate']:.1%} | {r['avg_engagement_secs']:.0f}s |"
        )

    lines.append("")
    if signal_clear:
        lines.append(f"**Detected cutover: {cutover_date}** — clear inflection point found (bounce rate drop and/or engagement rise sustained over 3-day window).\n")
    else:
        lines.append(f"**No clear inflection point detected.** Defaulting to **2025-12-01** as the cutover date. The data does not show a strong step-change in November–December; the gate removal may have been gradual, or traffic volume on /blog/ was too low to surface a clean signal.\n")

    lines.append(f"> Pre window: **2025-04-01 → {cutover_date}**")
    lines.append(f"> Post window: **{cutover_date} → 2026-03-31**\n")

    # Step 3
    lines.append("---\n")
    lines.append("## 2. Scroll Events Tracked on /blog/ Pages\n")
    lines.append("Top 20 events on /blog/* (full year Apr 2025 – Mar 2026):\n")
    lines.append("| Event Name | Count |")
    lines.append("|-----------|-------|")
    for r in event_rows:
        lines.append(f"| `{r['event_name']}` | {r['event_count']:,} |")

    lines.append("")
    if scroll_event:
        lines.append(f"**Scroll event found:** `{scroll_event}` — used for scroll rate analysis below.\n")
        if scroll_event == "scroll":
            lines.append("> GA4's built-in `scroll` event fires at **90% page depth** by default.\n")
    else:
        lines.append("**No scroll event found.** Scroll rate column omitted from article table.\n")

    # Step 4 — article table
    lines.append("---\n")
    lines.append("## 3. Per-Article Pre/Post Comparison\n")
    lines.append(f"Qualifying criteria: ≥ 30 users in both pre and post windows. **{n} articles qualify.**\n")

    if scroll_event:
        header = ("| Article | Pre users | Post users | Pre eng/user | Post eng/user | Δ eng "
                  "| Pre bounce | Post bounce | Δ bounce | Pre scroll% | Post scroll% | Campaign confounder? |")
        sep = ("|---------|-----------|------------|--------------|---------------|-------"
               "|------------|-------------|----------|------------|------------|---------------------|")
    else:
        header = ("| Article | Pre users | Post users | Pre eng/user | Post eng/user | Δ eng "
                  "| Pre bounce | Post bounce | Δ bounce | Campaign confounder? |")
        sep = ("|---------|-----------|------------|--------------|---------------|-------"
               "|------------|-------------|----------|---------------------|")

    lines.append(header)
    lines.append(sep)

    for a in article_data:
        title_short = a["title"][:55].rstrip() if a["title"] else a["path"]
        if len(a["title"]) > 55:
            title_short += "…"
        delta_eng = a["post_eng_per_user"] - a["pre_eng_per_user"]
        delta_bounce = a["post_bounce"] - a["pre_bounce"]
        eng_arrow = "▲" if delta_eng > 0 else ("▼" if delta_eng < 0 else "—")
        bounce_arrow = "▲" if delta_bounce > 0 else ("▼" if delta_bounce < 0 else "—")

        confounder_cell = a["confounder"] if a["confounder"] else ""

        if scroll_event:
            lines.append(
                f"| {title_short} | {a['pre_users']} | {a['post_users']} "
                f"| {fmt_secs(a['pre_eng_per_user'])} | {fmt_secs(a['post_eng_per_user'])} "
                f"| {eng_arrow} {fmt_secs(abs(delta_eng))} "
                f"| {a['pre_bounce']:.1%} | {a['post_bounce']:.1%} "
                f"| {bounce_arrow} {abs(delta_bounce)*100:.1f}pp "
                f"| {a['pre_scroll_rate']:.1%} | {a['post_scroll_rate']:.1%} "
                f"| {confounder_cell} |"
            )
        else:
            lines.append(
                f"| {title_short} | {a['pre_users']} | {a['post_users']} "
                f"| {fmt_secs(a['pre_eng_per_user'])} | {fmt_secs(a['post_eng_per_user'])} "
                f"| {eng_arrow} {fmt_secs(abs(delta_eng))} "
                f"| {a['pre_bounce']:.1%} | {a['post_bounce']:.1%} "
                f"| {bounce_arrow} {abs(delta_bounce)*100:.1f}pp "
                f"| {confounder_cell} |"
            )

    # Weighted aggregate row
    delta_agg_eng = agg_post_eng - agg_pre_eng
    delta_agg_bounce = agg_post_bounce - agg_pre_bounce
    eng_arrow = "▲" if delta_agg_eng > 0 else ("▼" if delta_agg_eng < 0 else "—")
    bounce_arrow = "▲" if delta_agg_bounce > 0 else ("▼" if delta_agg_bounce < 0 else "—")

    if scroll_event:
        total_pre_scroll = sum(pre_scroll.get(a["path"], 0) for a in article_data)
        total_post_scroll = sum(post_scroll.get(a["path"], 0) for a in article_data)
        agg_pre_scroll_rate = total_pre_scroll / total_pre_sessions if total_pre_sessions else 0.0
        agg_post_scroll_rate = total_post_scroll / total_post_sessions if total_post_sessions else 0.0
        lines.append(
            f"| **WEIGHTED AGGREGATE** | **{total_pre_users}** | **{total_post_users}** "
            f"| **{fmt_secs(agg_pre_eng)}** | **{fmt_secs(agg_post_eng)}** "
            f"| **{eng_arrow} {fmt_secs(abs(delta_agg_eng))}** "
            f"| **{agg_pre_bounce:.1%}** | **{agg_post_bounce:.1%}** "
            f"| **{bounce_arrow} {abs(delta_agg_bounce)*100:.1f}pp** "
            f"| **{agg_pre_scroll_rate:.1%}** | **{agg_post_scroll_rate:.1%}** "
            f"| |"
        )
    else:
        lines.append(
            f"| **WEIGHTED AGGREGATE** | **{total_pre_users}** | **{total_post_users}** "
            f"| **{fmt_secs(agg_pre_eng)}** | **{fmt_secs(agg_post_eng)}** "
            f"| **{eng_arrow} {fmt_secs(abs(delta_agg_eng))}** "
            f"| **{agg_pre_bounce:.1%}** | **{agg_post_bounce:.1%}** "
            f"| **{bounce_arrow} {abs(delta_agg_bounce)*100:.1f}pp** "
            f"| |"
        )

    lines.append(f"\n_Engagement direction: {improved_eng}/{n} articles improved. Bounce direction: {improved_bounce}/{n} articles improved._\n")

    # Step 5 — campaign confounder details
    lines.append("---\n")
    lines.append("## 4. Campaign Confounder Detail\n")
    lines.append("Campaigns cross-referenced against article URLs/titles. 'Pre-only' or 'Post-only' means that campaign ran in only one window, making a like-for-like comparison unreliable for those articles.\n")
    lines.append("| Article | Confounder note |")
    lines.append("|---------|----------------|")
    for a in article_data:
        title_short = a["title"][:60].rstrip() if a["title"] else a["path"]
        confounder = a["confounder"] if a["confounder"] else "None identified"
        lines.append(f"| {title_short} | {confounder} |")

    lines.append("")
    lines.append("**Campaigns with no URL keyword match** (Mailchimp Sep 8/22/28, Oct 29, Nov 14; Solus Weekend Read Dec 13) are not mappable to specific articles without click-level UTM data. These are omitted from the per-article flags above but may have driven un-attributed traffic that confounds the aggregate.\n")

    # Step 6 — verdict
    lines.append("---\n")
    lines.append("## 5. Verdict\n")

    # Build honest verdict
    verdict_parts = []

    eng_direction = delta_agg_eng > 0
    bounce_direction = delta_agg_bounce < 0  # bounce dropping is good
    eng_consistency = improved_eng / n if n else 0
    bounce_consistency = improved_bounce / n if n else 0

    # Magnitude check: meaningful = > 15 secs change per user
    eng_meaningful = abs(delta_agg_eng) >= 15
    bounce_meaningful = abs(delta_agg_bounce) >= 0.05  # 5pp

    # Period length imbalance
    pre_days = (datetime.strptime(cutover_date, "%Y-%m-%d") - datetime(2025, 4, 1)).days
    post_days = (datetime(2026, 3, 31) - datetime.strptime(cutover_date, "%Y-%m-%d")).days

    confounded_articles = [a for a in article_data if a["confounder"]]
    confounded_n = len(confounded_articles)

    lines.append("### Summary\n")
    lines.append(f"- **Engagement per user:** {fmt_secs(agg_pre_eng)} pre → {fmt_secs(agg_post_eng)} post "
                 f"({eng_arrow} {fmt_secs(abs(delta_agg_eng))})")
    lines.append(f"- **Bounce rate (aggregate):** {agg_pre_bounce:.1%} pre → {agg_post_bounce:.1%} post "
                 f"({bounce_arrow} {abs(delta_agg_bounce)*100:.1f}pp)")
    lines.append(f"- **Consistency:** {improved_eng}/{n} articles improved on engagement; "
                 f"{improved_bounce}/{n} improved on bounce rate")
    lines.append(f"- **Articles with campaign confounders:** {confounded_n}/{n}")
    lines.append(f"- **Window lengths:** pre = {pre_days} days, post = {post_days} days\n")

    lines.append("### Assessment\n")

    signals_for = []
    signals_against = []
    caveats = []

    if eng_direction and eng_meaningful:
        signals_for.append(f"Engagement per user improved by {fmt_secs(abs(delta_agg_eng))} in aggregate — above the 15s materiality threshold.")
    elif eng_direction:
        caveats.append(f"Engagement per user increased by {fmt_secs(abs(delta_agg_eng))} but this is below the 15s materiality threshold — marginal.")
    else:
        signals_against.append(f"Engagement per user declined by {fmt_secs(abs(delta_agg_eng))} post gate removal.")

    if bounce_direction and bounce_meaningful:
        signals_for.append(f"Bounce rate dropped by {abs(delta_agg_bounce)*100:.1f}pp in aggregate — meaningful reduction.")
    elif bounce_direction:
        caveats.append(f"Bounce rate dropped by {abs(delta_agg_bounce)*100:.1f}pp — directionally positive but below the 5pp materiality threshold.")
    else:
        signals_against.append(f"Bounce rate increased by {abs(delta_agg_bounce)*100:.1f}pp post gate removal.")

    if eng_consistency >= 0.7:
        signals_for.append(f"Engagement improvement is consistent: {improved_eng}/{n} articles improved ({eng_consistency:.0%}).")
    elif eng_consistency < 0.5:
        signals_against.append(f"Engagement improvement is inconsistent: only {improved_eng}/{n} articles improved ({eng_consistency:.0%}).")

    if confounded_n > 0:
        caveats.append(
            f"{confounded_n} of {n} qualifying articles had campaign activity in only one window. "
            "These articles' pre/post comparison may reflect campaign timing rather than the gate change."
        )

    if abs(pre_days - post_days) > 30:
        caveats.append(
            f"Window lengths differ significantly: {pre_days} days pre vs {post_days} days post. "
            "Seasonal effects (Q4 vs Q1 HCP activity patterns) may inflate or suppress the post-window metrics."
        )

    if not signal_clear:
        caveats.append(
            "The exact gate removal date could not be confirmed from traffic data alone. "
            "Using 2025-12-01 as default; if the actual date was later (e.g. Dec 8–15), "
            "the pre window includes some ungated traffic and the effect will be understated."
        )

    if signals_for:
        lines.append("**Evidence supporting 'gate removal improved engagement':**")
        for s in signals_for:
            lines.append(f"- {s}")
        lines.append("")

    if signals_against:
        lines.append("**Evidence against:**")
        for s in signals_against:
            lines.append(f"- {s}")
        lines.append("")

    if caveats:
        lines.append("**Caveats and confounders:**")
        for s in caveats:
            lines.append(f"- {s}")
        lines.append("")

    # Final call
    positive_signals = len(signals_for)
    negative_signals = len(signals_against)

    if positive_signals > negative_signals and eng_consistency >= 0.6:
        verdict = (
            "**The data moderately supports the claim that removing the gate improved engagement.** "
            "Both engagement time and bounce rate moved in the right direction for the majority of qualifying articles. "
            "The effect is not large enough to be conclusive given the window length difference and campaign confounders, "
            "but the direction is consistent."
        )
    elif positive_signals == negative_signals or (positive_signals > 0 and eng_consistency < 0.6):
        verdict = (
            "**The data is mixed — a clear verdict is not supported.** "
            "Aggregate metrics move in the expected direction, but consistency across articles is low and "
            "confounders (campaign timing, window length differences) are substantial enough that the gate removal "
            "cannot be confidently credited for the observed changes."
        )
    else:
        verdict = (
            "**The data does not support the claim that removing the gate improved engagement.** "
            "The majority of metrics moved in the wrong direction or showed no meaningful change. "
            "Other factors likely dominate the variance in this dataset."
        )

    lines.append(f"### Final verdict\n\n{verdict}\n")
    lines.append("---\n")
    lines.append(f"_Analysis window: pre = 2025-04-01 to {cutover_date}, post = {cutover_date} to 2026-03-31_\n")

    content = "\n".join(lines) + "\n"

    out_file.write_text(content, encoding="utf-8")
    print(f"\nWrote {out_file}", flush=True)

    # Copy to Windows Downloads
    io.copy_to_downloads(out_file)

    print("\n=== DONE ===", flush=True)


if __name__ == "__main__":
    main()

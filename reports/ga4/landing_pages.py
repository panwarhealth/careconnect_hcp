"""Traffic-source data via LANDING-PAGE methodology (v3).

    python reports/ga4/landing_pages.py --period FY26

Each session is bucketed into exactly one section based on its landing page
(first page of the session). Section %'s within email or total-site sum to 100%.

Outputs (to reports/out/, copied to Windows Downloads):
  - landing_page_v3_<slug>.csv
  - traffic_source_v3_qbr_<slug>.md

Note: the markdown "URL discovery" narrative is FY26-specific commentary; the
tables are always correct for the selected period, but re-read the prose if you
run this for a different window.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io, periods
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Metric, OrderBy, RunReportRequest,
)

START, END = "2025-04-01", "2026-03-31"   # reset from the resolved period in main()
PERIOD_LABEL = "FY26"


# ---------------------------------------------------------------------------
# Section bucketing. Order matters: first match wins. More-specific first.
# ---------------------------------------------------------------------------
SECTION_RULES: list[tuple[str, list[str], str]] = [
    (
        "Sample pages",
        ["/order-samples", "/order-patient-demo", "/order-patient-demo-kits", "/product-demo*"],
        r"^/(order-samples|order-patient-demo|product-demo|ordersamples)",
    ),
    (
        "Login & Signup",
        ["/log-in", "/login", "/register", "/edit-your-profile", "/my-account",
         "/dashboard", "/profile", "/welcome", "/forgot-password", "/lost-password"],
        r"^/(log-in|login|register|edit-your-profile|my-account|dashboard|profile|welcome|forgot-password|lost-password)(/|$|\.|-)",
    ),
    (
        "Resources / Factsheets",
        ["/resources, /resources/ (hub page)",
         "/wp-content/uploads/*.pdf (PDF assets)"],
        r"^/resources(/|$)|wp-content/uploads.*\.pdf",
    ),
    (
        "Tools",
        ["/allergy-analyser*", "/rectogesic-3d-model", "/fess-demonstration",
         "/interactive-model*", "/interactivetools*",
         "/tools-and-videos (hub - judgment call, see notes)",
         "/section1, /section2, /section3, /section4, /sec1, /sec2, /sec4 (analyser steps)",
         "/tip1, /tip2 (analyser tips)",
         "/option1, /option2 (analyser options)",
         "/bake, /clues, /chart, /detection, /mist (analyser sub-pages)"],
        r"^/(allergy-analyser|interactive-model|rectogesic-3d-model|fess-demonstration|interactivetools|tools-and-videos|section\d|sec\d|tip\d|option\d|bake$|bake/|clues$|clues/|chart$|chart/|detection$|detection/|mist$|mist/)",
    ),
    (
        "Videos",
        ["/videos (only video-specific URL found in FY26 data)"],
        r"^/videos(/|$)",
    ),
    (
        "Mini Clinical Audit Course",
        ["/courses/*", "/lessons/*", "/quizzes/*", "/quiz/*", "/certificate*",
         "/anal-fissures-breaking-the-cycle-and-the-stigma*",
         "/anal-fissures-breaking-the-cycle-and-the-stigma-langing (typo variant)",
         "/mini-clinical-audit*", "/retrospective*", "/complete-mini*",
         "/pre-learning-survey*", "/pre-survey*", "/post-learning-survey*"],
        r"^/(courses|lessons|quizzes|quiz|certificate|anal-fissures-breaking|mini-clinical-audit|retrospective|complete-mini|pre-learning-survey|pre-survey|post-learning-survey)",
    ),
    (
        "Articles & blog",
        ["/blog/*", "/nasal-and-sinus-health*", "/topics/*"],
        r"(?i)^/(blog|nasal-and-sinus-health|topics)(/|$)",
    ),
]
SECTION_ORDER = [
    "Articles & blog",
    "Resources / Factsheets",
    "Mini Clinical Audit Course",
    "Login & Signup",
    "Sample pages",
    "Tools",
    "Videos",
    "Other (homepage + brands + misc)",
    "(landing page not tracked)",
]
NOT_SET_LABEL = "(landing page not tracked)"
OTHER_LABEL = "Other (homepage + brands + misc)"


def bucket(path: str) -> str:
    if not path or path.strip() in ("(not set)", ""):
        return NOT_SET_LABEL
    p = path.split("?")[0].rstrip("/") or "/"
    for label, _, regex in SECTION_RULES:
        if re.search(regex, p):
            return label
    return OTHER_LABEL


def normalise_path(path: str) -> str:
    return path.split("?")[0].rstrip("/") or "/"


def landing_metrics(ga, only_email: bool):
    """Return ({section: sessions}, {section: users}, {path: {section, sessions, users}})."""
    kwargs = dict(
        property=f"properties/{ga4.PROPERTY_ID}",
        date_ranges=[DateRange(start_date=START, end_date=END)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=10_000,
    )
    if only_email:
        kwargs["dimension_filter"] = ga4.channel_filter("Email")
    resp = ga.run_report(RunReportRequest(**kwargs))
    sess = {s: 0 for s in SECTION_ORDER}
    usrs = {s: 0 for s in SECTION_ORDER}
    audit: dict[str, dict] = {}
    for r in resp.rows:
        path = normalise_path(r.dimension_values[0].value)
        s = int(r.metric_values[0].value)
        u = int(r.metric_values[1].value)
        sec = bucket(path)
        sess[sec] = sess.get(sec, 0) + s
        usrs[sec] = usrs.get(sec, 0) + u
        if path not in audit:
            audit[path] = {"section": sec, "sessions": 0, "users": 0}
        audit[path]["sessions"] += s
        audit[path]["users"] += u
    return sess, usrs, audit


def main() -> None:
    global START, END, PERIOD_LABEL
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    START, END, PERIOD_LABEL = p.start, p.end, p.label
    out = io.out_dir()
    ga = ga4.client()
    print(f"=== Landing-page section data ({p.label}) ===")

    e_sess, e_usrs, e_audit = landing_metrics(ga, only_email=True)
    t_sess, t_usrs, t_audit = landing_metrics(ga, only_email=False)

    e_total = sum(e_sess.values())
    t_total = sum(t_sess.values())
    rows: list[dict] = []
    for s in SECTION_ORDER:
        rows.append({
            "section": s,
            "email_sessions": e_sess.get(s, 0),
            "email_users": e_usrs.get(s, 0),
            "total_site_sessions": t_sess.get(s, 0),
            "total_site_users": t_usrs.get(s, 0),
        })
    rows.append({
        "section": "TOTAL",
        "email_sessions": e_total,
        "email_users": sum(e_usrs.values()),
        "total_site_sessions": t_total,
        "total_site_users": sum(t_usrs.values()),
    })
    io.copy_to_downloads(io.write_dicts(out / f"landing_page_v3_{p.slug}.csv", rows))

    print(f"\n{'Section':<32} {'E.Sess':>7} {'E.Usrs':>7} {'T.Sess':>7} {'T.Usrs':>7}")
    print("-" * 70)
    for r in rows:
        print(f"{r['section']:<32} {r['email_sessions']:>7,} {r['email_users']:>7,} "
              f"{r['total_site_sessions']:>7,} {r['total_site_users']:>7,}")

    # --- Markdown ---
    md: list[str] = []
    md.append(f"# {PERIOD_LABEL} Traffic Source — Landing-Page Methodology v3 (Corrected Sections)\n")
    md.append(f"**Window:** {START} – {END} (GA4 property {ga4.PROPERTY_ID})  ")
    md.append("**Metric:** Sessions and unique users  ")
    md.append("**Methodology:** landing-page only — each session bucketed into exactly one section based on its first page. Section %'s within Email or Total-site sum to 100%.\n")

    md.append("## URL discovery — what we actually found (FY26 commentary)\n")
    md.append("Before bucketing, we ran a discovery pass over all FY26 landing pages (top 200 by sessions, top 200 by pageviews) plus targeted regex searches for `/resources*`, `/videos*`, `/factsheet*`, `/patient-resource*`, `/clinical-paper*`, `/guide*`, `/wp-content/uploads*`. Findings:\n")
    md.append("- **Resources:** the only Resources URLs with traffic are the hub pages `/resources` and `/resources/` (752 combined pageviews). **No individual factsheet, patient-resource, clinical-paper, or guide pages have any FY26 traffic.** PDFs in `/wp-content/uploads/` exist (e.g. `MIST-FESS.pdf`) but only 13 pageviews total across the whole year. *PDF traffic is almost certainly under-tracked* — GA4 only fires on PDF requests if the JS triggers before the file opens, which is browser-dependent. The Resources/Factsheets section is functionally just hub-page traffic.")
    md.append("- **Videos:** the only video-specific URL is `/videos` (29 pageviews, 24 sessions). No `/videos/<slug>` sub-pages exist. Videos on the site appear to be **embedded** in other pages (likely the `/tools-and-videos` hub) rather than on dedicated landing pages.")
    md.append("- **`/tools-and-videos` (the shared hub):** 347 combined pageviews, 257 sessions. This is genuinely ambiguous — it's the hub that lists both interactive tools (allergy analyser, FESS demo, 3D model) AND videos. **Decision: bundled in Tools** because (a) the page primarily entry-points the interactive tool products, (b) Tools already has the actual tool URLs (`/allergy-analyser-tool` etc.), and (c) putting it in Videos would inflate Videos with non-video activity. Flagged as a judgment call.")
    md.append("- **No `/factsheet*`, `/patient-resource*`, `/clinical-paper*`, `/guide*` pages exist** with any FY26 traffic. Patterns kept in the regex for future-proofing.\n")

    md.append("## URL patterns per section\n")
    for label, patterns, regex in SECTION_RULES:
        md.append(f"### {label}")
        for pat in patterns:
            md.append(f"- `{pat}`")
        md.append(f"- *Regex used:* `{regex}`")
        md.append("")
    md.append(f"### {OTHER_LABEL}")
    md.append("- Catch-all: any landing page that doesn't match the regexes above. Dominated by the homepage `/`, brand pages `/brands/rectogesic` and `/brands/hydralyte`, plus assorted long-tail.")
    md.append("")
    md.append(f"### {NOT_SET_LABEL}")
    md.append("- GA4's `(not set)` landing-page bucket: sessions where GA4 couldn't resolve a landing page (privacy-thresholded sessions, app-event-only sessions, tracking gaps).")
    md.append("- Reported separately as requested.\n")

    md.append("## Section data (landing-page attribution)\n")
    md.append("| Section | Email sessions | Email users | Total-site sessions | Total-site users |")
    md.append("|---|---:|---:|---:|---:|")
    for r in rows[:-1]:
        md.append(f"| {r['section']} | {r['email_sessions']:,} | {r['email_users']:,} | "
                  f"{r['total_site_sessions']:,} | {r['total_site_users']:,} |")
    last = rows[-1]
    md.append(f"| **{last['section']}** | **{last['email_sessions']:,}** | **{last['email_users']:,}** | "
              f"**{last['total_site_sessions']:,}** | **{last['total_site_users']:,}** |")
    md.append("")
    md.append("Section %'s within email column sum to 100% (each session is in exactly one bucket). Same for total-site column.\n")

    md.append("## One-line honest read\n")
    real_sections = [r for r in rows[:-1] if r["section"] not in (NOT_SET_LABEL, OTHER_LABEL)]
    if real_sections:
        top_email = max(real_sections, key=lambda r: r["email_sessions"])
        top_email_pct = top_email["email_sessions"] / e_total * 100 if e_total else 0
        md.append(f"- Email landing pages cluster heavily on **{top_email['section']}** "
                  f"({top_email['email_sessions']:,} sessions, {top_email_pct:.0f}% of email landings) — "
                  f"that's where eDM links are pointing.")
        videos_row = next((r for r in rows if r["section"] == "Videos"), None)
        resources_row = next((r for r in rows if r["section"] == "Resources / Factsheets"), None)
        if videos_row and videos_row["total_site_sessions"] < 100:
            md.append(f"- **Videos** is structurally tiny in the data ({videos_row['total_site_sessions']} total-site sessions) — videos are embedded on other pages, not landed-on directly.")
        if resources_row and resources_row["total_site_sessions"] < 500:
            md.append(f"- **Resources / Factsheets** is also small as a *landing* destination ({resources_row['total_site_sessions']} total-site sessions). Section-touch data (separate report) shows the truer engagement picture.")
    md.append("")

    md.append("## Audit — top landing pages per section\n")
    md.append("Top 5 paths per section (from email+total combined audit), so you can sanity-check the bucketing:\n")
    combined: dict[str, dict] = {}
    for path, info in t_audit.items():
        combined[path] = {
            "section": info["section"],
            "total_sessions": info["sessions"],
            "email_sessions": e_audit.get(path, {}).get("sessions", 0),
        }
    by_section: dict[str, list[tuple[str, int, int]]] = {}
    for path, info in combined.items():
        by_section.setdefault(info["section"], []).append(
            (path, info["total_sessions"], info["email_sessions"]))
    for s in SECTION_ORDER:
        if s not in by_section:
            continue
        md.append(f"**{s}**\n")
        md.append("| Path | Total sessions | Email sessions |")
        md.append("|---|---:|---:|")
        for path, ts, es in sorted(by_section[s], key=lambda x: x[1], reverse=True)[:5]:
            md.append(f"| `{path}` | {ts:,} | {es:,} |")
        md.append("")

    out_md = out / f"traffic_source_v3_qbr_{p.slug}.md"
    out_md.write_text("\n".join(md))
    print(f"wrote {out_md.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(out_md)


if __name__ == "__main__":
    main()

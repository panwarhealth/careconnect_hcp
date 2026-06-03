"""Build a clean markdown for Claude Work from the MC + GA4 merge.

Per-campaign:
  - send date, title, subject, recipients
  - opens, open rate, clicks, click rate
  - per-URL clicks table (deduped by canonical path)
  - for campaigns where Mailchimp click-details is missing, GA4 fills in.

Reads (default-window output of reports/mailchimp/url_merge.py):
  reports/out/mailchimp/mc_ga4_url_merge_2025-01-01_2026-05-01.csv
  reports/out/mailchimp/mc_ga4_summary_2025-01-01_2026-05-01.csv

Writes:
  reports/out/edm/edm_campaigns_sep_nov_2025.md

    python reports/edm/build_md.py
"""
from __future__ import annotations

import csv
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io

MERGE_DIR = io.out_dir("mailchimp")
MERGE_SLUG = "2025-01-01_2026-05-01"
OUT = io.out_dir("edm")


def fmt_url(host: str, path: str) -> str:
    if not host and not path:
        return "(homepage / no path)"
    if not path:
        return host or "(unknown)"
    return path or "/"


def main() -> None:
    summary = list(csv.DictReader(open(MERGE_DIR / f"mc_ga4_summary_{MERGE_SLUG}.csv")))
    urls = list(csv.DictReader(open(MERGE_DIR / f"mc_ga4_url_merge_{MERGE_SLUG}.csv")))

    # Dedup URL rows per (campaign_id, canonical_path) — sum MC clicks/uniques
    # Some Mailchimp campaigns have multiple URL variants (different merge tags,
    # tracking prefixes) that all resolve to the same canonical path. Sum them.
    grouped: dict[tuple[str, str, str], dict] = {}
    for r in urls:
        key = (r["campaign_id"], r["url_host"], r["url_path"])
        if key not in grouped:
            grouped[key] = {
                "campaign_id": r["campaign_id"],
                "url_host": r["url_host"],
                "url_path": r["url_path"],
                "mc_total_clicks": 0,
                "mc_unique_clickers": 0,
                "ga4_users": 0,
                "ga4_sessions": 0,
                "is_external": r["status"] == "external_url",
                "mc_missing": r["status"] == "ga4_only_mc_missing",
            }
        g = grouped[key]
        g["mc_total_clicks"] += int(r["mc_total_clicks"] or 0)
        g["mc_unique_clickers"] += int(r["mc_unique_clickers"] or 0)
        # GA4 figures repeat across URL variants for the same path — take max, not sum
        g["ga4_users"] = max(g["ga4_users"], int(r["ga4_users"] or 0))
        g["ga4_sessions"] = max(g["ga4_sessions"], int(r["ga4_sessions"] or 0))

    # Group by campaign
    by_campaign: dict[str, list[dict]] = defaultdict(list)
    for g in grouped.values():
        by_campaign[g["campaign_id"]].append(g)

    lines: list[str] = []
    lines.append("# Care Connect HCP — Mailchimp eDM campaigns, Sep–Nov 2025")
    lines.append("")
    lines.append(
        "Six Mailchimp eDM campaigns sent in the QBR window. For each campaign: "
        "send stats + the URLs that were clicked through, with click counts."
    )
    lines.append("")
    lines.append(
        "**Data sources:** Mailchimp click-details for per-URL click counts. "
        "**Exception:** for the 22 Sep Tatersall send, Mailchimp's per-URL data is missing "
        "(known anomaly — aggregate counters intact, per-URL breakdown empty), so GA4 "
        "session/user data is shown instead. GA4 takes precedence on conflicts because "
        "it counts actual page loads, not just bot/scanner click pixels."
    )
    lines.append("")

    # ---- Headline numbers table ----
    lines.append("## Headline numbers")
    lines.append("")
    lines.append(
        "| Send date | Title | Subject | Sent | Unique opens | Open rate | "
        "Unique clickers | Click rate |"
    )
    lines.append(
        "|---|---|---|---:|---:|---:|---:|---:|"
    )
    for s in summary:
        title = s["title"] or "—"
        subject = s["subject"] or "—"
        lines.append(
            f"| {s['send_date']} | {title} | {subject} | "
            f"{s['recipients']} | {s['mc_unique_opens']} | "
            f"{s['mc_open_rate_pct']}% | {s['mc_unique_clickers']} | "
            f"{s['mc_click_rate_pct']}% |"
        )
    lines.append("")

    # ---- Per-campaign URL breakdown ----
    lines.append("## Per-campaign URL click breakdown")
    lines.append("")

    for s in summary:
        cid = s["campaign_id"]
        title = s["title"] or "(no campaign title set in Mailchimp)"
        subject = s["subject"] or "(no subject set)"
        lines.append(f"### {s['send_date']} — {title}")
        lines.append("")
        lines.append(f"- **Subject:** {subject}")
        lines.append(f"- **Sent to:** {s['recipients']} recipients")
        lines.append(
            f"- **Opens:** {s['mc_unique_opens']} unique ({s['mc_open_rate_pct']}% open rate)"
        )
        lines.append(
            f"- **Clicks:** {s['mc_unique_clickers']} unique clickers "
            f"({s['mc_click_rate_pct']}% click rate)"
        )
        lines.append(f"- **UTM tag:** `{s['utm_tag'] or '(none)'}`")
        lines.append("")

        rows = by_campaign.get(cid, [])

        # Tatersall special case — MC missing, use GA4
        is_mc_missing = any(r["mc_missing"] for r in rows)

        if is_mc_missing:
            lines.append(
                "*Mailchimp per-URL click data missing for this campaign — table below "
                "uses GA4 session/user data instead.*"
            )
            lines.append("")
            lines.append("| URL path | GA4 sessions | GA4 unique users |")
            lines.append("|---|---:|---:|")
            ga_rows = [r for r in rows if r["mc_missing"]]
            ga_rows.sort(key=lambda r: -r["ga4_users"])
            for r in ga_rows:
                path = r["url_path"] or "(homepage)"
                lines.append(f"| `{path}` | {r['ga4_sessions']} | {r['ga4_users']} |")
            total_sessions = sum(r["ga4_sessions"] for r in ga_rows)
            total_users = sum(r["ga4_users"] for r in ga_rows)
            lines.append(f"| **Total** | **{total_sessions}** | **{total_users}** |")
            lines.append("")
        else:
            lines.append("| URL | Total clicks | Unique clickers |")
            lines.append("|---|---:|---:|")
            mc_rows = [r for r in rows if not r["mc_missing"]]
            mc_rows.sort(key=lambda r: -r["mc_unique_clickers"])
            for r in mc_rows:
                if r["is_external"]:
                    label = f"{r['url_host']}{r['url_path']} *(external)*"
                else:
                    path = r["url_path"] or "/"
                    label = f"`{path}`"
                lines.append(
                    f"| {label} | {r['mc_total_clicks']} | {r['mc_unique_clickers']} |"
                )
            total_clicks = sum(r["mc_total_clicks"] for r in mc_rows)
            total_unique = sum(r["mc_unique_clickers"] for r in mc_rows)
            lines.append(f"| **Total** | **{total_clicks}** | **{total_unique}** |")
            lines.append("")

    md_path = OUT / "edm_campaigns_sep_nov_2025.md"
    md_path.write_text("\n".join(lines) + "\n")
    print(f"Wrote {md_path}")


if __name__ == "__main__":
    main()

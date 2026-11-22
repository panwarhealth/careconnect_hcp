"""Sign-ups and HCP engagement by eDM, exact, from our own tracking on PROD.

    reports/.venv/bin/python reports/db/edm_tracking.py --period FY27Q3

Reads what the hcp-analytics plugin records (live 18 Nov 2026), see
docs/ANALYTICS_MEASUREMENT_PLAN.md "Our database":
  - signups_by_source     real-HCP sign-ups in the period by campaign of the
                          sign-up visit; accounts created before tracking went
                          live are "(not tracked)", not "(no campaign)"
  - signup_landing_pages  first page seen before signing up
  - signup_first_campaign first campaign in the journey before signing up
  - engagement_by_campaign HCPs, pages and views per campaign, by content type
  - engagement_hcps       one row per HCP x campaign x page (user ID and
                          profession only, no names or emails)

Engagement rows count from the day a page was first seen under a campaign
(views are cumulative per row and can't be split across quarters).
All dates are Sydney time. Staff accounts are excluded.
"""
from __future__ import annotations

import argparse
import sys
from collections import Counter, defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io, periods, prod_db

TRACKING_LIVE = "2026-11-18"
UNTRACKED = "__untracked__"  # no sign-up meta: account created before tracking
STAFF_EMAIL = r"@(carepharma|tbstdigital|panwarhealth|rnafiel|rscottdigital)\\.com\\.au$"
STAFF_ROLES = ("administrator", "editor", "author", "contributor", "hcp_mca_reviewer")

CPD_TYPES = {"sfwd-courses", "sfwd-lessons", "sfwd-topic", "sfwd-quiz"}
TYPE_LABELS = {"post": "article", "resources": "resource", "video": "video", "brands": "brand"}


def content_type(post_type: str, slug: str) -> str:
    if post_type in CPD_TYPES:
        return "cpd"
    if post_type == "page":
        return "samples" if "sample" in slug else "page"
    return TYPE_LABELS.get(post_type, post_type or "unknown")


def _staff_filter(user_col: str) -> str:
    roles = " OR ".join(f"cap.meta_value LIKE '%\"{r}\"%'" for r in STAFF_ROLES)
    return (f"{user_col} NOT IN (SELECT cap.user_id FROM tbstwp_usermeta cap "
            f"WHERE cap.meta_key = 'tbstwp_capabilities' AND ({roles}))")


def _meta(key: str, alias: str) -> str:
    return f"LEFT JOIN tbstwp_usermeta {alias} ON {alias}.user_id = u.ID AND {alias}.meta_key = '{key}'"


def signups(p: periods.Period) -> list[dict]:
    reg_local = "CONVERT_TZ(u.user_registered, 'UTC', 'Australia/Sydney')"
    return prod_db.query(f"""
        SELECT u.ID AS user_id, DATE({reg_local}) AS registered,
               IFNULL(src.meta_value, '{UNTRACKED}') AS src, IFNULL(med.meta_value, '{UNTRACKED}') AS med,
               IFNULL(camp.meta_value, '{UNTRACKED}') AS camp,
               IFNULL(land.meta_value, '') AS landing,
               IFNULL(fsrc.meta_value, '') AS first_src, IFNULL(fcamp.meta_value, '') AS first_camp
        FROM tbstwp_users u
        {_meta('hcp_signup_utm_source', 'src')}
        {_meta('hcp_signup_utm_medium', 'med')}
        {_meta('hcp_signup_utm_campaign', 'camp')}
        {_meta('hcp_signup_landing_page', 'land')}
        {_meta('hcp_signup_first_utm_source', 'fsrc')}
        {_meta('hcp_signup_first_utm_campaign', 'fcamp')}
        WHERE {reg_local} >= '{p.start}' AND {reg_local} < DATE_ADD('{p.end}', INTERVAL 1 DAY)
          AND u.user_email NOT REGEXP '{STAFF_EMAIL}'
          AND {_staff_filter('u.ID')}
        ORDER BY u.ID;
    """)


def engagement(p: periods.Period) -> list[dict]:
    return prod_db.query(f"""
        SELECT e.user_id, e.utm_source, e.utm_medium, e.utm_campaign, e.object_id, e.views,
               DATE(e.first_seen) AS first_seen, p.post_type, p.post_name, p.post_title,
               IFNULL(LEFT(UPPER(a.meta_value), 3), '') AS profession
        FROM tbstwp_hcp_engagement e
        LEFT JOIN tbstwp_posts p ON p.ID = e.object_id
        LEFT JOIN tbstwp_usermeta a ON a.user_id = e.user_id AND a.meta_key = 'ahpra_id'
        WHERE e.first_seen >= '{p.start}' AND e.first_seen < DATE_ADD('{p.end}', INTERVAL 1 DAY)
          AND {_staff_filter('e.user_id')}
        ORDER BY e.utm_campaign, e.user_id, e.first_seen;
    """)


def campaign_key(src: str, med: str, camp: str) -> tuple[str, str, str]:
    if src == UNTRACKED:
        return ("(not tracked)", "", "")
    if not (src or med or camp):
        return ("(no campaign)", "", "")
    return (camp or "(no campaign name)", src, med)


def write(name: str, slug: str, rows: list[dict]) -> None:
    path = io.write_dicts(io.out_dir(slug) / f"edm_tracking_{name}_{slug}.csv", rows)
    if rows:
        io.copy_to_downloads(path)


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY27Q3")
    p = periods.from_args(ap.parse_args())
    if p.end < TRACKING_LIVE:
        raise SystemExit(f"{p.label} ends before tracking went live on {TRACKING_LIVE}; nothing to report")
    if p.start < TRACKING_LIVE:
        print(f"NOTE: tracking went live {TRACKING_LIVE}. Sign-ups before then show as '(not tracked)'; "
              f"estimate those with the GA4 eDM reconciliation method. Engagement starts {TRACKING_LIVE}.")

    su = signups(p)
    by_src = Counter(campaign_key(r["src"], r["med"], r["camp"]) for r in su)
    write("signups_by_source", p.slug, [
        {"campaign": c, "utm_source": s, "utm_medium": m, "signups": n}
        for (c, s, m), n in by_src.most_common()])
    tracked = [r for r in su if r["src"] != UNTRACKED]
    write("signup_landing_pages", p.slug, [
        {"landing_page": k or "(no journey: cookie blocked or cleared)", "signups": n}
        for k, n in Counter(r["landing"] for r in tracked).most_common()])
    first = Counter(campaign_key(r["first_src"], "", r["first_camp"]) for r in tracked)
    write("signup_first_campaign", p.slug, [
        {"first_campaign": c, "utm_source": src, "signups": n} for (c, src, _), n in first.most_common()])

    eng = engagement(p)
    for r in eng:
        r["content_type"] = content_type(r["post_type"], r["post_name"])
        r["campaign"] = campaign_key(r["utm_source"], r["utm_medium"], r["utm_campaign"])
    summary: dict[tuple, dict] = defaultdict(lambda: {"hcps": set(), "pages": set(), "views": 0, "types": defaultdict(set)})
    for r in eng:
        s = summary[r["campaign"]]
        s["hcps"].add(r["user_id"])
        s["pages"].add(r["object_id"])
        s["views"] += int(r["views"])
        s["types"][r["content_type"]].add(r["user_id"])
    types = sorted({r["content_type"] for r in eng})
    write("engagement_by_campaign", p.slug, [
        {"campaign": c, "utm_source": src, "utm_medium": med, "hcps": len(s["hcps"]),
         "pages": len(s["pages"]), "views": s["views"],
         **{f"hcps_{t}": len(s["types"][t]) for t in types}}
        for (c, src, med), s in sorted(summary.items(), key=lambda kv: -len(kv[1]["hcps"]))])
    write("engagement_hcps", p.slug, [
        {"campaign": r["campaign"][0], "utm_source": r["campaign"][1], "user_id": r["user_id"],
         "profession": r["profession"], "first_seen": r["first_seen"], "content_type": r["content_type"],
         "page": r["post_title"], "views": r["views"]}
        for r in eng])

    print(f"\n{p.label}: {len(su)} sign-ups ({len(tracked)} with source recorded), "
          f"{len({r['user_id'] for r in eng})} HCPs engaged across {len(summary)} campaigns")


if __name__ == "__main__":
    main()

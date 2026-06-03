"""
CAPH0062 Rectogesic Clinical Audit Solus eDM — topline performance.
Send date: 2026-05-22 (Friday)
Campaign: CAPH0062 / medium: email

Pulls:
  1. GA4: sessions/users from CAPH0062 (May 22–now) vs prior 7-day baseline
  2. GA4: landing pages hit from the campaign
  3. DB: new HCP sign-ups since send date
  4. DB: new Form 81 (pre-survey) submissions since send date = enrolments
  5. DB: new Form 161 (audit) submissions since send date
"""
import json, subprocess, shutil
from datetime import date, timedelta
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT        = Path(__file__).resolve().parent.parent
TOKEN_JSON  = ROOT / ".secrets" / "ga4-token.json"
PROPERTY_ID = "306115293"
OUT         = ROOT / "reports" / "out"
WINDOWS_DL  = Path("/mnt/c/Users/User/Downloads")

SEND_DATE   = "2026-05-22"
TODAY       = date.today().isoformat()
PRE_START   = (date.fromisoformat(SEND_DATE) - timedelta(days=7)).isoformat()
PRE_END     = (date.fromisoformat(SEND_DATE) - timedelta(days=1)).isoformat()

EXCLUDE = """
    AND u.user_email NOT LIKE '%@panwarhealth.com.au'
    AND u.user_email NOT LIKE '%@tbstdigital.com.au'
    AND u.ID NOT IN (23747)
""".strip()


def load_env():
    env = {}
    for line in (ROOT / ".env").read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def db_query(sql, env):
    pw = env.get("MYSQL_ROOT_PASSWORD", "")
    r = subprocess.run(
        ["docker", "compose", "exec", "-T", "db",
         "mysql", "--default-character-set=utf8mb4",
         "-u", "root", f"-p{pw}", "hcp_care", "-e", sql],
        capture_output=True, cwd=str(ROOT),
    )
    stdout = r.stdout.decode("utf-8", errors="replace")
    lines = stdout.strip().splitlines()
    if len(lines) < 2:
        return [], []
    headers = lines[0].split("\t")
    rows = [line.split("\t") for line in lines[1:]]
    return headers, rows


def ga_client():
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(
        token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"),
    )
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return BetaAnalyticsDataClient(credentials=c)


def campaign_filter():
    return FilterExpression(filter=Filter(
        field_name="sessionCampaignName",
        string_filter=Filter.StringFilter(value="CAPH0062"),
    ))


def main():
    env = load_env()
    ga  = ga_client()

    print(f"\n{'='*60}")
    print(f"CAPH0062 Solus eDM — topline (send: {SEND_DATE}, today: {TODAY})")
    print(f"{'='*60}\n")

    # -------------------------------------------------------------------------
    # 1. GA4: overall sessions/users post-send vs prior week baseline
    # -------------------------------------------------------------------------
    print("--- GA4: Campaign sessions/users ---")
    def pull_campaign_totals(start, end):
        resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=start, end_date=end)],
            dimensions=[Dimension(name="sessionCampaignName")],
            metrics=[
                Metric(name="sessions"),
                Metric(name="totalUsers"),
                Metric(name="newUsers"),
            ],
            dimension_filter=campaign_filter(),
        ))
        if resp.rows:
            r = resp.rows[0]
            return int(r.metric_values[0].value), int(r.metric_values[1].value), int(r.metric_values[2].value)
        return 0, 0, 0

    post_sessions, post_users, post_new_users = pull_campaign_totals(SEND_DATE, TODAY)
    pre_sessions,  pre_users,  pre_new_users  = pull_campaign_totals(PRE_START, PRE_END)

    days_post = (date.today() - date.fromisoformat(SEND_DATE)).days + 1
    print(f"  Post-send ({SEND_DATE}–{TODAY}, {days_post} days):")
    print(f"    Sessions:   {post_sessions}")
    print(f"    Users:      {post_users}")
    print(f"    New users:  {post_new_users}")
    print(f"  Pre-send baseline (prior 7 days, {PRE_START}–{PRE_END}):")
    print(f"    Sessions:   {pre_sessions}")
    print(f"    Users:      {pre_users}")
    print(f"    New users:  {pre_new_users}")

    # -------------------------------------------------------------------------
    # 2. GA4: landing pages hit from campaign
    # -------------------------------------------------------------------------
    print("\n--- GA4: Landing pages from CAPH0062 ---")
    resp_lp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        dimensions=[Dimension(name="landingPage")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers")],
        dimension_filter=campaign_filter(),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
        limit=10,
    ))
    for row in resp_lp.rows:
        lp = row.dimension_values[0].value
        s  = row.metric_values[0].value
        u  = row.metric_values[1].value
        print(f"  {lp:<55} sessions={s}  users={u}")

    # -------------------------------------------------------------------------
    # 3. GA4: UTM source breakdown (which CTA links drove traffic)
    # -------------------------------------------------------------------------
    print("\n--- GA4: UTM source breakdown (which link in the eDM) ---")
    resp_src = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY_ID}",
        date_ranges=[DateRange(start_date=SEND_DATE, end_date=TODAY)],
        dimensions=[Dimension(name="sessionSource"), Dimension(name="sessionMedium")],
        metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="newUsers")],
        dimension_filter=campaign_filter(),
        order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
    ))
    for row in resp_src.rows:
        src = row.dimension_values[0].value
        med = row.metric_values[0].value
        u   = row.metric_values[1].value
        nu  = row.metric_values[2].value
        print(f"  source={row.dimension_values[0].value:<35}  sessions={med}  users={u}  new={nu}")

    # -------------------------------------------------------------------------
    # 4. GA4: overall site traffic post-send vs baseline (all sources)
    #    to show if eDM drove a broader lift
    # -------------------------------------------------------------------------
    print("\n--- GA4: Site-wide traffic (all sources) — post vs baseline ---")
    def pull_site_totals(start, end):
        resp = ga.run_report(RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=start, end_date=end)],
            metrics=[
                Metric(name="sessions"),
                Metric(name="totalUsers"),
                Metric(name="newUsers"),
            ],
        ))
        if resp.rows:
            r = resp.rows[0]
            return {"sessions": int(r.metric_values[0].value), "users": int(r.metric_values[1].value), "new_users": int(r.metric_values[2].value)}
        return {"sessions": 0, "users": 0, "new_users": 0}

    site_post = pull_site_totals(SEND_DATE, TODAY)
    site_pre  = pull_site_totals(PRE_START, PRE_END)
    print(f"  Post-send ({days_post} days):  sessions={site_post.get('sessions')}  users={site_post.get('users')}  new={site_post.get('new_users')}")
    print(f"  Pre-week  (7 days):   sessions={site_pre.get('sessions')}  users={site_pre.get('users')}  new={site_pre.get('new_users')}")

    # -------------------------------------------------------------------------
    # 5. DB: new HCP sign-ups since send date
    # -------------------------------------------------------------------------
    print("\n--- DB: New HCP sign-ups since send ---")
    _, rows = db_query(f"""
        SELECT DATE(user_registered) as day, COUNT(*) as n
        FROM tbstwp_users
        WHERE user_registered >= '{SEND_DATE}'
          AND user_registered < NOW()
          AND user_email NOT LIKE '%@panwarhealth.com.au'
          AND user_email NOT LIKE '%@tbstdigital.com.au'
          AND ID NOT IN (23747)
        GROUP BY day ORDER BY day;
    """, env)
    total_signups = 0
    for r in rows:
        print(f"  {r[0]}:  {r[1]} sign-ups")
        total_signups += int(r[1])
    print(f"  TOTAL: {total_signups} sign-ups since send")

    # -------------------------------------------------------------------------
    # 6. DB: Form 81 (pre-survey) submissions = enrolments in MCA course
    # -------------------------------------------------------------------------
    print("\n--- DB: Form 81 (MCA pre-survey / enrolment) since send ---")
    _, rows81 = db_query(f"""
        SELECT DATE(fi.created_at) as day, COUNT(*) as n
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = 81
          AND fi.created_at >= '{SEND_DATE}'
          {EXCLUDE}
        GROUP BY day ORDER BY day;
    """, env)
    total_81 = 0
    for r in rows81:
        print(f"  {r[0]}:  {r[1]}")
        total_81 += int(r[1])
    print(f"  TOTAL: {total_81} new enrolments (form 81) since send")

    # -------------------------------------------------------------------------
    # 7. DB: Form 161 (audit) submissions
    # -------------------------------------------------------------------------
    print("\n--- DB: Form 161 (audit submission) since send ---")
    _, rows161 = db_query(f"""
        SELECT DATE(fi.created_at) as day, COUNT(*) as n
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = 161
          AND fi.created_at >= '{SEND_DATE}'
          {EXCLUDE}
        GROUP BY day ORDER BY day;
    """, env)
    total_161 = 0
    for r in rows161:
        print(f"  {r[0]}:  {r[1]}")
        total_161 += int(r[1])
    print(f"  TOTAL: {total_161} new audit submissions since send")

    # -------------------------------------------------------------------------
    # 8. DB: baseline pre-send enrolments (prior 7 days) for comparison
    # -------------------------------------------------------------------------
    print("\n--- DB: Form 81 baseline (prior 7 days for comparison) ---")
    _, rows81_pre = db_query(f"""
        SELECT COUNT(*) as n
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = 81
          AND fi.created_at >= '{PRE_START}'
          AND fi.created_at < '{SEND_DATE}'
          {EXCLUDE};
    """, env)
    pre_81 = int(rows81_pre[0][0]) if rows81_pre else 0
    print(f"  {PRE_START}–{PRE_END}: {pre_81} enrolments (7-day pre-send baseline)")

    # -------------------------------------------------------------------------
    # Summary
    # -------------------------------------------------------------------------
    print(f"\n{'='*60}")
    print("TOPLINE SUMMARY")
    print(f"{'='*60}")
    print(f"eDM send: {SEND_DATE} | Days elapsed: {days_post}")
    print(f"\nGA4 (campaign tagged, CAPH0062):")
    print(f"  Sessions:              {post_sessions}")
    print(f"  Users:                 {post_users}")
    print(f"  New users:             {post_new_users}")
    print(f"\nSite-wide traffic (all sources):")
    print(f"  Post-send {days_post}d:  {site_post.get('sessions')} sessions / {site_post.get('users')} users")
    print(f"  Prior 7d baseline:     {site_pre.get('sessions')} sessions / {site_pre.get('users')} users")
    print(f"\nDB (local seed — directional, not live prod):")
    print(f"  New sign-ups:          {total_signups}")
    print(f"  MCA enrolments (F81):  {total_81}  (vs {pre_81} in prior 7d)")
    print(f"  Audit submissions:     {total_161}")
    print(f"{'='*60}\n")


if __name__ == "__main__":
    main()

"""
MCA course — full raw data export, clean cohort.

Source of truth: Formidable forms.
Enrolled = submitted Form 81 (pre-survey) on or after 2026-03-19.
Excluded: @panwarhealth.com.au, @tbstdigital.com.au, known tester ID 23747.

LearnDash usermeta used only for course completion events (known to be incomplete).

Forms:
  81  = Pre-learning survey    (enrollment signal)
  97  = Post-learning survey
  161 = Retrospective Audit
  209 = Activity evaluation

Outputs:
  1. mca_funnel_per_user.csv       — one row per enrolled HCP, all funnel stages
  2. mca_pre_survey_raw.csv        — Form 81 answers, wide format
  3. mca_post_survey_raw.csv       — Form 97 answers, wide format
  4. mca_audit_submissions.csv     — Form 161 summary per submission
  5. mca_audit_full_raw.csv        — Form 161 all answers, long format
  6. mca_activity_eval_raw.csv     — Form 209 answers, wide format
  7. mca_pageviews_ga4.csv         — GA4 daily page views for MCA URLs
"""
import csv
import json
import shutil
import subprocess
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression,
    Metric, OrderBy, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT       = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROPERTY   = "306115293"
OUT        = ROOT / "reports" / "out"
DL         = Path("/mnt/c/Users/User/Downloads")

COURSE_ID  = 111793
FORM_PRE   = 81
FORM_POST  = 97
FORM_AUDIT = 161
FORM_EVAL  = 209
LAUNCH     = "2026-03-19"

# Reusable exclusion clause — apply to any query joining tbstwp_users as u
EXCLUDE = """
    AND u.user_email NOT LIKE '%@panwarhealth.com.au'
    AND u.user_email NOT LIKE '%@tbstdigital.com.au'
    AND u.ID NOT IN (23747)
""".strip()


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------
def load_env() -> dict:
    env = {}
    for line in (ROOT / ".env").read_text().splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, _, v = line.partition("=")
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def db(sql: str, env: dict) -> tuple[list[str], list[list[str]]]:
    pw = env.get("MYSQL_ROOT_PASSWORD", "")
    r = subprocess.run(
        ["docker", "compose", "exec", "-T", "db",
         "mysql", "--default-character-set=utf8mb4",
         "-u", "root", f"-p{pw}", "hcp_care", "-e", sql],
        capture_output=True, cwd=str(ROOT),
    )
    stdout = r.stdout.decode("utf-8", errors="replace")
    stderr = r.stderr.decode("utf-8", errors="replace")
    if r.returncode != 0:
        print(f"  DB error: {stderr[:300]}")
        return [], []
    lines = stdout.strip().splitlines()
    if len(lines) < 1:
        return [], []
    headers = lines[0].split("\t")
    rows = [line.split("\t") for line in lines[1:]] if len(lines) > 1 else []
    return headers, rows


# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------
def write_csv(rows: list[dict], filename: str) -> None:
    if not rows:
        print(f"  [empty] {filename}")
        return
    all_keys: list[str] = []
    seen: set[str] = set()
    for row in rows:
        for k in row.keys():
            if k not in seen:
                all_keys.append(k)
                seen.add(k)
    path = OUT / filename
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=all_keys, extrasaction="ignore", restval="")
        w.writeheader()
        w.writerows(rows)
    print(f"  Written:  {path}")
    if DL.exists():
        shutil.copy(path, DL / filename)
        print(f"  Copied:   {DL / filename}")


# ---------------------------------------------------------------------------
# 1. Funnel per user
# Enrolled = submitted Form 81 >= launch date (clean cohort).
# All other stages joined in from other forms + LearnDash.
# ---------------------------------------------------------------------------
def pull_funnel(env: dict) -> list[dict]:
    # Base: everyone who submitted the pre-survey (enrolled)
    _, rows = db(f"""
        SELECT u.ID, u.user_login, u.user_email, u.display_name,
               DATE(u.user_registered) AS registered,
               fi.created_at AS pre_survey_at
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {FORM_PRE}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        ORDER BY fi.created_at;
    """, env)

    enrolled: dict[str, dict] = {}
    for r in rows:
        uid = r[0]
        enrolled[uid] = {
            "user_id":        uid,
            "user_login":     r[1],
            "user_email":     r[2],
            "display_name":   r[3],
            "registered":     r[4],
            "pre_survey_at":  r[5],
        }

    # Post-survey
    _, post_rows = db(f"""
        SELECT fi.user_id, MIN(fi.created_at)
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {FORM_POST}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        GROUP BY fi.user_id;
    """, env)
    for r in post_rows:
        if r[0] in enrolled:
            enrolled[r[0]]["post_survey_at"] = r[1]

    # Audit started (Form 161 first entry)
    _, audit_rows = db(f"""
        SELECT fi.user_id, MIN(fi.created_at), MAX(fi.updated_at),
               TIMESTAMPDIFF(MINUTE, MIN(fi.created_at), MAX(fi.updated_at))
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {FORM_AUDIT}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        GROUP BY fi.user_id;
    """, env)
    for r in audit_rows:
        if r[0] in enrolled:
            enrolled[r[0]]["audit_started_at"]   = r[1]
            enrolled[r[0]]["audit_last_saved_at"] = r[2]
            enrolled[r[0]]["audit_minutes"]       = r[3]

    # Activity evaluation (Form 209)
    _, eval_rows = db(f"""
        SELECT fi.user_id, MIN(fi.created_at)
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {FORM_EVAL}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        GROUP BY fi.user_id;
    """, env)
    for r in eval_rows:
        if r[0] in enrolled:
            enrolled[r[0]]["activity_eval_at"] = r[1]

    # LearnDash course completion (incomplete source — include as bonus signal)
    _, ld_rows = db(f"""
        SELECT um.user_id, FROM_UNIXTIME(um.meta_value)
        FROM tbstwp_usermeta um
        JOIN tbstwp_users u ON u.ID = um.user_id
        WHERE um.meta_key = 'course_completed_{COURSE_ID}'
          {EXCLUDE};
    """, env)
    for r in ld_rows:
        if r[0] in enrolled:
            enrolled[r[0]]["ld_course_completed_at"] = r[1]

    # New vs existing
    from datetime import datetime
    out = []
    for d in enrolled.values():
        try:
            reg = datetime.strptime(d["registered"], "%Y-%m-%d")
            pre = datetime.strptime(d["pre_survey_at"][:10], "%Y-%m-%d")
            days = (pre - reg).days
            d["days_reg_to_enrol"] = days
            d["user_type"] = "new for course" if days <= 3 else "existing user"
        except Exception:
            d["days_reg_to_enrol"] = ""
            d["user_type"] = ""
        out.append({
            "user_id":               d["user_id"],
            "display_name":          d["display_name"],
            "user_email":            d["user_email"],
            "user_login":            d["user_login"],
            "registered":            d["registered"],
            "days_reg_to_enrol":     d.get("days_reg_to_enrol", ""),
            "user_type":             d.get("user_type", ""),
            "pre_survey_at":         d.get("pre_survey_at", ""),
            "post_survey_at":        d.get("post_survey_at", ""),
            "audit_started_at":      d.get("audit_started_at", ""),
            "audit_last_saved_at":   d.get("audit_last_saved_at", ""),
            "audit_minutes":         d.get("audit_minutes", ""),
            "activity_eval_at":      d.get("activity_eval_at", ""),
            "ld_course_completed_at": d.get("ld_course_completed_at", ""),
        })
    return out


# ---------------------------------------------------------------------------
# Survey wide-format pivot
# ---------------------------------------------------------------------------
def pull_survey_wide(form_id: int, env: dict) -> list[dict]:
    # Field labels
    _, frows = db(f"""
        SELECT id, name, description
        FROM tbstwp_frm_fields
        WHERE form_id = {form_id}
        ORDER BY field_order;
    """, env)
    field_map: dict[str, str] = {}
    for r in frows:
        if len(r) < 2:
            continue
        fid = r[0]
        name = r[1] if len(r) > 1 else ""
        desc = r[2] if len(r) > 2 else ""
        label = desc.strip() if desc and desc.strip() not in ("", "NULL") else name
        label = label[:80].replace("\n", " ").replace("\r", "")
        field_map[fid] = label

    # Submissions (clean cohort only)
    _, irows = db(f"""
        SELECT fi.id, fi.user_id, u.user_login, u.user_email,
               u.display_name, fi.created_at
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {form_id}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        ORDER BY fi.created_at;
    """, env)

    subs: dict[str, dict] = {}
    for r in irows:
        iid = r[0]
        subs[iid] = {
            "submission_id": iid,
            "user_id":       r[1],
            "user_login":    r[2],
            "user_email":    r[3],
            "display_name":  r[4],
            "submitted_at":  r[5],
        }

    if not subs:
        return []

    # Answers
    ids_csv = ",".join(subs.keys())
    _, mrows = db(f"""
        SELECT item_id, field_id, meta_value
        FROM tbstwp_frm_item_metas
        WHERE item_id IN ({ids_csv})
        ORDER BY item_id, field_id;
    """, env)

    for r in mrows:
        item_id, field_id = r[0], r[1]
        value = r[2] if len(r) > 2 else ""
        if item_id not in subs or field_id not in field_map:
            continue
        col = field_map[field_id]
        if col in subs[item_id]:
            col = f"{col} [fid={field_id}]"
        subs[item_id][col] = "" if value in ("NULL", "null") else value

    return list(subs.values())


# ---------------------------------------------------------------------------
# Audit summary (Form 161)
# ---------------------------------------------------------------------------
def pull_audit_summary(env: dict) -> list[dict]:
    headers, rows = db(f"""
        SELECT fi.id, fi.user_id, u.user_login, u.user_email, u.display_name,
               fi.created_at, fi.updated_at,
               TIMESTAMPDIFF(MINUTE, fi.created_at, fi.updated_at) AS minutes_on_audit,
               COUNT(CASE WHEN fim.meta_value != '' AND fim.meta_value != 'NULL'
                          THEN 1 END) AS fields_filled
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        LEFT JOIN tbstwp_frm_item_metas fim ON fim.item_id = fi.id
        WHERE fi.form_id = {FORM_AUDIT}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        GROUP BY fi.id, fi.user_id, u.user_login, u.user_email,
                 u.display_name, fi.created_at, fi.updated_at
        ORDER BY fi.created_at;
    """, env)
    if not headers:
        return []
    return [dict(zip(headers, r)) for r in rows]


# ---------------------------------------------------------------------------
# Audit full raw — long format
# ---------------------------------------------------------------------------
def pull_audit_raw_long(env: dict) -> list[dict]:
    # Get submission IDs for clean cohort
    _, id_rows = db(f"""
        SELECT fi.id
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {FORM_AUDIT}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE};
    """, env)
    if not id_rows:
        return []
    ids_csv = ",".join(r[0] for r in id_rows)

    headers, rows = db(f"""
        SELECT fi.id AS submission_id, fi.user_id, u.user_login, u.display_name,
               fi.created_at AS submitted_at,
               ff.id AS field_id, ff.name AS field_name,
               ff.description AS field_label, ff.field_order,
               fim.meta_value AS answer
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        JOIN tbstwp_frm_item_metas fim ON fim.item_id = fi.id
        JOIN tbstwp_frm_fields ff ON ff.id = fim.field_id
          AND ff.form_id = {FORM_AUDIT}
        WHERE fi.id IN ({ids_csv})
          AND fim.meta_value != ''
          AND fim.meta_value != 'NULL'
        ORDER BY fi.created_at, fi.id, ff.field_order;
    """, env)
    if not headers:
        return []
    return [dict(zip(headers, r)) for r in rows]


# ---------------------------------------------------------------------------
# GA4 page views
# ---------------------------------------------------------------------------
def creds() -> Credentials:
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(
        token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"),
    )
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    return c


def pull_ga4_pageviews() -> list[dict]:
    ga = BetaAnalyticsDataClient(credentials=creds())
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY}",
        date_ranges=[DateRange(start_date="2025-04-01", end_date="2026-03-31")],
        dimensions=[Dimension(name="pagePath"), Dimension(name="date")],
        metrics=[
            Metric(name="screenPageViews"),
            Metric(name="totalUsers"),
            Metric(name="sessions"),
        ],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                value=r"^/(courses|lessons|quizzes|quiz|certificate|anal-fissures-breaking|mini-clinical-audit|retrospective|complete-mini|pre-learning-survey|pre-survey|post-learning-survey)",
            ),
        )),
        order_bys=[
            OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name="date")),
            OrderBy(metric=OrderBy.MetricOrderBy(metric_name="screenPageViews"), desc=True),
        ],
        limit=5000,
    ))
    return [{
        "page_path": r.dimension_values[0].value,
        "date":      r.dimension_values[1].value,
        "pageviews": int(r.metric_values[0].value),
        "users":     int(r.metric_values[1].value),
        "sessions":  int(r.metric_values[2].value),
    } for r in resp.rows]


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> None:
    env = load_env()

    print("\n1. Funnel per user (Formidable source of truth)...")
    funnel = pull_funnel(env)
    new_ct  = sum(1 for r in funnel if r["user_type"] == "new for course")
    exist_ct = sum(1 for r in funnel if r["user_type"] == "existing user")
    post_ct  = sum(1 for r in funnel if r.get("post_survey_at"))
    audit_ct = sum(1 for r in funnel if r.get("audit_started_at"))
    eval_ct  = sum(1 for r in funnel if r.get("activity_eval_at"))
    print(f"   Enrolled:       {len(funnel)}")
    print(f"   New for course: {new_ct}  |  Existing: {exist_ct}")
    print(f"   Post-survey:    {post_ct}")
    print(f"   Audit started:  {audit_ct}")
    print(f"   Activity eval:  {eval_ct}")
    write_csv(funnel, "mca_funnel_per_user_v3.csv")

    print("\n2. Pre-learning survey (Form 81)...")
    pre = pull_survey_wide(FORM_PRE, env)
    print(f"   {len(pre)} submissions")
    write_csv(pre, "mca_pre_survey_raw_v3.csv")

    print("\n3. Post-learning survey (Form 97)...")
    post = pull_survey_wide(FORM_POST, env)
    print(f"   {len(post)} submissions")
    write_csv(post, "mca_post_survey_raw_v3.csv")

    print("\n4. Activity evaluation (Form 209)...")
    evl = pull_survey_wide(FORM_EVAL, env)
    print(f"   {len(evl)} submissions")
    write_csv(evl, "mca_activity_eval_raw_v3.csv")

    print("\n5. Audit submissions summary (Form 161)...")
    audit_sum = pull_audit_summary(env)
    print(f"   {len(audit_sum)} submissions")
    write_csv(audit_sum, "mca_audit_submissions_v3.csv")

    print("\n6. Audit full raw answers (Form 161, long format)...")
    audit_raw = pull_audit_raw_long(env)
    print(f"   {len(audit_raw)} field answers")
    write_csv(audit_raw, "mca_audit_full_raw_v3.csv")

    print("\n7. GA4 page views (MCA URLs, FY26)...")
    pv = pull_ga4_pageviews()
    print(f"   {len(pv)} rows")
    write_csv(pv, "mca_pageviews_ga4_v3.csv")

    print(f"\n{'='*50}")
    print(f"CLEAN FUNNEL SUMMARY (post-launch, real HCPs only)")
    print(f"{'='*50}")
    print(f"  Enrolled (pre-survey submitted):  {len(funnel)}")
    print(f"  Post-survey submitted:            {post_ct}")
    print(f"  Audit started:                    {audit_ct}")
    print(f"  Activity eval completed:          {eval_ct}")
    print(f"  New sign-ups for course:          {new_ct}")
    print(f"  Existing users who enrolled:      {exist_ct}")
    print(f"\n  7 CSVs in Windows Downloads.")


if __name__ == "__main__":
    main()

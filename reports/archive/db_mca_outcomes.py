"""
MCA course — outcomes analysis for both slides.

Slide 1 (Course Mechanics):
  - Completion rate, avg audit time, GP%, avg learning module engagement time

Slide 2 (Outcomes):
  - Pre/post knowledge confidence shifts (top-2 box)
  - Patient-risk perception shift
  - Practice-change intent
  - What HCPs said

Source: Formidable Forms 81 (pre), 97 (post), 161 (audit).
Cohort: post-2026-03-19, excluding @panwarhealth.com.au, @tbstdigital.com.au, ID 23747.

Outputs:
  - mca_outcomes_summary.csv
  - mca_outcomes_summary.md
"""
import csv, json, shutil, subprocess
from collections import defaultdict
from pathlib import Path

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange, Dimension, Filter, FilterExpression, FilterExpressionList,
    Metric, RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

ROOT       = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
PROPERTY   = "306115293"
OUT        = ROOT / "reports" / "out"
DL         = Path("/mnt/c/Users/User/Downloads")
LAUNCH     = "2026-03-19"
EXCLUDE    = """
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


def db(sql, env):
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


def write_csv(rows, filename):
    if not rows: return
    all_keys = []
    seen = set()
    for row in rows:
        for k in row:
            if k not in seen:
                all_keys.append(k)
                seen.add(k)
    path = OUT / filename
    with path.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=all_keys, extrasaction="ignore", restval="")
        w.writeheader()
        w.writerows(rows)
    print(f"  Written: {path}")
    if DL.exists():
        shutil.copy(path, DL / filename)
        print(f"  Copied:  {DL / filename}")


def write_text(content, filename):
    path = OUT / filename
    path.write_text(content, encoding="utf-8")
    print(f"  Written: {path}")
    if DL.exists():
        shutil.copy(path, DL / filename)
        print(f"  Copied:  {DL / filename}")


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
TOP2 = {"Strongly agree", "Agree"}
MORE_LIKELY = {"Much more likely", "Somewhat more likely"}
LESS_LIKELY = {"Much less likely", "Somewhat less likely"}


def pct(n, d):
    return round(n / d * 100) if d else 0


def top2_pct(answers):
    total = len(answers)
    top2_n = sum(1 for a in answers if a in TOP2)
    return pct(top2_n, total), total


# ---------------------------------------------------------------------------
# Pull all answers for a form, keyed by user_id → {field_id: meta_value}
# ---------------------------------------------------------------------------
def get_answers(form_id, env):
    _, rows = db(f"""
        SELECT fi.user_id, fim.field_id, fim.meta_value
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        JOIN tbstwp_frm_item_metas fim ON fim.item_id = fi.id
        WHERE fi.form_id = {form_id}
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE}
        ORDER BY fi.created_at;
    """, env)
    user_answers = defaultdict(dict)
    for r in rows:
        uid, fid, val = r[0], r[1], r[2] if len(r) > 2 else ""
        if val and val != "NULL":
            user_answers[uid][fid] = val
    return user_answers


# ---------------------------------------------------------------------------
# GA4 — avg engagement time on course pages
# ---------------------------------------------------------------------------
def ga4_avg_engagement():
    info = json.loads(TOKEN_JSON.read_text())
    c = Credentials(token=info.get("token"), refresh_token=info["refresh_token"],
        token_uri=info["token_uri"], client_id=info["client_id"],
        client_secret=info["client_secret"], scopes=info.get("scopes"))
    c.refresh(Request())
    TOKEN_JSON.write_text(c.to_json())
    ga = BetaAnalyticsDataClient(credentials=c)
    resp = ga.run_report(RunReportRequest(
        property=f"properties/{PROPERTY}",
        date_ranges=[DateRange(start_date="2026-03-19", end_date="2026-05-10")],
        metrics=[
            Metric(name="averageSessionDuration"),
            Metric(name="userEngagementDuration"),
            Metric(name="sessions"),
        ],
        dimension_filter=FilterExpression(filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                value=r"^/(courses|lessons|anal-fissures-breaking|mini-clinical-audit)",
            ),
        )),
    ))
    if resp.rows:
        avg_secs = float(resp.rows[0].metric_values[0].value)
        return round(avg_secs / 60, 1)
    return None


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main():
    env = load_env()

    # --- Get answers ---
    print("Pulling pre-survey answers (Form 81)...")
    pre  = get_answers(81, env)
    print(f"  {len(pre)} users")

    print("Pulling post-survey answers (Form 97)...")
    post = get_answers(97, env)
    print(f"  {len(post)} users")

    # Paired users
    paired = sorted(set(pre.keys()) & set(post.keys()))
    print(f"  Paired (both surveys): {len(paired)}")

    # --- Audit mechanics ---
    print("Pulling audit data (Form 161)...")
    _, audit_rows = db(f"""
        SELECT fi.user_id, u.user_login,
               TIMESTAMPDIFF(MINUTE, fi.created_at, fi.updated_at) as minutes
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = 161
          AND fi.created_at >= '{LAUNCH}'
          {EXCLUDE};
    """, env)
    audit_minutes = [int(r[2]) for r in audit_rows if r[2].lstrip('-').isdigit() and int(r[2]) > 0]
    audit_logins  = [r[1] for r in audit_rows]
    gp_count      = sum(1 for l in audit_logins if l.upper().startswith("MED"))
    avg_audit_min = round(sum(audit_minutes) / len(audit_minutes)) if audit_minutes else 0
    gp_pct        = pct(gp_count, len(audit_logins))

    # --- GA4 learning module time ---
    print("Pulling GA4 engagement time...")
    avg_module_min = ga4_avg_engagement()

    # --- Funnel totals ---
    _, en_rows = db(f"""
        SELECT COUNT(DISTINCT fi.user_id) FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = 81 AND fi.created_at >= '{LAUNCH}' {EXCLUDE};
    """, env)
    enrolled = int(en_rows[0][0]) if en_rows else 0

    # =========================================================================
    # KNOWLEDGE / CONFIDENCE — pre vs post top-2 box (paired users only)
    # =========================================================================
    # field_pre → field_post → label
    KNOWLEDGE_MAP = [
        ("3857", "3633", "Identifying fissures via visual exam"),
        ("3841", "3617", "Distinguishing fissures from other conditions"),
        ("3809", "3569", "Educating patients about bowel habits"),
        ("3745", "3505", "Familiarity with risk factors"),
        ("3793", "3585", "Role of internal anal sphincter"),
        ("3761", "3009", "Comfortable asking about anal symptoms"),
    ]

    knowledge_rows = []
    for fid_pre, fid_post, label in KNOWLEDGE_MAP:
        pre_ans  = [pre[u].get(fid_pre, "")  for u in paired if pre[u].get(fid_pre)]
        post_ans = [post[u].get(fid_post, "") for u in paired if post[u].get(fid_post)]
        pre_pct,  pre_n  = top2_pct(pre_ans)
        post_pct, post_n = top2_pct(post_ans)
        shift = post_pct - pre_pct
        print(f"  {label}: {pre_pct}% → {post_pct}% ({'+' if shift>=0 else ''}{shift} pp)")
        knowledge_rows.append({
            "question": label,
            "pre_top2_pct": pre_pct, "pre_n": pre_n,
            "post_top2_pct": post_pct, "post_n": post_n,
            "shift_pp": shift,
        })

    # =========================================================================
    # PATIENT-RISK PERCEPTION — pre field 2401, post field 2705
    # =========================================================================
    RISK_BUCKETS = ["<10%", "10–30%", "30–50%", ">50%"]
    risk_rows = []
    for bucket in RISK_BUCKETS:
        pre_n  = sum(1 for u in paired if pre[u].get("2401") == bucket)
        post_n = sum(1 for u in paired if post[u].get("2705") == bucket)
        pre_p  = pct(pre_n, len(paired))
        post_p = pct(post_n, len(paired))
        risk_rows.append({
            "bucket": bucket,
            "pre_pct": pre_p, "pre_n": pre_n,
            "post_pct": post_p, "post_n": post_n,
            "shift_pp": post_p - pre_p,
        })
        print(f"  Risk {bucket}: {pre_p}% → {post_p}% ({'+' if post_p-pre_p>=0 else ''}{post_p-pre_p} pp)")

    # =========================================================================
    # PRACTICE-CHANGE INTENT (post only, all 24 respondents)
    # =========================================================================
    POST_N = len(post)
    INTENT_MAP = [
        ("3329", "GTN ointment (Rectogesic)"),
        ("2993", "Sitz baths"),
        ("3409", "Hydration"),
        ("3377", "Correct bowel movement behaviours"),
        ("3425", "Fibre supplementation"),
        ("3345", "Topical corticosteroid"),
    ]
    intent_rows = []
    for fid, label in INTENT_MAP:
        answers = [post[u].get(fid, "") for u in post if post[u].get(fid)]
        more_n = sum(1 for a in answers if a in MORE_LIKELY)
        less_n = sum(1 for a in answers if a in LESS_LIKELY)
        more_p = pct(more_n, len(answers)) if answers else 0
        less_p = pct(less_n, len(answers)) if answers else 0
        intent_rows.append({
            "treatment": label,
            "more_likely_pct": more_p, "more_likely_n": more_n,
            "less_likely_pct": less_p, "less_likely_n": less_n,
            "n_answered": len(answers),
        })
        print(f"  {label}: more={more_p}%  less={less_p}%")

    # =========================================================================
    # WHAT HCPs SAID (post only)
    # =========================================================================
    recommend_yes = sum(1 for u in post if post[u].get("2641") == "Yes")
    change_yes    = sum(1 for u in post if post[u].get("2673") == "Yes")
    extreme_imp   = sum(1 for u in post if post[u].get("2785") == "Extremely important")
    at_risk_10plus = sum(1 for u in post if post[u].get("2705", "<10%") != "<10%")

    # Pre importance for comparison
    pre_extreme_imp = sum(1 for u in paired if pre[u].get("2465") == "Extremely important")
    pre_imp_pct = pct(pre_extreme_imp, len(paired))
    post_imp_pct = pct(extreme_imp, POST_N)

    hcp_said = [
        {"metric": "Would recommend to a colleague",      "value": f"{pct(recommend_yes, POST_N)}%", "raw": f"{recommend_yes} of {POST_N}"},
        {"metric": "Would change something in practice",  "value": f"{pct(change_yes, POST_N)}%",    "raw": f"{change_yes} of {POST_N}"},
        {"metric": "Now consider 'extremely important'",  "value": f"{post_imp_pct}%",               "raw": f"vs {pre_imp_pct}% pre (+{post_imp_pct-pre_imp_pct}pp)"},
        {"metric": "See ≥10% of patients as at-risk",    "value": f"{pct(at_risk_10plus, POST_N)}%","raw": f"vs {pct(sum(1 for u in paired if pre[u].get('2401','<10%') != '<10%'), len(paired))}% pre"},
    ]

    # =========================================================================
    # COURSE MECHANICS
    # =========================================================================
    completion_rate = pct(len(post), enrolled)
    mechanics = [
        {"metric": "Enrolled",                          "value": str(enrolled)},
        {"metric": "Post-survey / module completed",    "value": str(len(post))},
        {"metric": "Completion rate (enrolled → post)", "value": f"{completion_rate}%"},
        {"metric": "Paired users (both surveys)",       "value": str(len(paired))},
        {"metric": "Audit started",                     "value": str(len(audit_rows))},
        {"metric": "Avg time to complete audit",        "value": f"~{round(avg_audit_min/60,1)} hrs ({avg_audit_min} min)"},
        {"metric": "Audit submitters who are GPs (MED)", "value": f"~{gp_pct}% ({gp_count} of {len(audit_rows)})"},
        {"metric": "Avg GA4 engagement time on course pages", "value": f"~{avg_module_min} min" if avg_module_min else "n/a"},
    ]

    # =========================================================================
    # WRITE OUTPUTS
    # =========================================================================
    write_csv(mechanics,      "mca_course_mechanics.csv")
    write_csv(knowledge_rows, "mca_knowledge_shift.csv")
    write_csv(risk_rows,      "mca_risk_perception_shift.csv")
    write_csv(intent_rows,    "mca_practice_change_intent.csv")
    write_csv(hcp_said,       "mca_what_hcps_said.csv")

    # --- Markdown summary ---
    md = []
    md.append("# MCA Course — Outcomes Data (Fresh Prod DB)\n")
    md.append(f"**Cohort:** post-launch (19 Mar 2026+), real HCPs only  ")
    md.append(f"**Enrolled:** {enrolled} | **Post-survey:** {len(post)} | **Paired:** {len(paired)}\n")

    md.append("## Course Mechanics\n")
    md.append("| Metric | Value |")
    md.append("|---|---|")
    for r in mechanics:
        md.append(f"| {r['metric']} | **{r['value']}** |")
    md.append("")

    md.append("## Knowledge / Confidence Shift (top-2 box, paired users)\n")
    md.append(f"Top-2 box = 'Strongly agree' + 'Agree'. n={len(paired)} paired users.\n")
    md.append("| Question | Pre | Post | Shift |")
    md.append("|---|---:|---:|---:|")
    for r in knowledge_rows:
        md.append(f"| {r['question']} | {r['pre_top2_pct']}% | {r['post_top2_pct']}% | "
                  f"**{'+' if r['shift_pp']>=0 else ''}{r['shift_pp']} pp** |")
    md.append("")

    md.append("## Patient-Risk Perception Shift (paired users)\n")
    md.append("| % patients seen as at-risk | Pre | Post | Shift |")
    md.append("|---|---:|---:|---:|")
    for r in risk_rows:
        md.append(f"| {r['bucket']} | {r['pre_pct']}% | {r['post_pct']}% | "
                  f"**{'+' if r['shift_pp']>=0 else ''}{r['shift_pp']} pp** |")
    md.append("")

    md.append("## Practice-Change Intent (post only, n={POST_N})\n".replace("{POST_N}", str(POST_N)))
    md.append("'More likely' = 'Much more likely' + 'Somewhat more likely'\n")
    md.append("| Treatment | More likely | Less likely |")
    md.append("|---|---:|---:|")
    for r in intent_rows:
        md.append(f"| {r['treatment']} | **{r['more_likely_pct']}%** | {r['less_likely_pct']}% |")
    md.append("")

    md.append("## What HCPs Said\n")
    md.append("| Metric | Value | Detail |")
    md.append("|---|---|---|")
    for r in hcp_said:
        md.append(f"| {r['metric']} | **{r['value']}** | {r['raw']} |")
    md.append("")

    write_text("\n".join(md), "mca_outcomes_summary.md")
    print("\nDone.")


if __name__ == "__main__":
    main()

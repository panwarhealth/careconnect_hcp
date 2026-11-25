"""MCA / CPD course — AMJ26 review refresh, queried from PROD.

Cohort: post-launch (2026-03-19 →), real HCPs only (same exclusions as the
FY26 outcomes run). Reports cumulative-to-30-Jun plus the AMJ26-quarter delta.

Outputs to reports/out/AMJ26/ (+ Windows Downloads):
  - amj26_mca_funnel.csv           sign-up → module → audit → cert funnel
  - amj26_mca_eval_feedback.csv    post-survey headline stats + 'Entirely met' items
  - amj26_mca_knowledge_shift.csv  pre/post confidence (paired users)
  - amj26_mca_risk_shift.csv       patient-risk perception (paired users)
  - amj26_mca_intent.csv           practice-change intent (post survey)
"""
import subprocess
import sys
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

FORM_PRE, FORM_POST, FORM_AUDIT, FORM_EVAL = 81, 97, 161, 209
MODULE_COURSE, AUDIT_COURSE = 95553, 111793
LAUNCH = "2026-03-19"
Q_START, Q_END = "2026-04-01", "2026-07-01"
CUTOFF = "2026-07-01"
EXCLUDE = """
    AND u.user_email NOT LIKE '%@panwarhealth.com.au'
    AND u.user_email NOT LIKE '%@tbstdigital.com.au'
    AND u.ID NOT IN (23747)
""".strip()

def _prod_secrets() -> dict:
    """Credentials from gitignored infra/.prod-secrets (PROD_PASS, DB_USER, DB_PASS)."""
    secrets = {}
    for line in (Path(__file__).resolve().parents[2] / "infra" / ".prod-secrets").read_text().splitlines():
        if "=" in line and not line.startswith("#"):
            k, _, v = line.partition("=")
            secrets[k.strip()] = v.strip()
    return secrets


_S = _prod_secrets()
SSH = ["sshpass", "-p", _S["PROD_PASS"], "ssh", "-p", "9022",
       "-o", "ConnectTimeout=30", "rob.hcp.carepharma.com.au@www9.stratus.ayuda.net.au"]
MYSQL = f"mysql --default-character-set=utf8mb4 -h 127.0.0.1 -P 9306 -u {_S['DB_USER']} -p'{_S['DB_PASS']}' hcp_care --batch"


def db(sql: str) -> list[list[str]]:
    r = subprocess.run(SSH + [MYSQL], input=sql, capture_output=True, text=True)
    lines = [l for l in r.stdout.splitlines() if l and not l.startswith("Warning")]
    if r.returncode != 0 and not lines:
        raise RuntimeError(r.stderr[:300])
    return [l.split("\t") for l in lines[1:]]  # skip header


def count(sql: str) -> int:
    rows = db(sql)
    return int(rows[0][0]) if rows and rows[0][0] not in ("", "NULL") else 0


def form_users(form_id: int, start: str, end: str, drafts: str = "") -> int:
    return count(f"""
        SELECT COUNT(DISTINCT fi.user_id) FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        WHERE fi.form_id = {form_id}
          AND fi.created_at >= '{start}' AND fi.created_at < '{end}' {drafts}
          {EXCLUDE};""")


def answers(form_id: int) -> dict[str, dict[str, str]]:
    rows = db(f"""
        SELECT fi.user_id, fim.field_id, fim.meta_value
        FROM tbstwp_frm_items fi
        JOIN tbstwp_users u ON u.ID = fi.user_id
        JOIN tbstwp_frm_item_metas fim ON fim.item_id = fi.id
        WHERE fi.form_id = {form_id}
          AND fi.created_at >= '{LAUNCH}' AND fi.created_at < '{CUTOFF}'
          {EXCLUDE};""")
    out: dict[str, dict[str, str]] = defaultdict(dict)
    for r in rows:
        if len(r) >= 3 and r[2] and r[2] != "NULL":
            out[r[0]][r[1]] = r[2]
    return out


def pct(n, d):
    return round(n / d * 100) if d else 0


def write_titled_csv(path, title: str, rows: list[dict]) -> None:
    """CSV with a first title line explaining the data, then header + rows."""
    import csv as _csv
    with path.open("w", newline="", encoding="utf-8") as f:
        w = _csv.writer(f)
        w.writerow([title])
        if rows:
            keys = list(rows[0].keys())
            w.writerow(keys)
            for r in rows:
                w.writerow([r.get(k, "") for k in keys])
    print(f"wrote {path.name}  ({len(rows)} rows)")


def main() -> None:
    outdir = io.out_dir("AMJ26")

    # ── funnel: DB stages ────────────────────────────────────────────────
    enrolled_all = count(f"""
        SELECT COUNT(DISTINCT um.user_id) FROM tbstwp_usermeta um
        JOIN tbstwp_users u ON u.ID = um.user_id
        WHERE um.meta_key = 'course_{MODULE_COURSE}_access_from'
          AND FROM_UNIXTIME(um.meta_value) >= '{LAUNCH}' {EXCLUDE};""")
    enrolled_q = count(f"""
        SELECT COUNT(DISTINCT um.user_id) FROM tbstwp_usermeta um
        JOIN tbstwp_users u ON u.ID = um.user_id
        WHERE um.meta_key = 'course_{MODULE_COURSE}_access_from'
          AND FROM_UNIXTIME(um.meta_value) >= '{Q_START}'
          AND FROM_UNIXTIME(um.meta_value) < '{Q_END}' {EXCLUDE};""")

    pre_all,  pre_q  = form_users(FORM_PRE, LAUNCH, CUTOFF),  form_users(FORM_PRE, Q_START, Q_END)
    post_all, post_q = form_users(FORM_POST, LAUNCH, CUTOFF), form_users(FORM_POST, Q_START, Q_END)
    module_done_all = count(f"""
        SELECT COUNT(DISTINCT um.user_id) FROM tbstwp_usermeta um
        JOIN tbstwp_users u ON u.ID = um.user_id
        WHERE um.meta_key = 'course_completed_{MODULE_COURSE}'
          AND FROM_UNIXTIME(um.meta_value) >= '{LAUNCH}' {EXCLUDE};""")
    audit_started_all = form_users(FORM_AUDIT, LAUNCH, CUTOFF)
    audit_started_q   = form_users(FORM_AUDIT, Q_START, Q_END)
    audit_submitted_all = form_users(FORM_AUDIT, LAUNCH, CUTOFF, "AND fi.is_draft = 0")
    audit_submitted_q   = form_users(FORM_AUDIT, Q_START, Q_END, "AND fi.is_draft = 0")
    eval_all, eval_q = form_users(FORM_EVAL, LAUNCH, CUTOFF), form_users(FORM_EVAL, Q_START, Q_END)
    mca_completed_all = count(f"""
        SELECT COUNT(DISTINCT um.user_id) FROM tbstwp_usermeta um
        JOIN tbstwp_users u ON u.ID = um.user_id
        WHERE um.meta_key = 'course_completed_{AUDIT_COURSE}'
          AND FROM_UNIXTIME(um.meta_value) >= '{LAUNCH}' {EXCLUDE};""")

    # ── funnel: GA4 landing page ─────────────────────────────────────────
    from google.analytics.data_v1beta.types import Filter, FilterExpression
    c = ga4.client()
    def landing_users(start, end):
        r = ga4.run_report(c, dims=[], metrics=["totalUsers", "sessions"], start=start, end=end,
            dimension_filter=FilterExpression(filter=Filter(field_name="pagePath",
                string_filter=Filter.StringFilter(value="/anal-fissures-breaking-the-cycle-and-the-stigma-landing",
                                                  match_type=Filter.StringFilter.MatchType.BEGINS_WITH))))
        return (int(r.rows[0].metric_values[0].value), int(r.rows[0].metric_values[1].value)) if r.rows else (0, 0)
    land_u_all, land_s_all = landing_users(LAUNCH, "2026-06-30")
    land_u_q,   land_s_q   = landing_users(Q_START, "2026-06-30")

    funnel = [
        {"stage": "1. Viewed the course landing page (GA4 users)", "total_since_launch_19mar_to_30jun": land_u_all, "new_in_amj26_quarter": land_u_q},
        {"stage": "2. Enrolled in the online learning module",     "total_since_launch_19mar_to_30jun": enrolled_all, "new_in_amj26_quarter": enrolled_q},
        {"stage": "3. Started the module (submitted pre-course survey)", "total_since_launch_19mar_to_30jun": pre_all, "new_in_amj26_quarter": pre_q},
        {"stage": "4. Completed the module (submitted post-course survey)", "total_since_launch_19mar_to_30jun": post_all, "new_in_amj26_quarter": post_q},
        {"stage": "4b. Completed the module (LearnDash record - some skip the survey)", "total_since_launch_19mar_to_30jun": module_done_all, "new_in_amj26_quarter": ""},
        {"stage": "5. Commenced the clinical audit (incl saved drafts)", "total_since_launch_19mar_to_30jun": audit_started_all, "new_in_amj26_quarter": audit_started_q},
        {"stage": "6. Submitted the clinical audit",               "total_since_launch_19mar_to_30jun": audit_submitted_all, "new_in_amj26_quarter": audit_submitted_q},
        {"stage": "7. Completed the activity evaluation survey",   "total_since_launch_19mar_to_30jun": eval_all, "new_in_amj26_quarter": eval_q},
        {"stage": "8. MCA fully completed - certificate issued (requires Panwar manual review)", "total_since_launch_19mar_to_30jun": mca_completed_all, "new_in_amj26_quarter": ""},
    ]
    write_titled_csv(outdir / "amj26_mca_funnel.csv",
        "CPD COURSE ENGAGEMENT FUNNEL - Anal Fissure MCA - launched 19 Mar 2026 - counts are real HCPs only (staff/test excluded)",
        funnel)

    # ── surveys ──────────────────────────────────────────────────────────
    pre, post = answers(FORM_PRE), answers(FORM_POST)
    paired = sorted(set(pre) & set(post))
    npost = len(post)
    print(f"pre={len(pre)} post={npost} paired={len(paired)}")

    TOP2 = {"Strongly agree", "Agree"}
    KNOWLEDGE = [
        ("3857", "3633", "Identifying fissures via visual exam"),
        ("3841", "3617", "Distinguishing fissures from other conditions"),
        ("3809", "3569", "Educating patients about bowel habits"),
        ("3745", "3505", "Familiarity with risk factors"),
        ("3793", "3585", "Role of internal anal sphincter"),
        ("3761", "3009", "Comfortable asking about anal symptoms"),
    ]
    krows = []
    for fpre, fpost, label in KNOWLEDGE:
        pa = [pre[u][fpre] for u in paired if pre[u].get(fpre)]
        oa = [post[u][fpost] for u in paired if post[u].get(fpost)]
        pp = pct(sum(a in TOP2 for a in pa), len(pa))
        op = pct(sum(a in TOP2 for a in oa), len(oa))
        krows.append({"confidence_area": label, "before_module_pct": pp, "after_module_pct": op,
                      "improvement_points": f"{op - pp:+d}"})
    write_titled_csv(outdir / "amj26_mca_knowledge_shift.csv",
        f"CONFIDENCE SHIFT - % of HCPs who agreed/strongly agreed they are confident in each area - BEFORE the module vs AFTER (same {len(paired)} people asked twice)",
        krows)

    RISK_LABELS = {"<10%": "Below 10% of my patients", "10–30%": "10-30% of my patients",
                   "30–50%": "30-50% of my patients", ">50%": "Above 50% of my patients"}
    rrows = []
    for bucket, label in RISK_LABELS.items():
        pn = sum(1 for u in paired if pre[u].get("2401") == bucket)
        on = sum(1 for u in paired if post[u].get("2705") == bucket)
        rrows.append({"answer_given": label, "before_module_pct": pct(pn, len(paired)),
                      "after_module_pct": pct(on, len(paired)),
                      "change_points": f"{pct(on, len(paired)) - pct(pn, len(paired)):+d}"})
    write_titled_csv(outdir / "amj26_mca_risk_shift.csv",
        f"RISK PERCEPTION SHIFT - GPs were asked 'what % of your patients are at risk of anal fissure' BEFORE and AFTER the module (same {len(paired)} people)",
        rrows)

    MORE = {"Much more likely", "Somewhat more likely"}
    LESS = {"Much less likely", "Somewhat less likely"}
    NEITHER = {"Neither more nor less likely"}
    INTENT = [("2993", "Sitz baths"), ("3409", "Hydration"),
              ("3377", "Correct bowel movement behaviours"), ("3329", "GTN ointment (Rectogesic)"),
              ("3425", "Fibre supplementation"), ("3345", "Topical corticosteroid")]
    irows = []
    for fid, label in INTENT:
        a = [post[u][fid] for u in post if post[u].get(fid)]
        irows.append({"treatment": label,
                      "pct_more_likely_to_use": pct(sum(x in MORE for x in a), len(a)),
                      "pct_neither_more_nor_less": pct(sum(x in NEITHER for x in a), len(a)),
                      "pct_less_likely_to_use": pct(sum(x in LESS for x in a), len(a)),
                      "how_many_answered": len(a)})
    write_titled_csv(outdir / "amj26_mca_intent.csv",
        f"TREATMENT INTENT - after the module the {npost} completers were asked if they are now more or less likely to use each treatment (5-point scale collapsed; corticosteroid DOWN is the clinically correct direction)",
        irows)

    # ── eval feedback headline (form 97) ─────────────────────────────────
    recommend = sum(1 for u in post if post[u].get("2641") == "Yes")
    change    = sum(1 for u in post if post[u].get("2673") == "Yes")
    # 'Entirely met' items: find form-97 fields whose name matches the slide wording
    frows = db(f"""SELECT id, name FROM tbstwp_frm_fields WHERE form_id = {FORM_POST};""")
    met_items = []
    for fid, name in frows:
        nl = name.lower()
        if any(k in nl for k in ("current", "user friendly", "user-friendly", "navigat", "engaging", "interactive")):
            vals = [post[u][fid] for u in post if post[u].get(fid)]
            if vals and any(v in ("Entirely met", "Partially met", "Not met") for v in vals):
                # ASCII-safe label: swap unicode dashes, drop bracketed examples, tidy spaces
                clean = name.replace("–", "-").replace("—", "-").replace("’", "'")
                clean = clean.split("(")[0].strip().rstrip(",;- ")
                met_items.append({"metric": f"{clean} (rated 'Entirely met')",
                                  "value": f"{pct(sum(v == 'Entirely met' for v in vals), len(vals))}%",
                                  "n": len(vals)})
    feedback = [
        {"question": "Would you recommend this activity to a colleague? (said Yes)", "result": f"{pct(recommend, npost)}%", "how_many_answered": npost},
        {"question": "Will you change something in your practice? (said Yes)", "result": f"{pct(change, npost)}%", "how_many_answered": npost},
    ] + [{"question": m["metric"], "result": m["value"], "how_many_answered": m["n"]} for m in met_items]
    write_titled_csv(outdir / "amj26_mca_eval_feedback.csv",
        f"COURSE FEEDBACK - what the {npost} HCPs who completed the learning module said in the post-course survey (cumulative since 19 Mar launch)",
        feedback)

    for f in ("amj26_mca_funnel", "amj26_mca_eval_feedback", "amj26_mca_knowledge_shift",
              "amj26_mca_risk_shift", "amj26_mca_intent"):
        io.copy_to_downloads(outdir / f"{f}.csv")


if __name__ == "__main__":
    main()

"""Deep DB pull — every metric we can squeeze from the WordPress / LearnDash / RCP / Formidable schema.
Outputs CSVs in reports/out/db/.
"""
import csv
import os
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "reports" / "out" / "db"
OUT.mkdir(parents=True, exist_ok=True)


def env(key: str) -> str:
    for line in (ROOT / ".env").read_text().splitlines():
        if line.startswith(f"{key}="):
            return line.split("=", 1)[1].strip()
    raise KeyError(key)


def q(sql: str, name: str) -> list[dict]:
    """Run SQL via docker compose, parse TSV result, write CSV."""
    cmd = [
        "docker", "compose", "exec", "-T", "db",
        "mysql", "-uroot", f"-p{env('MYSQL_ROOT_PASSWORD')}",
        "hcp_care", "--batch", "--raw", "-e", sql,
    ]
    res = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True)
    lines = [l for l in res.stdout.splitlines() if not l.startswith("mysql:")]
    if not lines:
        print(f"  {name}: empty result")
        return []
    header = lines[0].split("\t")
    rows = [dict(zip(header, l.split("\t"))) for l in lines[1:]]
    out_path = OUT / f"{name}.csv"
    with out_path.open("w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=header)
        w.writeheader()
        w.writerows(rows)
    print(f"  wrote {out_path.relative_to(ROOT)}  ({len(rows)} rows)")
    return rows


def main() -> None:
    # --- Sign-ups ---
    q("""SELECT DATE_FORMAT(user_registered, '%Y-%m') AS month, COUNT(*) AS signups
         FROM tbstwp_users WHERE user_registered >= '2021-01-01'
         GROUP BY month ORDER BY month;""",
      "signups_monthly")

    q("""SELECT DATE_FORMAT(user_registered, '%Y-%m-%d') AS day, COUNT(*) AS signups
         FROM tbstwp_users WHERE user_registered >= '2025-01-01'
         GROUP BY day ORDER BY day;""",
      "signups_daily_2025_2026")

    # --- Form submissions ---
    q("""SELECT i.form_id, f.name AS form_name, DATE_FORMAT(i.created_at, '%Y-%m') AS month,
                COUNT(*) AS submissions
         FROM tbstwp_frm_items i
         JOIN tbstwp_frm_forms f ON f.id = i.form_id
         WHERE i.is_draft = 0 AND i.created_at >= '2024-01-01'
         GROUP BY i.form_id, f.name, month
         ORDER BY i.form_id, month;""",
      "form_submissions_monthly")

    q("""SELECT id, name, status, parent_form_id, logged_in,
                (SELECT COUNT(*) FROM tbstwp_frm_items WHERE form_id=tbstwp_frm_forms.id AND is_draft=0) AS total_submissions
         FROM tbstwp_frm_forms
         WHERE parent_form_id = 0
         ORDER BY id;""",
      "forms_overview")

    # --- Sample order details (form 55) — what was ordered ---
    q("""SELECT fl.name AS field_name, fl.id AS field_id, COUNT(DISTINCT m.item_id) AS submissions_with_value
         FROM tbstwp_frm_fields fl
         LEFT JOIN tbstwp_frm_item_metas m ON m.field_id = fl.id
         WHERE fl.form_id = 55
         GROUP BY fl.id, fl.name
         ORDER BY fl.field_order;""",
      "form_55_field_inventory")

    # --- LearnDash activity ---
    q("""SELECT activity_type, COUNT(*) AS events, COUNT(DISTINCT user_id) AS users
         FROM tbstwp_learndash_user_activity
         GROUP BY activity_type;""",
      "learndash_activity_types")

    q("""SELECT DATE_FORMAT(FROM_UNIXTIME(activity_updated), '%Y-%m') AS month,
                activity_type,
                COUNT(*) AS events,
                COUNT(DISTINCT user_id) AS unique_users
         FROM tbstwp_learndash_user_activity
         WHERE activity_updated > 0
         GROUP BY month, activity_type
         ORDER BY month, activity_type;""",
      "learndash_activity_monthly")

    q("""SELECT post_id,
                COUNT(DISTINCT user_id) AS users_started,
                SUM(CASE WHEN activity_completed > 0 THEN 1 ELSE 0 END) AS completions
         FROM tbstwp_learndash_user_activity
         WHERE activity_type IN ('course', 'lesson', 'topic', 'quiz')
         GROUP BY post_id
         ORDER BY users_started DESC LIMIT 50;""",
      "learndash_post_engagement")

    # --- Quiz attempts and pass rates ---
    q("""SELECT COUNT(*) AS total_attempts,
                COUNT(DISTINCT user_id) AS unique_users,
                COUNT(DISTINCT quiz_id) AS unique_quizzes
         FROM tbstwp_learndash_pro_quiz_statistic_ref;""",
      "quiz_attempts_overall")

    q("""SELECT DATE_FORMAT(FROM_UNIXTIME(create_time), '%Y-%m') AS month,
                COUNT(*) AS attempts,
                COUNT(DISTINCT user_id) AS unique_users
         FROM tbstwp_learndash_pro_quiz_statistic_ref
         WHERE create_time > 0
         GROUP BY month ORDER BY month;""",
      "quiz_attempts_monthly")

    # --- RCP membership ---
    q("""SELECT object_id, COUNT(*) AS members,
                MIN(created_date) AS earliest,
                MAX(created_date) AS latest
         FROM tbstwp_rcp_memberships
         GROUP BY object_id ORDER BY members DESC;""",
      "rcp_levels")

    q("""SELECT DATE_FORMAT(created_date, '%Y-%m') AS month,
                COUNT(*) AS new_memberships
         FROM tbstwp_rcp_memberships
         WHERE created_date >= '2024-01-01'
         GROUP BY month ORDER BY month;""",
      "rcp_memberships_monthly")

    # --- User demographics ---
    q("""SELECT
            CASE
                WHEN UPPER(meta_value) IN ('NSW','NEW SOUTH WALES') THEN 'NSW'
                WHEN UPPER(meta_value) IN ('VIC','VICTORIA') THEN 'VIC'
                WHEN UPPER(meta_value) IN ('QLD','QUEENSLAND') THEN 'QLD'
                WHEN UPPER(meta_value) IN ('WA','WESTERN AUSTRALIA') THEN 'WA'
                WHEN UPPER(meta_value) IN ('SA','SOUTH AUSTRALIA') THEN 'SA'
                WHEN UPPER(meta_value) IN ('ACT','AUSTRALIAN CAPITAL TERRITORY') THEN 'ACT'
                WHEN UPPER(meta_value) IN ('TAS','TASMANIA') THEN 'TAS'
                WHEN UPPER(meta_value) = 'NT' THEN 'NT'
                ELSE 'OTHER/MALFORMED'
            END AS state_norm,
            COUNT(*) AS users
         FROM tbstwp_usermeta
         WHERE meta_key='state' AND meta_value != ''
         GROUP BY state_norm ORDER BY users DESC;""",
      "users_by_state")

    q("""SELECT meta_value AS specialty, COUNT(*) AS users
         FROM tbstwp_usermeta
         WHERE meta_key='specialty' AND meta_value != ''
         GROUP BY meta_value ORDER BY users DESC;""",
      "users_by_specialty")

    q("""SELECT meta_value AS hear_us, COUNT(*) AS users
         FROM tbstwp_usermeta
         WHERE meta_key='hear_us' AND meta_value != ''
         GROUP BY meta_value ORDER BY users DESC;""",
      "users_by_hear_us")

    q("""SELECT
            CASE
                WHEN um.meta_value REGEXP '^MED' THEN 'GP/Doctor (MED)'
                WHEN um.meta_value REGEXP '^NMW' THEN 'Nurse/Midwife (NMW)'
                WHEN um.meta_value REGEXP '^PHA' THEN 'Pharmacist (PHA)'
                WHEN um.meta_value REGEXP '^DEN' THEN 'Dental (DEN)'
                WHEN um.meta_value REGEXP '^OPT' THEN 'Optometrist (OPT)'
                WHEN um.meta_value REGEXP '^PHY' THEN 'Physio (PHY)'
                WHEN um.meta_value REGEXP '^PSY' THEN 'Psychology (PSY)'
                WHEN um.meta_value REGEXP '^CHI' THEN 'Chiropractor (CHI)'
                WHEN um.meta_value = '' THEN '(blank)'
                ELSE 'Other/Invalid'
            END AS ahpra_profession,
            COUNT(*) AS users
         FROM tbstwp_usermeta um
         WHERE um.meta_key = 'ahpra_id'
         GROUP BY ahpra_profession ORDER BY users DESC;""",
      "users_by_ahpra_profession")

    # --- Active users via last_login_time ---
    q("""SELECT
            CASE
                WHEN last_login = '' OR last_login IS NULL THEN 'never_logged_in'
                WHEN last_login_dt >= '2026-04-01' THEN 'active_apr_2026'
                WHEN last_login_dt >= '2026-01-01' THEN 'active_jfm_2026'
                WHEN last_login_dt >= '2025-04-01' THEN 'active_apr2025_dec2025'
                WHEN last_login_dt >= '2024-04-01' THEN 'active_apr2024_mar2025'
                ELSE 'inactive_pre_apr2024'
            END AS bucket,
            COUNT(*) AS users
         FROM (
             SELECT um.meta_value AS last_login,
                    CASE WHEN um.meta_value REGEXP '^[0-9]+$' THEN FROM_UNIXTIME(um.meta_value) ELSE NULL END AS last_login_dt
             FROM tbstwp_users u
             LEFT JOIN tbstwp_usermeta um ON um.user_id = u.ID AND um.meta_key='last_login_time'
         ) sub
         GROUP BY bucket ORDER BY users DESC;""",
      "user_activity_buckets")

    q("""SELECT DATE_FORMAT(FROM_UNIXTIME(um.meta_value), '%Y-%m') AS month,
                COUNT(DISTINCT um.user_id) AS active_users
         FROM tbstwp_usermeta um
         WHERE um.meta_key='last_login_time' AND um.meta_value REGEXP '^[0-9]+$'
         GROUP BY month ORDER BY month;""",
      "monthly_last_login_distribution")

    # --- Cross: signups by source (registered users * hear_us) ---
    q("""SELECT DATE_FORMAT(u.user_registered, '%Y-%m') AS month,
                COALESCE(NULLIF(um.meta_value, ''), '(no answer)') AS hear_us,
                COUNT(*) AS signups
         FROM tbstwp_users u
         LEFT JOIN tbstwp_usermeta um ON um.user_id = u.ID AND um.meta_key='hear_us'
         WHERE u.user_registered >= '2024-01-01'
         GROUP BY month, hear_us
         ORDER BY month, signups DESC;""",
      "signups_by_hear_us_monthly")

    # --- Posts published by month (article cadence) ---
    q("""SELECT DATE_FORMAT(post_date, '%Y-%m') AS month, post_type, COUNT(*) AS posts_published
         FROM tbstwp_posts
         WHERE post_status = 'publish'
           AND post_type IN ('post', 'page', 'sfwd-courses', 'sfwd-lessons', 'sfwd-quiz', 'sfwd-topic')
           AND post_date >= '2024-01-01'
         GROUP BY month, post_type ORDER BY month, post_type;""",
      "posts_published_monthly")

    # --- MCA audit submissions detail ---
    q("""SELECT i.id AS submission_id, i.user_id, u.user_login, u.user_registered, i.created_at, i.is_draft
         FROM tbstwp_frm_items i
         LEFT JOIN tbstwp_users u ON u.ID = i.user_id
         WHERE i.form_id = 161
         ORDER BY i.created_at;""",
      "mca_audit_submissions")

    q("""SELECT i.id AS submission_id, i.user_id, u.user_login, u.user_registered, i.created_at, i.is_draft
         FROM tbstwp_frm_items i
         LEFT JOIN tbstwp_users u ON u.ID = i.user_id
         WHERE i.form_id = 209
         ORDER BY i.created_at;""",
      "mca_eval_submissions")

    # --- Cross-channel summary: form 55 monthly with extra granularity ---
    q("""SELECT DATE_FORMAT(i.created_at, '%Y-%m') AS month,
                COUNT(*) AS orders,
                COUNT(DISTINCT i.user_id) AS unique_users
         FROM tbstwp_frm_items i
         WHERE i.form_id = 55 AND i.is_draft = 0 AND i.created_at >= '2024-01-01'
         GROUP BY month ORDER BY month;""",
      "order_samples_monthly")


if __name__ == "__main__":
    main()

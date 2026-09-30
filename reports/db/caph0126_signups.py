"""
CAPH0126 eDM sign-ups from the prod DB, split by send window.

GA4 undercounts registrations on this property, so the DB is the truth for
sign-up counts. Internal accounts are excluded.

    reports/.venv/bin/python reports/db/caph0126_signups.py
"""
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io

WINDOWS = [
    ("Medicine Today", "2026-08-20", "2026-08-25"),
    ("The Medical Republic", "2026-08-25", "2026-08-30"),
]

EXCLUDE = """
    AND user_email NOT LIKE '%@carepharma.com.au'
    AND user_email NOT LIKE '%@panwarhealth.com.au'
    AND user_email NOT LIKE '%@tbstdigital.com.au'
""".strip()


def _secrets() -> dict:
    out = {}
    for line in (io.PROJECT_ROOT / "infra" / ".prod-secrets").read_text().splitlines():
        if "=" in line and not line.startswith("#"):
            k, _, v = line.partition("=")
            out[k.strip()] = v.strip().strip('"\'')
    return out


_S = _secrets()
SSH = ["sshpass", "-p", _S["PROD_PASS"], "ssh", "-p", _S.get("PROD_PORT", "9022"),
       "-o", "ConnectTimeout=30", f"{_S['PROD_USER']}@{_S['PROD_HOST']}"]
# wp-cli reads prod's own wp-config, so the DB user/password never leave the server.
MYSQL = "cd /var/www/hcp.carepharma.com.au/httpdocs && wp db query"


def db(sql: str) -> list[list[str]]:
    r = subprocess.run(SSH + [MYSQL], input=sql, capture_output=True, text=True)
    lines = [l for l in r.stdout.splitlines() if l and not l.startswith("Warning")]
    if r.returncode != 0 and not lines:
        raise RuntimeError(r.stderr[:300])
    return [l.split("\t") for l in lines[1:]]


def main() -> None:
    rows = []
    for label, start, end in WINDOWS:
        daily = db(f"""
            SELECT DATE(user_registered) d, COUNT(*) n
            FROM tbstwp_users
            WHERE user_registered >= '{start}' AND user_registered < '{end}'
              {EXCLUDE}
            GROUP BY d ORDER BY d;""")
        total = sum(int(n) for _, n in daily)
        print(f"\n{label}  {start} to {end}  ->  {total} sign-ups")
        for d, n in daily:
            print(f"  {d}  {n}")
            rows.append({"send": label, "date": d, "signups": int(n)})
        rows.append({"send": label, "date": "TOTAL", "signups": total})

    io.write_dicts(io.out_dir("db") / "caph0126_signups_by_send.csv", rows)


if __name__ == "__main__":
    main()

"""Run SQL against the local dockerised MySQL (service `db`, schema hcp_care).

Reads MYSQL_ROOT_PASSWORD from the repo .env. SQL is piped over stdin so
multi-statement scripts (e.g. `SET time_zone=...; SELECT ...`) work.
"""
from __future__ import annotations

import subprocess

from .io import PROJECT_ROOT, out_dir, write_dicts


def _env(key: str) -> str:
    for line in (PROJECT_ROOT / ".env").read_text().splitlines():
        if line.startswith(f"{key}="):
            return line.split("=", 1)[1].strip()
    raise KeyError(f"{key} not found in {PROJECT_ROOT / '.env'}")


def query(sql: str, *, column_names: bool = True) -> list[dict] | list[list[str]]:
    """Execute SQL in the docker `db` container.

    With column_names=True (default) returns a list of dict rows (first line
    is the header). With column_names=False returns raw list-of-lists with no
    header — handy for ad-hoc two-column pulls.
    """
    cmd = [
        "docker", "compose", "exec", "-T", "db",
        "mysql", "-uroot", f"-p{_env('MYSQL_ROOT_PASSWORD')}",
        "hcp_care", "--batch", "--raw",
    ]
    if not column_names:
        cmd.append("--skip-column-names")
    res = subprocess.run(cmd, input=sql, cwd=PROJECT_ROOT, capture_output=True, text=True)
    lines = [l for l in res.stdout.splitlines() if not l.startswith("mysql:")]
    if not lines:
        if res.returncode != 0:
            raise RuntimeError(f"mysql failed: {res.stderr.strip()}")
        return []
    if not column_names:
        return [l.split("\t") for l in lines]
    header = lines[0].split("\t")
    return [dict(zip(header, l.split("\t"))) for l in lines[1:]]


def query_to_csv(sql: str, name: str, subdir: str = "db") -> list[dict]:
    """Run SQL and write the result to reports/out/<subdir>/<name>.csv."""
    rows = query(sql)
    write_dicts(out_dir(subdir) / f"{name}.csv", rows)
    return rows

"""Run read-only SQL against the PROD WordPress DB over SSH.

SSH credentials come from the gitignored infra/.prod-secrets. The query runs
through prod's own `wp db query`, so the DB user/password never leave the
server. Same return shape as lib.db.query.
"""
from __future__ import annotations

import os
import subprocess

from .io import PROJECT_ROOT

WP_ROOT = "/var/www/hcp-carepharma/httpdocs"


def _secrets() -> dict[str, str]:
    out = {}
    for line in (PROJECT_ROOT / "infra" / ".prod-secrets").read_text().splitlines():
        if "=" in line and not line.startswith("#"):
            k, _, v = line.partition("=")
            out[k.strip()] = v.strip().strip("\"'")
    return out


def query(sql: str, *, column_names: bool = True) -> list[dict] | list[list[str]]:
    s = _secrets()
    cmd = ["sshpass", "-e", "ssh", "-p", s.get("PROD_PORT", "9022"),
           "-o", "ConnectTimeout=30", f"{s['PROD_USER']}@{s['PROD_HOST']}",
           f"cd {WP_ROOT} && wp db query"]
    res = subprocess.run(cmd, input=sql, capture_output=True, text=True,
                         env={**os.environ, "SSHPASS": s["PROD_PASS"]})
    lines = [l for l in res.stdout.splitlines() if l and not l.startswith("Warning")]
    if not lines:
        if res.returncode != 0:
            raise RuntimeError(f"prod query failed: {res.stderr.strip()[:300]}")
        return []
    if not column_names:
        return [l.split("\t") for l in lines[1:]]
    header = lines[0].split("\t")
    return [dict(zip(header, l.split("\t"))) for l in lines[1:]]

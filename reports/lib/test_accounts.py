"""Our own test sign-ups, kept out of sign-up and engagement counts.

The list lives in the gitignored reports/.test-accounts: one email address or
AHPRA number per line (# comments allowed). Matches user_email, user_login and
the ahpra_id user meta.
"""
from __future__ import annotations

import re

from .io import PROJECT_ROOT

FILE = PROJECT_ROOT / "reports" / ".test-accounts"
_SAFE = re.compile(r"^[A-Za-z0-9._%+@-]+$")


def _entries() -> list[str]:
    if not FILE.exists():
        print(f"NOTE: {FILE.name} not found; no test accounts excluded")
        return []
    out = []
    for line in FILE.read_text().splitlines():
        v = line.split("#", 1)[0].strip()
        if not v:
            continue
        if not _SAFE.match(v):
            raise ValueError(f"unexpected characters in {FILE.name}: {v!r}")
        out.append(v)
    return out


def sql_exclude(user_col: str) -> str:
    """SQL condition that drops the test accounts, e.g. sql_exclude('u.ID')."""
    vals = _entries()
    if not vals:
        return "1=1"
    lst = ", ".join(f"'{v.lower()}'" for v in vals)
    return (f"{user_col} NOT IN ("
            f"SELECT t.ID FROM tbstwp_users t WHERE LOWER(t.user_email) IN ({lst}) OR LOWER(t.user_login) IN ({lst}) "
            f"UNION SELECT t.user_id FROM tbstwp_usermeta t WHERE t.meta_key = 'ahpra_id' AND LOWER(TRIM(t.meta_value)) IN ({lst}))")

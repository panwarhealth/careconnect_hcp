"""Filesystem helpers shared by every report: output dirs, CSV writers,
and the WSL→Windows Downloads copy (so CSVs land where the client expects).
"""
from __future__ import annotations

import csv
import shutil
from pathlib import Path

REPORTS_ROOT = Path(__file__).resolve().parent.parent   # reports/
PROJECT_ROOT = REPORTS_ROOT.parent                       # repo root
OUT = REPORTS_ROOT / "out"


def out_dir(*parts: str) -> Path:
    """Return reports/out/<parts...>, creating it if needed."""
    d = OUT.joinpath(*parts)
    d.mkdir(parents=True, exist_ok=True)
    return d


def write_csv(path: Path, header: list[str], rows: list[list]) -> Path:
    """Write a header + list-of-lists to CSV."""
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="") as f:
        w = csv.writer(f)
        w.writerow(header)
        w.writerows(rows)
    print(f"wrote {path.relative_to(PROJECT_ROOT)}  ({len(rows)} rows)")
    return path


def write_dicts(path: Path, rows: list[dict]) -> Path:
    """Write a list of dicts to CSV, columns taken from the first row."""
    if not rows:
        print(f"(no rows — skipped {path.name})")
        return path
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader()
        w.writerows(rows)
    print(f"wrote {path.relative_to(PROJECT_ROOT)}  ({len(rows)} rows)")
    return path


# Windows system profiles that have a Downloads dir but aren't a real user.
_SKIP_PROFILES = {"Default", "Default User", "Public", "All Users"}


def find_windows_downloads() -> Path | None:
    """Locate a writable Windows Downloads folder from inside WSL, or None."""
    users_root = Path("/mnt/c/Users")
    if not users_root.exists():
        return None
    import os
    for user_dir in sorted(users_root.iterdir()):
        if user_dir.name in _SKIP_PROFILES:
            continue
        dl = user_dir / "Downloads"
        if dl.is_dir() and os.access(dl, os.W_OK):
            return dl
    return None


def copy_to_downloads(path: Path) -> Path | None:
    """Best-effort copy of a finished file to Windows Downloads under WSL.

    Never fatal: the file in reports/out/ is the source of truth, so a missing
    or non-writable Downloads folder only produces a warning.
    """
    dl = find_windows_downloads()
    if dl is None:
        print("Windows Downloads not found — left file in reports/out/ only")
        return None
    dest = dl / path.name
    try:
        shutil.copy(path, dest)
    except OSError as e:
        print(f"could not copy to {dest} ({e}) — left file in reports/out/ only")
        return None
    print(f"copied → {dest}")
    return dest

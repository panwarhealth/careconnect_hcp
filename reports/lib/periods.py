"""Reporting periods for Care Pharma's fiscal calendar.

Care Pharma's FY runs **April 1 – March 31**. The FY label is the calendar
year in which the FY *ends*:

    FY26      = 2025-04-01 .. 2026-03-31
    FY26 Q1   = 2025-04-01 .. 2025-06-30   (Apr–Jun)
    FY26 Q2   = 2025-07-01 .. 2025-09-30   (Jul–Sep)
    FY26 Q3   = 2025-10-01 .. 2025-12-31   (Oct–Dec)
    FY26 Q4   = 2026-01-01 .. 2026-03-31   (Jan–Mar)   <- the old "JFM 2026"

Scripts take `--period FY26Q4` (or `--start/--end` for an arbitrary window)
so next quarter is a zero-edit rerun. Use `add_arguments()` / `from_args()`.
"""
from __future__ import annotations

import argparse
import re
from calendar import monthrange
from dataclasses import dataclass
from datetime import date

FY_START_MONTH = 4  # April

_QUARTER_START_MONTH = {1: 4, 2: 7, 3: 10, 4: 1}  # Q4 wraps into the FY-end calendar year


@dataclass(frozen=True)
class Period:
    start: str   # YYYY-MM-DD inclusive
    end: str     # YYYY-MM-DD inclusive
    label: str   # human label, e.g. "FY26 Q4"
    fy: int | None = None       # FY-end calendar year, e.g. 2026
    quarter: int | None = None  # 1-4, or None for a full FY / custom window

    @property
    def slug(self) -> str:
        """Filename-safe form, e.g. 'fy26q4' or '2025-04-01_2026-03-31'."""
        if self.fy is None:
            return f"{self.start}_{self.end}"
        s = f"fy{self.fy % 100:02d}"
        return f"{s}q{self.quarter}" if self.quarter else s

    @property
    def prior_year(self) -> "Period":
        """Same window, one fiscal year earlier — for YoY comparisons."""
        if self.fy is None:
            raise ValueError("prior_year is only defined for FY/quarter periods")
        spec = f"FY{(self.fy - 1) % 100:02d}"
        if self.quarter:
            spec += f"Q{self.quarter}"
        return resolve(spec)


def _last_day(year: int, month: int) -> int:
    return monthrange(year, month)[1]


def _fy_bounds(fy: int) -> tuple[date, date]:
    return date(fy - 1, FY_START_MONTH, 1), date(fy, FY_START_MONTH - 1, 31)


def _quarter_bounds(fy: int, q: int) -> tuple[date, date]:
    month = _QUARTER_START_MONTH[q]
    year = fy if q == 4 else fy - 1
    start = date(year, month, 1)
    end_month = month + 2
    return start, date(year, end_month, _last_day(year, end_month))


def resolve(spec: str) -> Period:
    """Parse 'FY26' / 'FY26Q4' / 'FY26 Q4' into a Period."""
    s = spec.upper().replace(" ", "").replace("-", "")
    m = re.fullmatch(r"FY(\d{2})(?:Q([1-4]))?", s)
    if not m:
        raise ValueError(f"bad period {spec!r}; expected e.g. 'FY26' or 'FY26Q4'")
    fy = 2000 + int(m.group(1))
    if m.group(2):
        q = int(m.group(2))
        start, end = _quarter_bounds(fy, q)
        return Period(start.isoformat(), end.isoformat(), f"FY{m.group(1)} Q{q}", fy, q)
    start, end = _fy_bounds(fy)
    return Period(start.isoformat(), end.isoformat(), f"FY{m.group(1)}", fy, None)


def add_arguments(parser: argparse.ArgumentParser, default: str = "FY26") -> argparse.ArgumentParser:
    """Add --period / --start / --end to a parser."""
    g = parser.add_argument_group("reporting period")
    g.add_argument("--period", default=default,
                   help=f"FY period, e.g. FY26 or FY26Q4 (default: {default})")
    g.add_argument("--start", help="explicit start YYYY-MM-DD (with --end, overrides --period)")
    g.add_argument("--end", help="explicit end YYYY-MM-DD")
    return parser


def from_args(args: argparse.Namespace) -> Period:
    """Resolve the Period chosen on the command line."""
    if args.start or args.end:
        if not (args.start and args.end):
            raise SystemExit("--start and --end must be given together")
        return Period(args.start, args.end, f"{args.start}..{args.end}")
    return resolve(args.period)


if __name__ == "__main__":
    # Quick self-check: `python reports/lib/periods.py FY26 FY26Q4 FY25Q1`
    import sys
    for spec in (sys.argv[1:] or ["FY26", "FY26Q1", "FY26Q2", "FY26Q3", "FY26Q4", "FY25"]):
        p = resolve(spec)
        print(f"{spec:9} -> {p.start} .. {p.end}   label={p.label!r} slug={p.slug} "
              f"prior_year={p.prior_year.start}..{p.prior_year.end}")

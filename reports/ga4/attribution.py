"""Attribution: of sessions that hit /register, /register/, or registration pages,
what was the source/medium? Compare JFM 2025 vs JFM 2026.

Usage: python reports/ga4/attribution.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import ga4, io

from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    Metric,
    OrderBy,
    RunReportRequest,
)

PROPERTY_ID = ga4.PROPERTY_ID


def write_csv(path: Path, header, rows) -> None:
    io.write_csv(path, header, rows)


def reg_filter() -> FilterExpression:
    return FilterExpression(
        filter=Filter(
            field_name="pagePath",
            string_filter=Filter.StringFilter(
                match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                value="(?i)^/register",
            ),
        ),
    )


def main() -> None:
    c = ga4.client()
    out = io.out_dir("ga4")

    # TODO: parameterize via lib.periods — this script runs YoY window pairs
    # (JFM 2025 vs JFM 2026, FY25 vs FY26), so the date constants stay.
    for label, start, end in [
        ("jfm_2025", "2025-01-01", "2025-03-31"),
        ("jfm_2026", "2026-01-01", "2026-03-31"),
        ("apr2024_mar2025", "2024-04-01", "2025-03-31"),
        ("apr2025_mar2026", "2025-04-01", "2026-03-31"),
    ]:
        # Sessions that landed on /register, broken down by acquisition source/medium
        req = RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=start, end_date=end)],
            dimensions=[Dimension(name="sessionSource"), Dimension(name="sessionMedium"), Dimension(name="sessionDefaultChannelGroup")],
            metrics=[Metric(name="sessions"), Metric(name="totalUsers"), Metric(name="newUsers")],
            dimension_filter=FilterExpression(
                filter=Filter(
                    field_name="landingPagePlusQueryString",
                    string_filter=Filter.StringFilter(
                        match_type=Filter.StringFilter.MatchType.PARTIAL_REGEXP,
                        value="(?i)^/register",
                    ),
                ),
            ),
            order_bys=[OrderBy(metric=OrderBy.MetricOrderBy(metric_name="sessions"), desc=True)],
            limit=200,
        )
        resp = c.run_report(req)
        rows = []
        for r in resp.rows:
            dims = [v.value for v in r.dimension_values]
            mets = [v.value for v in r.metric_values]
            rows.append(dims + mets)
        write_csv(out / f"register_landing_{label}.csv",
                  ["source", "medium", "channel", "sessions", "users", "new_users"], rows)


if __name__ == "__main__":
    main()

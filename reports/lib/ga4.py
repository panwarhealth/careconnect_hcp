"""GA4 Data API client + query helpers.

One place for the OAuth refresh-token dance that ~28 scripts used to each
re-implement. Credentials live in ../.secrets/ga4-token.json (gitignored);
bootstrap them once with `python reports/ga4_auth.py`.
"""
from __future__ import annotations

import json

from google.analytics.data_v1beta import BetaAnalyticsDataClient
from google.analytics.data_v1beta.types import (
    DateRange,
    Dimension,
    Filter,
    FilterExpression,
    Metric,
    OrderBy,
    RunReportRequest,
)
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials

from .io import PROJECT_ROOT

PROPERTY_ID = "306115293"
TOKEN_JSON = PROJECT_ROOT / ".secrets" / "ga4-token.json"


def credentials() -> Credentials:
    """Load the stored refresh token, refresh the access token, persist it back."""
    if not TOKEN_JSON.exists():
        raise FileNotFoundError(
            f"{TOKEN_JSON} not found — run `python reports/ga4_auth.py` to authorise once"
        )
    info = json.loads(TOKEN_JSON.read_text())
    creds = Credentials(
        token=info.get("token"),
        refresh_token=info["refresh_token"],
        token_uri=info["token_uri"],
        client_id=info["client_id"],
        client_secret=info["client_secret"],
        scopes=info.get("scopes"),
    )
    creds.refresh(Request())
    TOKEN_JSON.write_text(creds.to_json())
    return creds


def client() -> BetaAnalyticsDataClient:
    return BetaAnalyticsDataClient(credentials=credentials())


def metric_order(name: str, desc: bool = True) -> OrderBy:
    return OrderBy(metric=OrderBy.MetricOrderBy(metric_name=name), desc=desc)


def dimension_order(name: str, desc: bool = False) -> OrderBy:
    return OrderBy(dimension=OrderBy.DimensionOrderBy(dimension_name=name), desc=desc)


def channel_filter(value: str = "Email") -> FilterExpression:
    """Filter to a single sessionDefaultChannelGroup (default: Email)."""
    return FilterExpression(
        filter=Filter(
            field_name="sessionDefaultChannelGroup",
            string_filter=Filter.StringFilter(value=value),
        )
    )


def run_report(
    c: BetaAnalyticsDataClient,
    *,
    dims: list[str],
    metrics: list[str],
    start: str,
    end: str,
    dimension_filter: FilterExpression | None = None,
    order_by: list[OrderBy] | None = None,
    limit: int = 100000,
):
    """Run a single-date-range report. Returns the raw RunReportResponse."""
    return c.run_report(
        RunReportRequest(
            property=f"properties/{PROPERTY_ID}",
            date_ranges=[DateRange(start_date=start, end_date=end)],
            dimensions=[Dimension(name=d) for d in dims],
            metrics=[Metric(name=m) for m in metrics],
            dimension_filter=dimension_filter,
            order_bys=order_by or [],
            limit=limit,
        )
    )


def rows_of(resp) -> list[list[str]]:
    """Flatten a response into [dim values... + metric values...] string rows."""
    return [
        [v.value for v in row.dimension_values] + [v.value for v in row.metric_values]
        for row in resp.rows
    ]


def dicts_of(resp, dim_names: list[str], metric_names: list[str]) -> list[dict]:
    """Flatten a response into dicts keyed by the given column names."""
    cols = dim_names + metric_names
    return [dict(zip(cols, r)) for r in rows_of(resp)]

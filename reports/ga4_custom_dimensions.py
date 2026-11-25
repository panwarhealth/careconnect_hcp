"""Registers the event parameters the site sends as GA4 custom dimensions, so they show in reports.

Usage:
    python reports/ga4_custom_dimensions.py           # list what exists and what would be created
    python reports/ga4_custom_dimensions.py --apply   # create the missing ones

Needs the edit token from `python reports/ga4_auth.py --admin`. Safe to re-run: existing
dimensions are skipped. GA4 only fills a dimension from the moment it is created.
"""
import sys
from pathlib import Path

from google.auth.transport.requests import AuthorizedSession, Request
from google.oauth2.credentials import Credentials

ROOT = Path(__file__).resolve().parent.parent
TOKEN_JSON = ROOT / ".secrets" / "ga4-admin-token.json"
PROPERTY = "306115293"
API = f"https://analyticsadmin.googleapis.com/v1beta/properties/{PROPERTY}/customDimensions"

# Event parameters sent by wp-spinnr-child/case-study/case-study.js
DIMENSIONS = [
    ("case_study", "Case study", "Which interactive case study the event came from (post slug)"),
    ("cs_step", "Case study step", "Section of the case study: meet_jess, investigate, factors_question, discuss, prepare"),
    ("cs_result", "Case study result", "correct or incorrect for a Check your answer press"),
    ("cs_attempt", "Case study attempt", "Attempt number for a Check your answer press"),
    ("cs_resource", "Case study resource", "Resource card or Order samples link clicked at the end"),
]


def session() -> AuthorizedSession:
    if not TOKEN_JSON.exists():
        sys.exit(f"No admin token at {TOKEN_JSON}. Run: python reports/ga4_auth.py --admin")
    creds = Credentials.from_authorized_user_file(str(TOKEN_JSON))
    if not creds.valid:
        creds.refresh(Request())
        TOKEN_JSON.write_text(creds.to_json())
    return AuthorizedSession(creds)


def main() -> None:
    s = session()
    existing, page = {}, None
    while True:
        r = s.get(API, params={"pageSize": 200, **({"pageToken": page} if page else {})})
        r.raise_for_status()
        body = r.json()
        for d in body.get("customDimensions", []):
            existing[d["parameterName"]] = d
        page = body.get("nextPageToken")
        if not page:
            break

    apply = "--apply" in sys.argv
    for param, name, desc in DIMENSIONS:
        if param in existing:
            print(f"exists   {param:<12} ({existing[param]['displayName']}, {existing[param]['scope']})")
            continue
        if not apply:
            print(f"missing  {param:<12} would create '{name}'")
            continue
        r = s.post(API, json={"parameterName": param, "displayName": name, "description": desc, "scope": "EVENT"})
        r.raise_for_status()
        print(f"created  {param:<12} '{name}'")
    if not apply:
        print("\nPreview only. Re-run with --apply to create the missing dimensions.")


if __name__ == "__main__":
    main()

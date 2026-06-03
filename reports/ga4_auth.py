"""One-time OAuth flow: produces .secrets/ga4-token.json with a refresh token.

Usage:
    python reports/ga4_auth.py
"""
import sys
from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow

ROOT = Path(__file__).resolve().parent.parent
CLIENT_JSON = ROOT / ".secrets" / "ga4-oauth-client.json"
TOKEN_JSON = ROOT / ".secrets" / "ga4-token.json"
SCOPES = ["https://www.googleapis.com/auth/analytics.readonly"]
PORT = 8766


def main() -> None:
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT_JSON), SCOPES)
    msg = "\n=== Open this URL in your browser ===\n\n{url}\n\n(Listening on http://localhost:" + str(PORT) + "/ — sign in, click Allow.)\n"
    creds = flow.run_local_server(
        port=PORT,
        open_browser=False,
        authorization_prompt_message=msg,
        access_type="offline",
        prompt="consent",
    )
    TOKEN_JSON.write_text(creds.to_json())
    TOKEN_JSON.chmod(0o600)
    print(f"\nSaved refresh token to {TOKEN_JSON}", flush=True)


if __name__ == "__main__":
    main()

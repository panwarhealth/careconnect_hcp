"""Verify the Mailchimp API key — pulls account info + last 5 campaigns.

    python reports/mailchimp/smoke_test.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import mailchimp


def main() -> None:
    client = mailchimp.client()

    info = client.root.get_root()
    print(f"Account: {info['account_name']} ({info['email']})")
    print(f"Plan: {info['pricing_plan_type']} — total subscribers: {info['total_subscribers']:,}")
    print(f"Industry: {info.get('account_industry', 'n/a')}")

    print("\n--- recent campaigns ---")
    res = client.campaigns.list(count=5, sort_field="send_time", sort_dir="DESC", status="sent")
    for c in res["campaigns"]:
        send = (c.get("send_time") or "")[:10]
        rep = c.get("report_summary") or {}
        opens = rep.get("open_rate", 0)
        clicks = rep.get("click_rate", 0)
        emails = c.get("emails_sent", 0)
        subj = (c.get("settings") or {}).get("subject_line", "(no subject)")
        print(f"{send}  sent={emails:>6,}  open={opens:.1%}  click={clicks:.1%}  | {subj[:60]}")


if __name__ == "__main__":
    main()

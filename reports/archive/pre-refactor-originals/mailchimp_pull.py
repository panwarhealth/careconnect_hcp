"""Pull all Mailchimp campaigns sent Apr 2025 -> Apr 2026."""
import csv
import os
from datetime import datetime
from pathlib import Path

import mailchimp_marketing as mc
from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent.parent
load_dotenv(ROOT / ".env")
OUT = ROOT / "reports" / "out" / "mailchimp"
SINCE = "2025-04-01T00:00:00+00:00"
UNTIL = "2026-04-30T23:59:59+00:00"


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    client = mc.Client()
    client.set_config({"api_key": os.environ["MAILCHIMP_API_KEY"], "server": os.environ["MAILCHIMP_DC"]})

    rows: list[dict] = []
    offset = 0
    page = 100
    while True:
        res = client.campaigns.list(
            count=page,
            offset=offset,
            sort_field="send_time",
            sort_dir="DESC",
            status="sent",
            since_send_time=SINCE,
            before_send_time=UNTIL,
        )
        if not res["campaigns"]:
            break
        for c in res["campaigns"]:
            rep = c.get("report_summary") or {}
            tracking = c.get("tracking") or {}
            settings = c.get("settings") or {}
            rows.append({
                "id": c["id"],
                "send_time": (c.get("send_time") or "")[:19],
                "subject": settings.get("subject_line", ""),
                "preview": settings.get("preview_text", ""),
                "from_name": settings.get("from_name", ""),
                "reply_to": settings.get("reply_to", ""),
                "list_id": (c.get("recipients") or {}).get("list_id", ""),
                "list_name": (c.get("recipients") or {}).get("list_name", ""),
                "emails_sent": c.get("emails_sent", 0),
                "open_rate": rep.get("open_rate", 0),
                "click_rate": rep.get("click_rate", 0),
                "opens": rep.get("opens", 0),
                "unique_opens": rep.get("unique_opens", 0),
                "clicks": rep.get("clicks", 0),
                "subscriber_clicks": rep.get("subscriber_clicks", 0),
                "bounces": (rep.get("bounces") or {}).get("hard_bounces", 0) + (rep.get("bounces") or {}).get("soft_bounces", 0),
                "unsubscribed": rep.get("unsubscribed", 0),
                "url_tracking": tracking.get("html_clicks", False),
            })
        if len(res["campaigns"]) < page:
            break
        offset += page

    rows.sort(key=lambda r: r["send_time"])
    out_csv = OUT / "campaigns_apr2025_apr2026.csv"
    if rows:
        with out_csv.open("w", newline="") as f:
            w = csv.DictWriter(f, fieldnames=rows[0].keys())
            w.writeheader()
            w.writerows(rows)
    print(f"wrote {out_csv.relative_to(ROOT)}  ({len(rows)} campaigns)")

    # Headline summary
    if rows:
        total_sent = sum(r["emails_sent"] for r in rows)
        total_opens = sum(r["unique_opens"] for r in rows)
        total_clicks = sum(r["subscriber_clicks"] for r in rows)
        total_unsubs = sum(r["unsubscribed"] for r in rows)
        print(f"  campaigns: {len(rows)}")
        print(f"  emails sent: {total_sent:,}")
        print(f"  unique opens: {total_opens:,} ({total_opens/total_sent:.1%})")
        print(f"  unique clicks: {total_clicks:,} ({total_clicks/total_sent:.1%})")
        print(f"  unsubscribes: {total_unsubs}")


if __name__ == "__main__":
    main()

"""Pull Mailchimp campaigns sent within a period.

    python reports/mailchimp/campaigns.py --period FY26
    python reports/mailchimp/campaigns.py --start 2025-04-01 --end 2026-04-30

Writes reports/out/mailchimp/campaigns_<slug>.csv and prints a headline summary.
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io, mailchimp, periods


def main() -> None:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    periods.add_arguments(ap, default="FY26")
    args = ap.parse_args()
    p = periods.from_args(args)
    since = f"{p.start}T00:00:00+00:00"
    until = f"{p.end}T23:59:59+00:00"
    out = io.out_dir("mailchimp")
    client = mailchimp.client()
    print(f"Mailchimp campaigns — {p.label}  ({p.start} .. {p.end})")

    rows: list[dict] = []
    offset, page = 0, 100
    while True:
        res = client.campaigns.list(count=page, offset=offset, sort_field="send_time",
                                     sort_dir="DESC", status="sent",
                                     since_send_time=since, before_send_time=until)
        if not res["campaigns"]:
            break
        for c in res["campaigns"]:
            rep = c.get("report_summary") or {}
            tracking = c.get("tracking") or {}
            settings = c.get("settings") or {}
            bounces = rep.get("bounces") or {}
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
                "bounces": bounces.get("hard_bounces", 0) + bounces.get("soft_bounces", 0),
                "unsubscribed": rep.get("unsubscribed", 0),
                "url_tracking": tracking.get("html_clicks", False),
            })
        if len(res["campaigns"]) < page:
            break
        offset += page

    rows.sort(key=lambda r: r["send_time"])
    io.write_dicts(out / f"campaigns_{p.slug}.csv", rows)

    if rows:
        total_sent = sum(r["emails_sent"] for r in rows)
        total_opens = sum(r["unique_opens"] for r in rows)
        total_clicks = sum(r["subscriber_clicks"] for r in rows)
        total_unsubs = sum(r["unsubscribed"] for r in rows)
        print(f"  campaigns:     {len(rows)}")
        print(f"  emails sent:   {total_sent:,}")
        if total_sent:
            print(f"  unique opens:  {total_opens:,} ({total_opens/total_sent:.1%})")
            print(f"  unique clicks: {total_clicks:,} ({total_clicks/total_sent:.1%})")
        print(f"  unsubscribes:  {total_unsubs}")


if __name__ == "__main__":
    main()

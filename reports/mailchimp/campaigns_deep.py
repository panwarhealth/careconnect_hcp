"""Deep Mailchimp pull — every drop. Filters to status=sent (= "completed").

    python reports/mailchimp/campaigns_deep.py
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io, mailchimp

OUT = io.out_dir("mailchimp")

# TODO: parameterize via lib.periods — the in-window filter below uses
# 2025-04-01..2026-04-30 (FY26 + a spillover April), which is not a clean
# lib.periods window; leaving the literals to preserve the numbers.


def main():
    client = mailchimp.client()

    # 1. All sent (completed) campaigns ever
    all_rows = []
    offset = 0
    while True:
        res = client.campaigns.list(count=100, offset=offset, sort_field="send_time", sort_dir="DESC", status="sent")
        if not res["campaigns"]:
            break
        for c in res["campaigns"]:
            rep = c.get("report_summary") or {}
            settings = c.get("settings") or {}
            recip = c.get("recipients") or {}
            all_rows.append({
                "id": c["id"],
                "send_time": (c.get("send_time") or "")[:19],
                "type": c.get("type"),
                "subject": settings.get("subject_line", ""),
                "preview": settings.get("preview_text", ""),
                "from_name": settings.get("from_name", ""),
                "list_id": recip.get("list_id", ""),
                "list_name": recip.get("list_name", ""),
                "segment_text": (recip.get("segment_opts") or {}).get("saved_segment_id", ""),
                "emails_sent": c.get("emails_sent", 0),
                "open_rate": rep.get("open_rate", 0),
                "click_rate": rep.get("click_rate", 0),
                "opens": rep.get("opens", 0),
                "unique_opens": rep.get("unique_opens", 0),
                "clicks": rep.get("clicks", 0),
                "subscriber_clicks": rep.get("subscriber_clicks", 0),
                "bounces_hard": (rep.get("bounces") or {}).get("hard_bounces", 0),
                "bounces_soft": (rep.get("bounces") or {}).get("soft_bounces", 0),
                "unsubscribed": rep.get("unsubscribed", 0),
            })
        if len(res["campaigns"]) < 100:
            break
        offset += 100

    if all_rows:
        io.write_dicts(OUT / "campaigns_all_sent.csv", all_rows)

    # In-window subset
    in_window = [r for r in all_rows if r["send_time"] and "2025-04-01" <= r["send_time"] <= "2026-04-30"]
    print(f"  {len(in_window)} campaigns in window Apr 2025 - Apr 2026")

    # 2. Per-campaign click details for the in-window campaigns
    click_rows = []
    for r in in_window:
        try:
            details = client.reports.get_campaign_click_details(r["id"], count=200)
            for u in details.get("urls_clicked", []):
                click_rows.append({
                    "campaign_id": r["id"],
                    "send_time": r["send_time"],
                    "subject": r["subject"],
                    "url": u.get("url", ""),
                    "total_clicks": u.get("total_clicks", 0),
                    "click_percentage": u.get("click_percentage", 0),
                    "unique_clicks": u.get("unique_clicks", 0),
                    "unique_click_percentage": u.get("unique_click_percentage", 0),
                })
        except Exception as e:
            print(f"  click details for {r['id']} failed: {e}")

    if click_rows:
        io.write_dicts(OUT / "campaign_click_details.csv", click_rows)

    # 3. Lists
    lists_rows = []
    res = client.lists.get_all_lists(count=50)
    for l in res.get("lists", []):
        stats = l.get("stats") or {}
        lists_rows.append({
            "id": l["id"],
            "name": l.get("name"),
            "date_created": (l.get("date_created") or "")[:10],
            "list_rating": l.get("list_rating"),
            "member_count": stats.get("member_count", 0),
            "unsubscribe_count": stats.get("unsubscribe_count", 0),
            "cleaned_count": stats.get("cleaned_count", 0),
            "open_rate": stats.get("open_rate", 0),
            "click_rate": stats.get("click_rate", 0),
            "campaign_count": stats.get("campaign_count", 0),
            "last_send_date": (stats.get("last_send_date") or "")[:10],
            "avg_sub_rate": stats.get("avg_sub_rate", 0),
            "avg_unsub_rate": stats.get("avg_unsub_rate", 0),
        })
    if lists_rows:
        io.write_dicts(OUT / "lists.csv", lists_rows)

    # 4. List growth history (per-list, last 24 months)
    growth_rows = []
    for l in res.get("lists", []):
        try:
            history = client.lists.get_list_growth_history(l["id"], count=36, sort_field="month", sort_dir="DESC")
            for h in history.get("history", []):
                growth_rows.append({
                    "list_id": l["id"],
                    "list_name": l.get("name"),
                    "month": h.get("month"),
                    "existing": h.get("existing", 0),
                    "imports": h.get("imports", 0),
                    "optins": h.get("optins", 0),
                    "subscribed": h.get("subscribed", 0),
                    "unsubscribed": h.get("unsubscribed", 0),
                    "cleaned": h.get("cleaned", 0),
                    "transactional": h.get("transactional", 0),
                    "deleted": h.get("deleted", 0),
                })
        except Exception as e:
            print(f"  growth for {l['id']} failed: {e}")

    if growth_rows:
        io.write_dicts(OUT / "list_growth.csv", growth_rows)


if __name__ == "__main__":
    main()

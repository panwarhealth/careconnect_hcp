"""
Build the CAPH0122 topline docx: Medicine Today alongside The Medical
Republic, in the same layout as the CAPH0126 topline.

Reads the CSVs from caph0122_by_send.py and caph0122_signups.py, plus the two
publisher reports from Downloads.

    reports/.venv/bin/python reports/ga4/build_caph0122_combined_docx.py
"""
import csv
import re
import sys
from datetime import date
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from lib import io

from build_caph0126_daily_docx import (
    DARK_NAVY,
    MID_GREY,
    PURPLE,
    add_data_table,
    add_heading,
    add_para,
    bullets,
    clear_body,
)

import openpyxl
from docx import Document
from docx.shared import Pt

DOWNLOADS = Path("/mnt/c/Users/User/Downloads")
TEMPLATE = DOWNLOADS / "CAPH0126_MT_eDM_Topline_Results_240826.docx"
MT_XLSX = DOWNLOADS / "Fess_SoluseDM_17.09.26.xlsx"
TMR_XLSX = DOWNLOADS / "Care_Solus EDM report_Fess_260923_Sept.xlsx"
OUT_NAME = f"CAPH0122_eDM_Topline_Results_MT_vs_TMR_{date.today():%d%m%y}.docx"

MT, TMR = "Medicine Today", "The Medical Republic"

CTA_ORDER = [
    ("watchnow-clinicalbites", "Watch Now (Clinical Bites videos)"),
    ("download-a-rhinitischart", "Download Allergic Rhinitis Chart"),
    ("download-patientleaflet", "Download Patient Leaflet"),
    ("accessnow-allergyanalyser", "Access the Allergy Analyser"),
    ("downloadfactsheet-zaditen", "Download Zaditen Factsheet"),
    ("zaditen-banner", "Zaditen banner"),
    ("logo", "Logo (header/footer)"),
]

EPISODES = [
    ("Ep 1", "Ep 1: Not another sinus infection"),
    ("Ep 2", "Ep 2: Expecting and congested"),
    ("Ep 3", "Ep 3: Are steroids safe for my child?"),
]


def read_csv(sub, name):
    with (io.OUT / sub / name).open() as f:
        return list(csv.DictReader(f))


def utm_content(url):
    m = re.search(r"utm_content=([^&]+)", url or "")
    return m.group(1) if m else None


def mt_publisher():
    ws = openpyxl.load_workbook(MT_XLSX, data_only=True).active
    rows = [[v for v in r if v is not None] for r in ws.iter_rows(values_only=True)]
    kv = {r[0]: r[1] for r in rows if len(r) == 2}
    clicks = {utm_content(r[0]): int(r[2]) for r in rows if len(r) == 3 and utm_content(r[0])}
    openers = int(str(kv["Recipients Who Opened"]).split()[0].replace(",", ""))
    clickers = int(str(kv["Recipients Who Clicked"]).split()[0].replace(",", ""))
    return {
        "sent": int(kv["Total Recipients"]),
        "delivered": int(kv["Successful Deliveries"]),
        "total_opens": int(kv["Total Opens"]),
        "unique_opens": openers,
        "total_clicks": int(kv["Total Clicks"]),
        "unique_clickers": clickers,
        "clicks": clicks,
    }


def tmr_publisher():
    ws = openpyxl.load_workbook(TMR_XLSX, data_only=True).active
    rows = [list(r) for r in ws.iter_rows(values_only=True)]
    hdr = next(i for i, r in enumerate(rows) if "Total sends" in r)
    h, v = rows[hdr], rows[hdr + 1]
    rec = dict(zip(h, v))
    clicks = {}
    for r in rows[hdr + 2:]:
        url = next((c for c in r if isinstance(c, str) and c.startswith("http")), None)
        if utm_content(url):
            nums = [c for c in r if isinstance(c, (int, float)) and not isinstance(c, bool)]
            clicks[utm_content(url)] = int(nums[2])  # total, % total, unique, % unique
    return {
        "delivered": int(rec["Total sends"]),
        "total_opens": int(rec["Opens"]),
        "total_clicks": int(rec["Total clicks"]),
        "clicks": clicks,
    }


def set_header(doc, code, title, when):
    """The template header is a 2-col table: code / title / date + version.
    Merged cells report the same text twice, so write each row once."""
    tbl = doc.sections[0].header.tables[0]
    for row, texts in zip(tbl.rows, ([code], [title], [when, "V1.0"])):
        seen = set()
        for cell, text in zip(row.cells, texts * 2):
            if id(cell._tc) in seen:
                continue
            seen.add(id(cell._tc))
            runs = cell.paragraphs[0].runs
            runs[0].text = text
            for r in runs[1:]:
                r.text = ""


def fmt(n):
    return f"{n:,}"


def minutes(sec, sessions):
    return f"~{sec / sessions / 60:.1f} min"


def main() -> None:
    totals = {r["send"]: r for r in read_csv("ga4", "caph0122_by_send_totals.csv")}
    ctas = {(r["send"], r["utm_content"]): int(r["sessions"])
            for r in read_csv("ga4", "caph0122_by_send_ctas.csv")}
    vids = read_csv("ga4", "caph0122_by_send_videos.csv")
    daily = read_csv("ga4", "caph0122_by_send_daily.csv")
    signups = {r["send"]: r for r in read_csv("db", "caph0122_signups.csv") if r["date"] == "TOTAL"}
    pub = {MT: mt_publisher(), TMR: tmr_publisher()}

    t = {k: {m: int(v) for m, v in totals[k].items()
             if m in ("sessions", "users", "new_users", "engaged", "pageviews",
                      "engagement_sec", "signups_ga4")}
         for k in (MT, TMR)}

    def starts(send, ep):
        return sum(int(r["starts"]) for r in vids if r["send"] == send and r["video"].startswith(ep))

    def comps(send):
        return sum(int(r["completions"]) for r in vids if r["send"] == send)

    vid_total = {k: sum(starts(k, ep) for ep, _ in EPISODES) for k in (MT, TMR)}
    day = {k: [int(r["sessions"]) for r in daily if r["send"] == k] for k in (MT, TMR)}
    su_both = int(signups["Both sends"]["signups"])

    doc = Document(TEMPLATE)
    clear_body(doc)
    set_header(doc, "CAPH0122", "FESS Allergy Solus eDM Topline Results: MT vs TMR",
               date.today().strftime("%-d %b %Y"))

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run("eDM Results: Medicine Today vs The Medical Republic")
    r.font.size = Pt(18)
    r.font.color.rgb = PURPLE
    r.bold = True

    add_para(doc, "CAPH0122  |  FESS Allergy GP Solus eDM (Allergic Rhinitis Rounds)",
             size=11, color=DARK_NAVY, bold=True, after=2)
    add_para(doc,
             "Sends: The Medical Republic, Wed 16 Sep 2026  ·  Medicine Today, Thu 17 Sep 2026  ·  "
             "Both emails measured from their send day to 23 Sep",
             size=9, after=1)
    add_para(doc, f"Prepared: {date.today().strftime('%-d %B %Y')}", size=9, after=10)

    # --- Publisher-reported -------------------------------------------------
    add_heading(doc, "What the Publishers Reported")
    add_para(doc, "Figures as supplied by each publisher.", after=4)
    mt, tmr = pub[MT], pub[TMR]
    add_data_table(
        doc,
        headers=["", MT, TMR],
        rows=[
            ["Emails sent", f"{fmt(mt['sent'])} ({fmt(mt['delivered'])} delivered)", fmt(tmr["delivered"])],
            ["Total opens", fmt(mt["total_opens"]), fmt(tmr["total_opens"])],
            ["Unique opens",
             f"{fmt(mt['unique_opens'])} ({mt['unique_opens'] / mt['delivered'] * 100:.1f}%)",
             "Not reported"],
            ["Total clicks", fmt(mt["total_clicks"]), fmt(tmr["total_clicks"])],
            ["Unique clickers",
             f"{fmt(mt['unique_clickers'])} ({mt['unique_clickers'] / mt['delivered'] * 100:.2f}%)",
             "Not reported"],
        ],
        col_widths=[2.9, 1.5, 1.7],
        font=9,
    )
    add_para(doc,
             f"The Medical Republic quotes a {tmr['total_opens'] / tmr['delivered'] * 100:.1f}% open "
             f"rate, but that is total opens (including repeat opens) divided by sends. On the "
             f"same basis Medicine Today would be "
             f"{mt['total_opens'] / mt['delivered'] * 100:.1f}%, so the two lists opened at a "
             f"similar rate. Total clicks were higher from The Medical Republic.",
             after=6, italic=True)

    # --- Site side ---------------------------------------------------------
    add_heading(doc, "Traffic & Reach on the Site")
    add_data_table(
        doc,
        headers=["", MT, TMR],
        rows=[
            ["Visits from the email", str(t[MT]["sessions"]), str(t[TMR]["sessions"])],
            ["People reached (unique users)", str(t[MT]["users"]), str(t[TMR]["users"])],
            ["First-time visitors to the site", str(t[MT]["new_users"]), str(t[TMR]["new_users"])],
            ["Engaged visits",
             f"{t[MT]['engaged']} ({round(t[MT]['engaged'] / t[MT]['sessions'] * 100)}%)",
             f"{t[TMR]['engaged']} ({round(t[TMR]['engaged'] / t[TMR]['sessions'] * 100)}%)"],
            ["Avg time actively reading",
             minutes(t[MT]["engagement_sec"], t[MT]["sessions"]),
             minutes(t[TMR]["engagement_sec"], t[TMR]["sessions"])],
            ["Video starts (Clinical Bites series)", str(vid_total[MT]), str(vid_total[TMR])],
        ],
        col_widths=[2.9, 1.5, 1.7],
        font=9,
    )
    add_para(doc,
             "Medicine Today measured 17 to 23 Sep, The Medical Republic 16 to 23 Sep. The two sends "
             "share one campaign tag and are separated by traffic source.",
             after=6, italic=True)

    # --- Links -------------------------------------------------------------
    add_heading(doc, "Which Links People Clicked")
    add_para(doc,
             "Publisher unique clicks next to the visits that actually reached the site through "
             "each button.", after=4)
    rows = []
    for key, label in CTA_ORDER:
        rows.append([label,
                     str(mt["clicks"].get(key, 0)), str(ctas.get((MT, key), 0)),
                     str(tmr["clicks"].get(key, 0)), str(ctas.get((TMR, key), 0))])
    rows.append(["TOTAL",
                 str(sum(mt["clicks"].values())), str(t[MT]["sessions"]),
                 str(sum(tmr["clicks"].values())), str(t[TMR]["sessions"])])
    add_data_table(
        doc,
        headers=["Link in the email", "MT clicks", "MT visits", "TMR clicks", "TMR visits"],
        rows=rows,
        col_widths=[2.5, 0.9, 0.9, 0.9, 0.9],
        font=9,
        bold_last=True,
    )
    add_para(doc,
             "Publisher clicks run well above site visits because a reader clicking twice counts "
             "twice, and email security software at hospitals and practices often follows links "
             "automatically to scan them. Medicine Today's own count of people who clicked "
             f"({mt['unique_clickers']}) sits close to the {t[MT]['sessions']} visits the site "
             "recorded.",
             after=6, italic=True)

    # --- Video ------------------------------------------------------------
    add_heading(doc, "Video Engagement (Clinical Bites)")
    add_para(doc,
             "Three-episode Allergic Rhinitis Rounds series at /allergic-rhinitis-clinical-bites/. "
             "A start is counted when the player actually plays.", after=4)
    add_data_table(
        doc,
        headers=["Episode", "MT starts", "TMR starts"],
        rows=[[label, str(starts(MT, ep)), str(starts(TMR, ep))] for ep, label in EPISODES]
        + [["TOTAL", str(vid_total[MT]), str(vid_total[TMR])]],
        col_widths=[3.4, 1.3, 1.4],
        font=9,
        bold_last=True,
    )
    add_para(doc,
             f"{comps(MT)} of {vid_total[MT]} Medicine Today starts and {comps(TMR)} of "
             f"{vid_total[TMR]} Medical Republic starts were watched to the end.",
             after=6, italic=True)

    # --- Registrations ----------------------------------------------------
    add_heading(doc, "New HCP Registrations")
    add_para(doc,
             f"{su_both} new HCPs registered on the site between 16 and 23 Sep. The two emails went "
             f"out a day apart, and a Zaditen eDM (IQVIA) also landed on 16 Sep, so the "
             f"registration records cannot show which email each person came from. GA4 credits "
             f"{t[MT]['signups_ga4']} registration to Medicine Today, {t[TMR]['signups_ga4']} to "
             f"The Medical Republic and 3 to the Zaditen eDM, with the rest arriving via Google "
             f"search. GA4 undercounts registrations on this site, so the true FESS share sits "
             f"somewhere between 2 and {su_both}.",
             after=6)

    # --- Summary ----------------------------------------------------------
    add_heading(doc, "What It Means")
    bullets(doc, [
        f"Both emails drove similar traffic: {t[MT]['sessions']} visits from Medicine Today and "
        f"{t[TMR]['sessions']} from The Medical Republic, most of it on the send day itself "
        f"({day[MT][0]} and {day[TMR][0]} visits).",
        f"Medicine Today readers stayed longer ({minutes(t[MT]['engagement_sec'], t[MT]['sessions'])} "
        f"vs {minutes(t[TMR]['engagement_sec'], t[TMR]['sessions'])} per visit) and kept "
        f"trickling in for longer: {sum(day[MT][2:])} visits after the first two days, against "
        f"{sum(day[TMR][2:])} for The Medical Republic.",
        "Watch Now was the top link in both emails. Below that the audiences split: Medicine "
        "Today readers went for the Allergic Rhinitis Chart, while The Medical Republic readers "
        "were far more likely to open the Allergy Analyser.",
        f"Video starts came out level ({vid_total[MT]} vs {vid_total[TMR]}), mostly on Episode 1.",
        f"{su_both} HCPs registered during the send window, but with a Zaditen eDM live the same "
        f"week only a share of those can be credited to FESS.",
    ])

    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(8)
    r1 = p.add_run("Data sources: ")
    r1.font.size = Pt(8)
    r1.font.color.rgb = MID_GREY
    r1.bold = True
    r2 = p.add_run(
        "Publisher reports from Medicine Today and The Medical Republic; GA4 property 306115293 "
        "(visits, engagement, link attribution, video events); the site registration database, "
        "excluding internal accounts. Site visits per link are counted once per visit, by the "
        "link the visit arrived through."
    )
    r2.font.size = Pt(8)
    r2.font.color.rgb = MID_GREY

    out = io.OUT / OUT_NAME
    doc.save(out)
    print(f"wrote {out.relative_to(io.PROJECT_ROOT)}")
    io.copy_to_downloads(out)


if __name__ == "__main__":
    main()

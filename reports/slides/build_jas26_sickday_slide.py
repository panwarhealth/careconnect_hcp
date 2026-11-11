"""JAS26 Hydralyte Diabetes Sick Day Plan slide, in the Zaditen 2pp insert layout.

    reports/.venv/bin/python reports/slides/build_jas26_sickday_slide.py [out.pptx]

Unique users on the tracked /hydralyte-sick-days/ link (redirects to the sick-day plan PDF), by source,
with email-security scanner bursts removed the same way as every other JAS26 figure (burst minutes of the
MT and TMR sends; no location test). Downloads = file_download events on the PDF from other pages.
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.enum.dml import MSO_THEME_COLOR
from pptx.enum.text import MSO_ANCHOR
from pptx.util import Inches

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import OUT, TEAL, label, new_slide, open_template, source, stat_card, table, text  # noqa: E402

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 Hydralyte sick day plan slide - Rob.pptx")
ASSETS = OUT / "assets"

# GA4 JAS26, /hydralyte-sick-days landing arrivals: (source, unique users, arrivals, arrivals in burst minutes)
SOURCES = [
    ("Medicine Today Hydralyte eDM (20 Aug)", 65, 95, 11),
    ("The Medical Republic Hydralyte eDM (25 Aug)", 45, 64, 4),
    ("QR code on the CWH pharmacist sick-day plan", 3, 3, 0),
    ("Direct", 1, 1, 0),
]
PAGE_USERS, PAGE_VIEWS = 120, 207
RESOURCES_DL_USERS, RESOURCES_DL = 11, 15
OTHER_DL_USERS = 12  # from the Clinical Bites episode and landing pages


def slide_sickday(prs, layout):
    s = new_slide(prs, layout)
    s.shapes.add_picture(str(ASSETS / "hydralyte_logo.jpeg"), Inches(0.45), Inches(0.42), Inches(2.3), Inches(0.5))
    rows_real = [round(u * (a - b) / a) for _, u, a, b in SOURCES]
    real = sum(rows_real)
    edm = sum(rows_real[:2])
    scanners = PAGE_USERS - real
    box = text(s, 0.42, 0.36, 12.6, 0.95,
               f"DIABETES SICK DAY PLAN – the 2pp patient plan had {real} unique users online in JAS, {edm} of them from the two Hydralyte eDMs",
               size=24, color=MSO_THEME_COLOR.ACCENT_4, bold=True, anchor=MSO_ANCHOR.MIDDLE)
    box.text_frame.paragraphs[0]._p.get_or_add_pPr().set("indent", str(int(Inches(2.5))))

    back = s.shapes.add_picture(str(ASSETS / "sickday_plan_p2.png"), Inches(2.1), Inches(1.8), Inches(3.1), Inches(4.38))
    back.rotation = 5.71
    s.shapes.add_picture(str(ASSETS / "sickday_plan_p1.png"), Inches(0.45), Inches(2.05), Inches(3.1), Inches(4.38))

    stat_card(s, 6.15, 1.8, 3.3, 1.75, "Via the tracked plan link", f"{real}",
              f"unique users: {edm} from the two Hydralyte eDMs, {real - edm} by QR code or direct ({PAGE_USERS} recorded, ~{scanners} scanner clicks removed)", value_size=26)
    stat_card(s, 9.6, 1.8, 3.3, 1.75, "Via the website", f"{RESOURCES_DL_USERS + OTHER_DL_USERS}",
              f"unique users who downloaded it from the Resources page ({RESOURCES_DL_USERS}) or the Clinical Bites pages ({OTHER_DL_USERS}); not in the eDM figure", value_color=TEAL)

    label(s, 6.15, 3.85, 6.75, "THE TRACKED LINK: WHERE THOSE USERS CAME FROM")
    trs = [["Came via", "Unique users"]] + [[name, f"{n}"] for (name, *_), n in zip(SOURCES, rows_real)] + [["Total", f"{real}"]]
    table(s, 6.15, 4.15, 6.75, [5.25, 1.5], trs, row_h=0.33, size=10.5, header_size=10.5, bold_cols=(1,))
    text(s, 6.15, 6.25, 6.75, 0.55, "The QR on the printed plan isn't tagged to this page as far as GA4 shows, so print copies handed out by reps can't be measured online; "
                                   "the 3 QR users came via tags on the CWH pharmacist materials.", size=10, italic=True, color=TEAL)
    source(s, "Source: GA4, JAS26 (1 Jul – 30 Sep 2026), sessions on the tracked /hydralyte-sick-days/ link before it redirects to the PDF. Excludes arrivals in "
              "email-security scanner bursts (10+ per minute from one send); location is never a test. Downloads are file_download events on the PDF.")


def main() -> None:
    prs, layout = open_template()
    slide_sickday(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest}")


if __name__ == "__main__":
    main()

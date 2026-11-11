"""JAS26 asset-analytics review slides: one slide per print/insert asset the
master deck asks Rob to report on (FESS detailing leaflet, Zaditen 2pp insert,
Hydralyte GP leave-behind). Same master/style as build_jas26_slides.py.

    reports/.venv/bin/python reports/slides/build_jas26_asset_slides.py [out.pptx]
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.util import Inches

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (GREY, INK, LINE, NAVY, TEAL, OUT, bullets, label, new_slide, open_template,  # noqa: E402
                                rect, source, stat_card, table, text, title)

ASSETS = OUT / "assets"
DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 asset analytics - Rob (review).pptx")


def asset_image(slide, name, x, y, max_w, max_h):
    from PIL import Image
    w, h = Image.open(ASSETS / name).size
    scale = min(max_w / w, max_h / h)
    pw, ph = w * scale, h * scale
    pic = slide.shapes.add_picture(str(ASSETS / name), Inches(x + (max_w - pw) / 2), Inches(y), Inches(pw), Inches(ph))
    pic.line.color.rgb = LINE
    return pic


def slide_fess_leaflet(prs, layout):
    s = new_slide(prs, layout)
    title(s, "FESS DETAILING LEAFLET (REPS) – the QR is just starting to register: 4 scans in JAS, all to the Allergic Rhinitis Clinical Bites")
    asset_image(s, "fess_detailing_leaflet.png", 0.42, 1.5, 4.4, 5.2)
    stat_card(s, 5.3, 1.55, 3.7, 1.6, "QR scans, JAS", "4", "4 people, all in Sep; every scan landed on the Allergic Rhinitis Clinical Bites page")
    stat_card(s, 9.2, 1.55, 3.7, 1.6, "QR visits, 1–11 Oct", "6", "from 2 people (audit, order samples and log-in pages), possibly reps or team", value_color=TEAL)
    stat_card(s, 5.3, 3.3, 3.7, 1.6, "Leaflet PDF downloads", "3", "from the Resources page, JAS")
    rect(s, 9.2, 3.3, 3.7, 1.6)
    text(s, 9.4, 3.42, 3.35, 0.3, "NOT COUNTED HERE", size=11, bold=True, color=GREY, spacing=150)
    text(s, 9.4, 3.75, 3.35, 1.1, "The leaflet's CPD-article QR goes to AJP, not Care Connect.", size=10.5, color=INK)
    rect(s, 5.3, 5.05, 7.6, 1.6)
    text(s, 5.5, 5.15, 7.2, 0.3, "WHAT THIS TELLS US", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 5.5, 5.5, 7.2, 1.1, [
        "The QR works and sends GPs to the right place; volumes will depend on how many leaflets reps hand out",
        "Worth checking the rep distribution dates so scans can be read against them",
    ], size=10.5, gap=3)
    source(s, "Source: GA4, sessions tagged utm_campaign=CAPH0128 (source education-detailing-material, medium print); JAS = 1 Jul – 30 Sep 2026. "
              "PDF downloads = file_download of caph0128-fess-allergy-pharmacist-factsheet.pdf.")


def slide_zaditen_insert(prs, layout):
    s = new_slide(prs, layout)
    title(s, "ZADITEN 2PP INSERT – no QR of its own, but the Allergic Conjunctivitis Treatments Chart it features reached 1,634 HCPs online in JAS")
    asset_image(s, "zaditen_insert.png", 0.42, 1.6, 5.3, 4.2)
    stat_card(s, 6.1, 1.55, 3.3, 1.6, "HCPs who opened the chart", "1,634", "3,135 opens via tracked links, Jul–Sep")
    stat_card(s, 9.6, 1.55, 3.3, 1.6, "Downloads from Resources", "6", "12 downloads via the resource card", value_color=TEAL)
    label(s, 6.1, 3.3, 6.8, "WHERE CHART OPENS CAME FROM")
    trs = [["Came via", "Opens", "People"],
           ["IQVIA Zaditen eDM (GP + ophthal)", "2,057", "1,075"],
           ["Insight (optometry eDM + insert)", "708", "398"],
           ["mivision (optometry)", "171", "78"],
           ["AJP / AJP Daily", "120", "69"],
           ["Australian Pharmacist", "40", "21"],
           ["MT + TMR FESS eDMs (button)", "31", "14"]]
    table(s, 6.1, 3.6, 6.8, [4.0, 1.4, 1.4], trs, row_h=0.3, size=10, header_size=10, bold_cols=(1,))
    rect(s, 0.42, 5.95, 5.3, 0.75)
    text(s, 0.6, 6.0, 5.0, 0.65, "The printed insert can't be measured on its own: it has no tagged QR. Recommendation: add one to future inserts.",
         size=10, color=INK, italic=True)
    source(s, "Source: GA4, JAS26 (1 Jul – 30 Sep 2026). Chart opens = page views of the tracked /allergic-conjunctivitis-chart/ link before it redirects to the PDF; "
              "Resources card clicks link straight to the PDF and are counted as downloads.")


def slide_leave_behind(prs, layout):
    s = new_slide(prs, layout)
    title(s, "HYDRALYTE GP LEAVE BEHIND – 4 QR scans in JAS, all to the Clinical Bites videos; none yet in October")
    asset_image(s, "hydralyte_leave_behind.png", 0.42, 1.5, 4.4, 5.2)
    stat_card(s, 5.3, 1.55, 3.7, 1.6, "QR scans, JAS", "4", "4 people: 6, 7 and 10 Aug, and 8 Sep")
    stat_card(s, 9.2, 1.55, 3.7, 1.6, "QR scans, 1–11 Oct", "0", "no scans so far this quarter", value_color=TEAL)
    label(s, 5.3, 3.35, 7.6, "WHERE SCANS LANDED")
    trs = [["Page", "Visits"],
           ["Clinical Bites: Diabetes Sick Day Management (/clinical-bites/)", "3"],
           ["/diabetes-clinical-bites/ (redirects to the same page)", "1"]]
    table(s, 5.3, 3.65, 7.6, [6.3, 1.3], trs, row_h=0.36, size=10.5, header_size=10.5, bold_cols=(1,))
    rect(s, 5.3, 5.0, 7.6, 1.65)
    text(s, 5.5, 5.1, 7.2, 0.3, "WHAT THIS TELLS US", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 5.5, 5.45, 7.2, 1.2, [
        "Very low volume so far; the early-August scans may be the team checking the QR before rep distribution",
        "The QR sends GPs to the video series rather than registration, so success here is video views, not sign-ups",
    ], size=10.5, gap=3)
    source(s, "Source: GA4, sessions tagged utm_campaign=caph0124 (source gp-leave-behind, medium qr), 1 Jul – 11 Oct 2026.")


def main() -> None:
    prs, layout = open_template()
    for build in (slide_fess_leaflet, slide_zaditen_insert, slide_leave_behind):
        build(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

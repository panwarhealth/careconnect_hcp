"""JAS26 Zaditen 2pp insert slide (Rob's layout) with scanner-adjusted reach.

    reports/.venv/bin/python reports/slides/build_jas26_zaditen_slide.py [out.pptx]

The IQVIA send's visits to /allergic-conjunctivitis-chart/ include automated
email-security link checks: 646 of 1,076 'people' arrived in bursts of 10+ in
one minute (2-hourly on 8 Sep) and 340 geolocate to data-centre cities. The
genuine range removes data-centre traffic (upper) or all burst traffic (lower).
Page views on the redirect page fire twice per visit, so opens are not shown.
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.enum.dml import MSO_THEME_COLOR
from pptx.enum.text import MSO_ANCHOR
from pptx.util import Inches

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (OUT, RED, TEAL, label, new_slide, open_template, source, stat_card, table,  # noqa: E402
                                text)

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 Zaditen insert slide - Rob (review).pptx")
ASSETS = OUT / "assets"


def slide_zaditen(prs, layout):
    s = new_slide(prs, layout)
    s.shapes.add_picture(str(ASSETS / "zaditen_logo.jpeg"), Inches(0.45), Inches(0.47), Inches(1.69), Inches(0.4))
    box = text(s, 0.42, 0.36, 12.6, 0.95,
               "2PP INSERT – no QR of its own, but the AC Treatments Chart it features reached ~1,000–1,200 HCPs online in JAS",
               size=24, color=MSO_THEME_COLOR.ACCENT_4, bold=True, anchor=MSO_ANCHOR.MIDDLE)
    box.text_frame.paragraphs[0]._p.get_or_add_pPr().set("indent", str(int(Inches(1.85))))

    back = s.shapes.add_picture(str(ASSETS / "ac_chart_page.png"), Inches(0.61), Inches(1.79), Inches(5.0), Inches(3.62))
    back.rotation = 5.71
    s.shapes.add_picture(str(ASSETS / "zaditen_insert_front.png"), Inches(0.45), Inches(2.35), Inches(4.75), Inches(3.57))

    stat_card(s, 6.15, 1.8, 3.3, 1.75, "HCPs who opened the chart", "~1,000–1,200",
              "1,634 recorded, incl. email-security scanner clicks on the IQVIA send", value_size=26)
    stat_card(s, 9.6, 1.8, 3.3, 1.75, "Downloads from Resources Page", "6", "unique users (a couple likely internal launch checks)",
              value_color=TEAL)

    label(s, 6.15, 3.85, 6.75, "WHERE CHART VISITS CAME FROM")
    trs = [["Came via", "People"],
           ["IQVIA Zaditen eDM (GP + ophthal)", "430–740*"],
           ["Insight (optometry eDM + insert)", "398"],
           ["mivision (optometry)", "78"],
           ["AJP / AJP Daily", "69"],
           ["Australian Pharmacist", "21"],
           ["MT + TMR FESS eDMs (button)", "14"]]
    table(s, 6.15, 4.15, 6.75, [5.25, 1.5], trs, row_h=0.33, size=10.5, header_size=10.5, bold_cols=(1,),
          color_cells={(1, 1): RED})
    text(s, 6.15, 6.5, 6.75, 0.35, "*The IQVIA send recorded 1,076 visitors, but many arrived in automated bursts (security software checking "
                                   "links on delivery). The range excludes that traffic.", size=9, italic=True, color=RED)
    source(s, "Source: GA4, JAS26 (1 Jul – 30 Sep 2026), visits to the tracked /allergic-conjunctivitis-chart/ link before it redirects to the PDF. Lower bound excludes all "
              "visits arriving 10+ per minute; upper bound excludes data-centre locations. Resources card downloads are file_download events on the PDF.")


def main() -> None:
    prs, layout = open_template()
    slide_zaditen(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest}")


if __name__ == "__main__":
    main()

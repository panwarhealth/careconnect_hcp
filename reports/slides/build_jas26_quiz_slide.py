"""JAS26 Guess the Glucose quiz slide (Rob's layout: Hydralyte logo in the
title, quiz banner) with a desktop vs mobile breakdown.

    reports/.venv/bin/python reports/slides/build_jas26_quiz_slide.py [out.pptx]

GA4, quiz page /blog/guess-the-glucose-challenge/, 17 Aug - 30 Sep 2026,
prod hostname only (pulled 12 Oct 2026).
"""
from __future__ import annotations

import sys
from pathlib import Path

from lxml import etree
from pptx.chart.data import CategoryChartData
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION
from pptx.enum.dml import MSO_THEME_COLOR
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Inches, Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (BLUE, TEAL, axis_quiet, label, new_slide, no_title, open_template,  # noqa: E402
                                source, style_chart, table, text, OUT)

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 glucose quiz slide - Rob (v2).pptx")
ASSETS = OUT / "assets"


def slide_quiz(prs, layout):
    s = new_slide(prs, layout)
    s.shapes.add_picture(str(ASSETS / "hydralyte_logo.jpeg"), Inches(0.42), Inches(0.47), Inches(2.09), Inches(0.49))
    box = text(s, 0.42, 0.36, 12.6, 0.95, "GUESS THE GLUCOSE QUIZ – 48 unique users, engaging on par with our articles; 1 in 4 on a phone",
               size=24, color=MSO_THEME_COLOR.ACCENT_4, bold=True, anchor=MSO_ANCHOR.MIDDLE)
    pPr = box.text_frame.paragraphs[0]._p.get_or_add_pPr()
    pPr.set("indent", str(int(Inches(2.25))))

    label(s, 0.42, 1.6, 6.3, "ENGAGEMENT — GUESS THE GLUCOSE CHALLENGE")
    cd = CategoryChartData()
    cd.categories = ["Landed on the quiz", "Stayed 15s+", "Scrolled to the bottom"]
    cd.add_series("Unique users", (48, 38, 10))
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_CLUSTERED, Inches(0.42), Inches(1.9), Inches(6.3), Inches(2.25), cd)
    ch = gf.chart
    style_chart(ch, 11)
    no_title(ch)
    ch.has_legend = False
    ch.plots[0].gap_width = 45
    ser = ch.series[0]
    ser.format.fill.solid()
    ser.format.fill.fore_color.rgb = BLUE
    ser.data_labels.show_value = True
    ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
    ser.data_labels.font.bold = True
    ch.category_axis.reverse_order = True
    axis_quiet(ch.category_axis, size=11)
    axis_quiet(ch.value_axis, gridlines=True, size=9)
    ch.value_axis.maximum_scale = 60

    label(s, 0.42, 4.35, 6.3, "DESKTOP VS MOBILE")
    trs = [["", "All", "Desktop", "Mobile"],
           ["Unique users on the quiz", "48", "36 (75%)", "12 (25%)"],
           ["Avg engaged time / user", "1m 12s", "1m 17s", "58s"],
           ["Stayed 15s+", "38 (79%)", "28 (78%)", "10 (83%)"],
           ["Scrolled to the bottom", "10", "7", "2"],
           ["Clicked through to the Clinical Audit", "6", "4", "2"]]
    table(s, 0.42, 4.65, 6.3, [2.7, 1.1, 1.25, 1.25], trs, row_h=0.33, size=10.5, header_size=10.5, bold_cols=(1,),
          color_cells={(1, 3): TEAL})

    s.shapes.add_picture(str(ASSETS / "ggq_banner.png"), Inches(7.68), Inches(1.6), Inches(4.89), Inches(2.6))

    label(s, 7.35, 4.35, 5.55, "WHERE THE TRAFFIC CAME FROM")
    trs2 = [["Source", "People", "Sessions"],
            ["Medicine Today Hydralyte eDM (20 Aug)", "29", "30"],
            ["Medical Republic Hydralyte eDM (25 Aug)", "18", "20"],
            ["Other (IQVIA Zaditen, direct, NZ Doctor)", "4", "4"]]
    table(s, 7.35, 4.65, 5.55, [3.45, 1.05, 1.05], trs2, row_h=0.4, size=10.5, header_size=10.5, bold_cols=(1,))
    text(s, 7.35, 6.35, 5.55, 0.45, "People add to 51, not 48: three people came through two sources and GA4 counts them once in the total. "
                                   "'Other' = people who came for the Zaditen chart or FESS banner and browsed on to the quiz.", size=9, italic=True, color=TEAL)
    source(s, "Source: GA4, quiz live 17 Aug 2026; figures 17 Aug – 30 Sep, prod hostname only. 'Stayed 15s+' = 15-second engaged-view event; 'Scrolled to the bottom' = "
              "90% scroll event (device rows can sum below 'All' where GA4 de-duplicates unique users).")


def main() -> None:
    prs, layout = open_template()
    slide_quiz(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest}")


if __name__ == "__main__":
    main()

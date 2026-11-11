"""JAS26 CPD pop-up slide, scanner sessions removed, five outcome cards and the 1 Jul eDM week dropped from the before period.

    reports/.venv/bin/python reports/slides/build_jas26_popup_slide.py [out.pptx]
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import GREY, TEAL, label, new_slide, open_template, source, stat_card, table, text, title  # noqa: E402

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 CPD pop-up slide - Rob.pptx")


def slide_popup(prs, layout):
    s = new_slide(prs, layout)
    title(s, "CPD POP-UP – live since 6 Aug, shown to 611 unique users in JAS; 48 clicked through, mostly eDM readers on the new video pages")
    w, gap, y, h = 2.4, 0.13, 1.55, 1.75
    xs = [0.42 + i * (w + gap) for i in range(5)]
    stat_card(s, xs[0], y, w, h, "Shown to", "611", "unique users (881 times); 26% of the 2,313 unique users on the site 6 Aug – 30 Sep", value_size=26)
    stat_card(s, xs[1], y, w, h, "Clicked 'start the activity'", "48", "8% of unique users shown; 43 arrived from an eDM", value_color=TEAL, value_size=26)
    stat_card(s, xs[2], y, w, h, "Closed it", "412", "67% of unique users shown", value_size=26)
    stat_card(s, xs[3], y, w, h, "Left the page", "151", "25% of unique users shown; left with the pop-up open (closed tab, back button)", value_size=26)
    stat_card(s, xs[4], y, w, h, "Enrolments since 6 Aug", "11", "no Audit eDM running, so the pop-up is the likely driver", value_color=TEAL, value_size=26)

    label(s, 0.42, 3.55, 6, "WHERE THE 48 CLICKED IT")
    trs = [["Page", "Clicked"], ["FESS Clinical Bites landing page", "13"], ["Hydralyte Clinical Bites landing page", "11"],
           ["Guess the Glucose quiz", "6"], ["Zaditen 'spot the allergy eye' article", "4"], ["Homepage", "4"], ["Other pages", "16"]]
    table(s, 0.42, 3.9, 6.2, [4.8, 1.4], trs, row_h=0.36, size=11, header_size=11, bold_cols=(1,))

    label(s, 7.0, 3.55, 6, "BEFORE VS AFTER THE POP-UP")
    trs2 = [["", "6 Jul – 5 Aug\n(no pop-up)*", "6 Aug – 30 Sep\n(pop-up live)"],
            ["Days", "31", "56"],
            ["Enrolments into the module", "10", "11"],
            ["Module started (pre-survey)", "11", "10"],
            ["Audits submitted", "6", "7"]]
    table(s, 7.0, 3.9, 5.9, [2.9, 1.5, 1.5], trs2, row_h=0.36, header_h=0.55, size=11, header_size=10.5)
    text(s, 7.0, 6.1, 5.9, 0.7, "*1 – 5 Jul left out: the TMR Clinical Audit eDM went out on 1 Jul and 12 enrolments landed in those five days. "
                                "The pop-up is the only Audit promotion live from 6 Aug, but GA4 can't tie a pop-up click to a specific enrolment (no user ID), so the 11 is an upper bound.",
         size=9.5, italic=True, color=GREY)
    source(s, "Source: GA4 popup_shown / popup_dismissed / popup_cta_click events (popup caph0150-mca-activity), scanner sessions removed as on every slide; WordPress DB enrolments "
              "(course 95553 access) and form entries, real HCPs only, 6 Aug – 30 Sep. Page rows count unique users per page, so sum above 48. The pop-up appears 3 seconds after a page "
              "loads, stays away for 24 hours once shown or closed, and never shows to logged-in members with MCA activity.")


def main() -> None:
    prs, layout = open_template()
    slide_popup(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest}")


if __name__ == "__main__":
    main()

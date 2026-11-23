"""Simplified layout options for the "what HCPs valued most" slide (one slide per option).

    python reports/slides/fy28/valued_options.py <out.pptx> [option_a ...]
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    GREY, INK, LINE, NAVY, PINK, PURPLE, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import QBR_SOURCE, SOFT_BLUE  # noqa: E402
from rec1_options import MID  # noqa: E402

TITLE = "WHAT HCPs VALUED MOST: practical clinical tools and CPD beat every other format"
SOURCE = QBR_SOURCE + " Outside-the-portal figures are the partners' own reporting (AJP, Princeton)."
PARTNERS = ("AJP allergy CPD had 3,472 completions in one month; the Princeton desk sets had 16,257 GP and "
            "4,080 gastro engagements.")


def option_a(prs, layout):
    """The two winners, side by side."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    panels = [
        (0.42, TEAL, "CLINICAL TOOLS", "~1,000", "unique users for the Zaditen AC Chart in its first month",
         "Top resource of FY27. The Diabetes Sick Day Plan reached ~122 unique users."),
        (6.82, PURPLE, "CPD", "184", "HCPs enrolled in the Clinical Audit since 19 March",
         "20 completed with certificates issued. Audit eDMs convert at 8.4%."),
    ]
    for x, colr, head, big, line, small in panels:
        rect(s, x, 1.5, 6.1, 3.9)
        text(s, x + 0.45, 1.8, 5.2, 0.3, head, size=12, color=GREY, bold=True, spacing=150)
        text(s, x + 0.45, 2.2, 5.2, 1.2, big, size=58, color=colr, bold=True, anchor=MID)
        text(s, x + 0.45, 3.55, 5.2, 0.8, line, size=15, color=INK, bold=True)
        text(s, x + 0.45, 4.45, 5.2, 0.8, small, size=13, color=INK)
    rect(s, 0.42, 5.6, 12.5, 1.1, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.87, 5.6, 11.6, 1.1, [[("Video is an education format: ", {"bold": True, "color": NAVY}),
                                   ("45% of arrivals to Clinical Bites pressed play (130 of 289).", {})]], size=15, anchor=MID)
    source(s, SOURCE)


def option_b(prs, layout):
    """Three columns: tools, CPD, what it means."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    cols = [
        ("CLINICAL TOOLS", TEAL, "Used with patients",
         ["Zaditen AC Chart: ~1,000 unique users in its first month", "Diabetes Sick Day Plan: ~122 unique users",
          "Princeton desk sets: 16,257 GP and 4,080 gastro engagements"]),
        ("CPD", PURPLE, "The sign-up driver",
         ["Clinical Audit: 184 enrolled, 20 completed", "AJP allergy CPD: 3,472 completions in one month",
          "Audit eDMs convert at 8.4%"]),
        ("WHAT IT MEANS", NAVY, "More tools, more CPD",
         ["Keep making charts, plans and leaflets", "Lead with CPD to bring in sign-ups",
          "Use video for education: 45% of arrivals press play"]),
    ]
    w, gap, x = 3.95, 0.325, 0.42
    for head, colr, big, items in cols:
        rect(s, x, 1.5, w, 5.2)
        rect(s, x, 1.5, w, 0.6, fill=colr, line=None, radius=0.08)
        text(s, x + 0.3, 1.5, w - 0.6, 0.6, head, size=12, color=WHITE, bold=True, spacing=150, anchor=MID)
        text(s, x + 0.3, 2.4, w - 0.6, 0.6, big, size=22, color=colr, bold=True)
        ty = 3.3
        for item in items:
            rect(s, x + 0.3, ty + 0.1, 0.12, 0.12, fill=colr, line=None, radius=0.5)
            text(s, x + 0.56, ty, w - 0.86, 0.8, item, size=13.5, color=INK)
            ty += 1.05
        x += w + gap
    source(s, SOURCE)


def option_c(prs, layout):
    """Plain ranked rows, no boxes."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    rows = [
        ("~1,000", TEAL, "Zaditen AC Chart", "unique users in its first month; top resource of FY27"),
        ("184", PURPLE, "Clinical Audit (CPD)", "enrolled since 19 March; 20 completed with certificates issued"),
        ("~122", TEAL, "Diabetes Sick Day Plan", "unique users: ~99 from eDMs and 23 browsing the site"),
        ("45%", PINK, "Clinical Bites videos", "of arrivals pressed play (130 of 289)"),
    ]
    y, h = 1.5, 1.0
    for i, (big, colr, name, what) in enumerate(rows):
        if i:
            rect(s, 0.42, y, 12.5, 0.02, fill=LINE, line=None, rounded=False)
        text(s, 0.42, y, 2.6, h, big, size=36, color=colr, bold=True, anchor=MID)
        text(s, 3.2, y, 3.6, h, name, size=16, color=INK, bold=True, anchor=MID)
        text(s, 6.9, y, 6.0, h, what, size=14, color=INK, anchor=MID)
        y += h
    rect(s, 0.42, 5.7, 12.5, 1.0, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.82, 5.7, 11.7, 1.0, [[("Outside the portal: ", {"bold": True, "color": NAVY}), (PARTNERS, {})]], size=12.5, anchor=MID)
    source(s, SOURCE)


def option_d(prs, layout):
    """Statement band and three big numbers."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    rect(s, 0.42, 1.5, 12.5, 1.5, fill=NAVY, line=None)
    text(s, 0.87, 1.5, 11.6, 1.5, "HCPs came for things they can use: charts and plans for their patients, and CPD for themselves.",
         size=20, color=WHITE, bold=True, anchor=MID)
    stats = [
        ("~1,000", "unique users for the Zaditen AC Chart in its first month", TEAL),
        ("184", "HCPs enrolled in the Clinical Audit (CPD) since 19 March", PURPLE),
        ("45%", "of arrivals to Clinical Bites videos pressed play", PINK),
    ]
    w, x = (12.5 - 0.6) / 3, 0.42
    for big, line, colr in stats:
        rect(s, x, 3.3, w, 3.4)
        text(s, x + 0.35, 3.55, w - 0.7, 1.4, big, size=60, color=colr, bold=True, anchor=MID)
        text(s, x + 0.35, 5.15, w - 0.7, 1.2, line, size=14.5, color=INK)
        x += w + 0.3
    source(s, SOURCE)


def option_e(prs, layout):
    """Fewest shapes: one headline panel and three plain rows (same shape as the compliance slide)."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    rect(s, 0.42, 1.5, 4.6, 5.2, fill=SOFT_BLUE, line=None, radius=0.05)
    text(s, 0.82, 1.9, 3.8, 0.3, "FY27 TO DATE", size=12, color=GREY, bold=True, spacing=150)
    text(s, 0.82, 2.4, 3.8, 1.7, ["Tools", "and CPD"], size=44, color=NAVY, bold=True, anchor=MID)
    text(s, 0.82, 4.4, 3.8, 2.0, "HCPs came for things they can use with patients, and for CPD.", size=16, color=INK, bold=True)
    rows = [
        ("CLINICAL TOOLS", "Zaditen AC Chart: ~1,000 unique users in its first month, the top resource of FY27. "
                           "Diabetes Sick Day Plan: ~122 unique users."),
        ("CPD", "Clinical Audit: 184 enrolled, 20 completed. Audit eDMs convert at 8.4%. "
                "AJP allergy CPD: 3,472 completions in one month."),
        ("VIDEO", "Clinical Bites: 45% of arrivals pressed play (130 of 289). An education format."),
    ]
    y, h = 1.5, 5.2 / 3
    for i, (head, body) in enumerate(rows):
        if i:
            rect(s, 5.5, y, 7.42, 0.02, fill=LINE, line=None, rounded=False)
        text(s, 5.5, y + 0.3, 7.42, 0.3, head, size=12, color=NAVY, bold=True, spacing=150)
        text(s, 5.5, y + 0.7, 7.42, 0.9, body, size=15, color=INK)
        y += h
    source(s, SOURCE)


def main() -> None:
    prs, layout = open_template()
    builds = [option_a, option_b, option_c, option_d, option_e]
    if len(sys.argv) > 2:
        builds = [b for b in builds if b.__name__ in sys.argv[2:]]
    for build in builds:
        build(prs, layout)
    prs.save(sys.argv[1])
    print(f"wrote {sys.argv[1]} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

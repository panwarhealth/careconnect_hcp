"""Layout options for the simplified Recommendation 1 slide (one slide per option).

    python reports/slides/fy28/rec1_options.py <out.pptx>
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.util import Inches

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from build_jas26_slides import (  # noqa: E402
    CARD, GREY, INK, LINE, MUTED, NAVY, PINK, PURPLE, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import GREEN, SOFT_BLUE  # noqa: E402

TITLE = "RECOMMENDATION 1: Replacing the patched CPD plugins with a scalable platform"
SOURCE = "Hours are Panwar Health estimates; cost to be finalised."
MID, CENTER = MSO_ANCHOR.MIDDLE, PP_ALIGN.CENTER


def hours_box(s, x, y, w=2.45, h=1.0):
    rect(s, x, y, w, h, fill=PINK, line=None, radius=0.08)
    text(s, x, y + 0.02, w, h * 0.6, "~80 HRS", size=24, color=WHITE, bold=True, align=CENTER, anchor=MID)
    text(s, x, y + h * 0.55, w, h * 0.4, "AMJ '27", size=12, color=WHITE, bold=True, align=CENTER, anchor=MID)


def arrow(s, x, y, w, h, fill):
    shp = s.shapes.add_shape(MSO_SHAPE.RIGHT_ARROW, Inches(x), Inches(y), Inches(w), Inches(h))
    shp.fill.solid()
    shp.fill.fore_color.rgb = fill
    shp.line.fill.background()
    return shp


def option_a(prs, layout):
    """Before and after, side by side."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rows = [
        ("New course on the site", "~30 hrs", "~5 hrs"),
        ("How it's built", "3 plugins patched together", "1 platform, built for the job"),
        ("CPD records", "On Care's site", "With Panwar, the CPD provider"),
        ("Course plugin licences", "US$350+ a year", "$0"),
    ]
    lx, lw, ax, rx, rw = 0.42, 5.45, 6.07, 7.02, 5.9
    rect(s, lx, 1.5, lw, 4.15, fill=CARD)
    rect(s, rx, 1.5, rw, 4.15, fill=NAVY, line=None)
    text(s, lx + 0.35, 1.72, lw - 0.7, 0.35, "TODAY", size=13, color=GREY, bold=True, spacing=200)
    text(s, rx + 0.35, 1.72, rw - 0.7, 0.35, "FROM FY28", size=13, color=WHITE, bold=True, spacing=200)
    y = 2.25
    for what, before, after in rows:
        text(s, lx + 0.35, y, lw - 0.7, 0.26, what, size=10.5, color=MUTED)
        text(s, lx + 0.35, y + 0.26, lw - 0.7, 0.45, before, size=18, color=GREY, bold=True)
        text(s, rx + 0.35, y, rw - 0.7, 0.26, what, size=10.5, color=RGB_SOFT)
        text(s, rx + 0.35, y + 0.26, rw - 0.7, 0.45, after, size=18, color=WHITE, bold=True)
        y += 0.82
    arrow(s, ax, 3.2, 0.78, 0.7, PINK)
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("One sign-up: ", {"bold": True, "color": NAVY}),
                                    ("registering for CPD registers the HCP for Care Connect.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85, h=0.85)
    source(s, SOURCE)


def option_b(prs, layout):
    """One hero number, three supporting points."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rect(s, 0.42, 1.5, 5.6, 4.15, fill=NAVY, line=None)
    text(s, 0.8, 1.8, 4.9, 0.35, "TIME TO PUT A NEW CPD COURSE ON THE SITE", size=11, color=RGB_SOFT, bold=True, spacing=120)
    text(s, 0.8, 2.3, 4.9, 0.7, "~30 hrs today", size=24, color=RGB_SOFT, bold=True)
    text(s, 0.8, 3.0, 4.9, 1.5, "~5 hrs", size=80, color=WHITE, bold=True, anchor=MID)
    text(s, 0.8, 4.65, 4.9, 0.7, "on a platform built for CPD, because nothing has to be rewired for each course.", size=12.5, color=WHITE)
    points = [
        ("ONE SIGN-UP", "Registering for CPD registers the HCP for Care Connect.", TEAL),
        ("DATA PROTECTED", "CPD records sit with Panwar as the CPD provider. Care gets de\u2011identified reports.", PURPLE),
        ("NO LICENCES", "No course plugin licences once LearnDash ends in Oct 2027.", GREEN),
    ]
    y = 1.5
    for head, line, colr in points:
        rect(s, 6.27, y, 6.65, 1.25)
        rect(s, 6.27, y, 0.12, 1.25, fill=colr, line=None, rounded=False)
        text(s, 6.65, y + 0.2, 6.0, 0.35, head, size=13, color=colr, bold=True, spacing=100)
        text(s, 6.65, y + 0.58, 6.0, 0.6, line, size=13, color=INK)
        y += 1.45
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("Why now: ", {"bold": True, "color": NAVY}),
                                    ("CPD brings in more sign-ups than anything else, and it runs on the most fragile part of the site.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85, h=0.85)
    source(s, SOURCE)


def option_c(prs, layout):
    """Problem, what we do, what Care gets."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    steps = [
        ("THE PROBLEM", GREY, "CPD runs on patched plugins",
         ["Set up quickly to get one course live", "Not designed to run more than one course",
          "Three plugins patched together",
          "Two have changed owners, so licence fees are likely to rise"]),
        ("WHAT WE DO", NAVY, "Build our own CPD platform",
         ["Replace the plugins with an LMS purpose-built for Care Connect",
          "Catalogue-driven: set up for many courses from day one",
          "Sensitive logic and records move to Panwar's infrastructure",
          "Add a SCORM player for Panwar\u2011built courses"]),
        ("WHAT CARE GETS", GREEN, "More CPD, more registrations",
         ["A catalogue of CPD courses on Panwar's latest LMS technology",
          "Richer, more engaging SCORM courses",
          "Compliant by design: the sponsor never holds participant data",
          "More HCP registrations: every CPD sign-up joins Care Connect"]),
    ]
    w, gap, x = 3.75, 0.62, 0.42
    for i, (head, colr, big, items) in enumerate(steps):
        rect(s, x, 1.5, w, 4.15)
        rect(s, x, 1.5, w, 0.6, fill=colr, line=None, radius=0.08)
        text(s, x + 0.3, 1.5, w - 0.6, 0.6, head, size=12, color=WHITE, bold=True, spacing=150, anchor=MID)
        text(s, x + 0.3, 2.35, w - 0.6, 0.95, big, size=20, color=colr if colr != GREY else INK, bold=True)
        ty = 3.4
        for item in items:
            rect(s, x + 0.3, ty + 0.09, 0.12, 0.12, fill=colr, line=None, radius=0.5)
            text(s, x + 0.56, ty, w - 0.86, 0.5, item, size=12.5, color=INK)
            ty += 2.16 / len(items)
        if i < 2:
            arrow(s, x + w + 0.08, 3.3, gap - 0.16, 0.55, PINK)
        x += w + gap
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("Also: ", {"bold": True, "color": NAVY}),
                                    ("no course plugin licences from Oct 2027, and Care gets de\u2011identified CPD reports on request.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85, h=0.85)
    source(s, SOURCE)


def option_d(prs, layout):
    """Statement band on top, three numbers below."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rect(s, 0.42, 1.5, 12.5, 1.55, fill=NAVY, line=None)
    text(s, 0.85, 1.5, 11.6, 1.55, [[("CPD brings in more sign-ups than anything else on Care Connect. ", {"bold": True}),
                                    ("It runs on three plugins patched together, and every new course makes that worse. "
                                     "We replace them with one platform built for the job.", {})]], size=15, color=WHITE, anchor=MID)
    stats = [
        ("~5 hrs", "to put a new course on the site, down from ~30", TEAL),
        ("1", "sign-up covers CPD and Care Connect", NAVY),
        ("$0", "course plugin licences from Oct 2027", GREEN),
    ]
    w = (12.5 - 2 * 0.3) / 3
    x = 0.42
    for big, line, colr in stats:
        rect(s, x, 3.3, w, 2.35)
        text(s, x + 0.3, 3.4, w - 0.6, 1.25, big, size=60, color=colr, bold=True, anchor=MID)
        text(s, x + 0.3, 4.75, w - 0.6, 0.8, line, size=13.5, color=INK)
        x += w + 0.3
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("Data protected: ", {"bold": True, "color": NAVY}),
                                    ("CPD records sit with Panwar as the CPD provider; Care gets de\u2011identified reports.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85, h=0.85)
    source(s, SOURCE)


def option_e(prs, layout):
    """Who looks after what: Care Connect in front, Panwar's platform behind."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    text(s, 0.42, 1.4, 12.5, 0.4, "HCPs do their CPD on Care Connect. The platform behind it is built and run by Panwar.", size=14, color=INK)
    boxes = [
        ("ON CARE CONNECT", "What HCPs see", TEAL,
         ["Course pages in the Care Connect design", "One \u201cMy CPD\u201d page for progress and certificates", "One sign-up for CPD and Care Connect"]),
        ("PANWAR'S CPD PLATFORM", "What runs behind it", NAVY,
         ["Courses, scoring and certificates", "CPD records, held by Panwar as the CPD provider", "De\u2011identified reports for Care"]),
    ]
    x, w = 0.42, 5.55
    for head, sub, colr, items in boxes:
        rect(s, x, 2.0, w, 3.65)
        rect(s, x, 2.0, w, 0.85, fill=colr, line=None, radius=0.08)
        text(s, x + 0.35, 2.08, w - 0.7, 0.4, head, size=14, color=WHITE, bold=True, spacing=100)
        text(s, x + 0.35, 2.46, w - 0.7, 0.3, sub, size=11, color=WHITE)
        ty = 3.15
        for item in items:
            rect(s, x + 0.35, ty + 0.1, 0.13, 0.13, fill=colr, line=None, radius=0.5)
            text(s, x + 0.65, ty, w - 1.0, 0.7, item, size=13.5, color=INK)
            ty += 0.8
        x += w + 1.4
    arrow(s, 6.12, 3.2, 1.1, 0.5, PINK)
    back = arrow(s, 6.12, 3.95, 1.1, 0.5, PINK)
    back.rotation = 180
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("What changes for Care: ", {"bold": True, "color": NAVY}),
                                    ("new courses in ~5 hours, not ~30, and no course plugin licences from Oct 2027.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85, h=0.85)
    source(s, SOURCE)


from pptx.dml.color import RGBColor  # noqa: E402

RGB_SOFT = RGBColor(0xB9, 0xC6, 0xDE)


def main() -> None:
    prs, layout = open_template()
    builds = [option_a, option_b, option_c, option_d, option_e]
    if len(sys.argv) > 2:  # only the named options, e.g. option_c
        builds = [b for b in builds if b.__name__ in sys.argv[2:]]
    for build in builds:
        build(prs, layout)
    prs.save(sys.argv[1])
    print(f"wrote {sys.argv[1]} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

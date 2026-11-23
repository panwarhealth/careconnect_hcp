"""Second set of layout options for the "what HCPs valued most" slide, showing
the contrast with the formats that did less (one slide per option).

    python reports/slides/fy28/valued_options2.py <out.pptx> [option_a ...]
"""
from __future__ import annotations

import math
import sys
from pathlib import Path

from pptx.enum.shapes import MSO_SHAPE
from pptx.util import Inches

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    CARD, GREY, INK, LIGHTBAR, LINE, MUTED, NAVY, PINK, PURPLE, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import GREEN, QBR_SOURCE, SOFT_BLUE, SOFT_GREEN  # noqa: E402
from rec1_options import CENTER, MID  # noqa: E402

TITLE = "WHAT HCPs VALUED MOST: practical clinical tools and CPD beat every other format"
SOURCE = QBR_SOURCE + " Glucose quiz: 17 Aug to 30 Sep 2026."


def circle(s, cx, cy, r, fill):
    shp = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(cx - r), Inches(cy - r), Inches(2 * r), Inches(2 * r))
    shp.fill.solid()
    shp.fill.fore_color.rgb = fill
    shp.line.fill.background()
    return shp


def option_a(prs, layout):
    """Split screen: what worked against what did less."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    sides = [
        (0.42, SOFT_GREEN, GREEN, "WHAT WORKED", [
            ("~1,000", "unique users for the Zaditen AC Chart in its first month"),
            ("184", "HCPs enrolled in the Clinical Audit (CPD)"),
            ("8.4%", "of clicks on the Clinical Audit eDM became sign-ups"),
        ]),
        (6.82, CARD, GREY, "WHAT DID LESS", [
            ("130", "plays of Clinical Bites videos, from 289 arrivals"),
            ("48", "unique users for the glucose quiz in six weeks"),
            ("0.5%", "of clicks on the video eDMs became sign-ups"),
        ]),
    ]
    for x, fill, colr, head, rows in sides:
        rect(s, x, 1.5, 6.1, 5.2, fill=fill, line=None, radius=0.05)
        text(s, x + 0.45, 1.8, 5.2, 0.3, head, size=13, color=colr, bold=True, spacing=150)
        y = 2.35
        for big, line in rows:
            text(s, x + 0.45, y, 2.2, 1.2, big, size=40, color=colr, bold=True, anchor=MID)
            text(s, x + 2.7, y, 3.1, 1.2, line, size=14, color=INK, anchor=MID)
            y += 1.4
    source(s, SOURCE)


def option_b(prs, layout):
    """Circles sized by how many HCPs each one reached."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    items = [
        (1000, "~1,000", "Zaditen AC Chart", "unique users, first month", TEAL),
        (289, "289", "Clinical Bites videos", "arrivals; 130 pressed play", LIGHTBAR),
        (184, "184", "Clinical Audit (CPD)", "HCPs enrolled", PURPLE),
        (122, "~122", "Diabetes Sick Day Plan", "unique users", TEAL),
        (48, "48", "Glucose quiz", "unique users, six weeks", LIGHTBAR),
    ]
    scale = 1.55 / math.sqrt(1000)
    radii = [scale * math.sqrt(v) for v, *_ in items]
    first, last, base = 0.42 + radii[0], 12.92 - 1.15, 4.75
    step = (last - first) / (len(items) - 1)
    for k, ((v, big, name, what, colr), r) in enumerate(zip(items, radii)):
        cx = first + k * step
        circle(s, cx, base - r, r, colr)
        if r > 0.6:
            text(s, cx - r, base - 2 * r, 2 * r, 2 * r, big, size=30 if r > 1 else 18, bold=True,
                 color=WHITE if colr != LIGHTBAR else INK, align=CENTER, anchor=MID)
        else:
            text(s, cx - 0.8, base - 2 * r - 0.45, 1.6, 0.4, big, size=18, color=INK, bold=True, align=CENTER)
        text(s, cx - 1.15, base + 0.2, 2.3, 0.4, name, size=13.5, color=INK, bold=True, align=CENTER)
        text(s, cx - 1.15, base + 0.6, 2.3, 0.6, what, size=12, color=GREY, align=CENTER)
    rect(s, 0.42, 6.05, 12.5, 0.65, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.82, 6.05, 11.7, 0.65, [[("Coloured: ", {"bold": True, "color": NAVY}), ("clinical tools and CPD.  ", {}),
                                    ("Grey: ", {"bold": True, "color": NAVY}), ("video and quiz formats.", {})]], size=12.5, anchor=MID)
    source(s, SOURCE)


def option_c(prs, layout):
    """One head-to-head: sign-ups from a CPD eDM against a video eDM."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    text(s, 0.42, 1.5, 12.5, 0.4, "SHARE OF eDM CLICKS THAT BECAME SIGN-UPS", size=12, color=GREY, bold=True, spacing=150)
    text(s, 0.42, 2.0, 5.6, 2.2, "8.4%", size=110, color=GREEN, bold=True, anchor=MID)
    text(s, 0.42, 4.25, 5.6, 0.5, "Clinical Audit (CPD) eDM", size=18, color=INK, bold=True)
    text(s, 6.0, 2.0, 1.3, 2.2, "vs", size=28, color=MUTED, bold=True, align=CENTER, anchor=MID)
    text(s, 7.5, 2.0, 5.4, 2.2, "0.5%", size=110, color=LIGHTBAR, bold=True, anchor=MID)
    text(s, 7.5, 4.25, 5.4, 0.5, "FESS video eDMs", size=18, color=INK, bold=True)
    rect(s, 0.42, 5.2, 12.5, 0.02, fill=LINE, line=None, rounded=False)
    cols = [
        ("CLINICAL TOOLS", "Zaditen AC Chart: ~1,000 unique users in its first month."),
        ("VIDEO", "Clinical Bites: 130 plays from 289 arrivals (45%)."),
        ("QUIZ", "Glucose quiz: 48 unique users in six weeks."),
    ]
    w, x = 12.5 / 3, 0.42
    for head, body in cols:
        text(s, x, 5.45, w - 0.3, 0.3, head, size=12, color=NAVY, bold=True, spacing=150)
        text(s, x, 5.8, w - 0.3, 0.8, body, size=13.5, color=INK)
        x += w
    source(s, SOURCE)


def option_d(prs, layout):
    """Three tiers, widest for what did most."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    tiers = [
        (12.5, NAVY, WHITE, "CLINICAL TOOLS AND CPD",
         "Zaditen AC Chart: ~1,000 unique users in its first month.  Clinical Audit: 184 enrolled, and 8.4% of its eDM clicks became sign-ups."),
        (9.6, TEAL, WHITE, "VIDEO",
         "Clinical Bites: 130 plays from 289 arrivals.  0.5% of video eDM clicks became sign-ups."),
        (6.7, LIGHTBAR, INK, "QUIZ", "Glucose quiz: 48 unique users in six weeks."),
    ]
    y, h = 1.5, 1.6
    for w, fill, colr, head, body in tiers:
        x = 0.42 + (12.5 - w) / 2
        rect(s, x, y, w, h, fill=fill, line=None, radius=0.06)
        text(s, x + 0.45, y + 0.38, w - 0.9, 0.3, head, size=13, color=colr, bold=True, spacing=150)
        text(s, x + 0.45, y + 0.8, w - 0.9, 0.7, body, size=14, color=colr)
        y += h + 0.2
    source(s, SOURCE)


def option_e(prs, layout):
    """Plain rows with an FY28 verdict on each format."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    for x, w, head in ((0.42, 3.0, "FORMAT"), (3.6, 6.3, "WHAT HAPPENED IN FY27"), (10.3, 2.62, "FOR FY28")):
        text(s, x, 1.5, w, 0.3, head, size=12, color=GREY, bold=True, spacing=150)
    rows = [
        ("Clinical tools", "Zaditen AC Chart: ~1,000 unique users in its first month, the top resource of FY27", "DO MORE", GREEN),
        ("CPD", "Clinical Audit: 184 enrolled; 8.4% of its eDM clicks became sign-ups", "DO MORE", GREEN),
        ("Video", "Clinical Bites: 130 plays from 289 arrivals; 0.5% of video eDM clicks became sign-ups", "KEEP FOR EDUCATION", TEAL),
        ("Quiz", "Glucose quiz: 48 unique users in six weeks", "RETHINK", GREY),
    ]
    y, h = 1.95, 1.15
    for name, what, verdict, colr in rows:
        rect(s, 0.42, y, 12.5, 0.02, fill=LINE, line=None, rounded=False)
        text(s, 0.42, y, 3.0, h, name, size=20, color=INK, bold=True, anchor=MID)
        text(s, 3.6, y, 6.3, h, what, size=14, color=INK, anchor=MID)
        rect(s, 10.3, y + 0.33, 2.62, 0.5, fill=colr, line=None, radius=0.5)
        text(s, 10.3, y + 0.33, 2.62, 0.5, verdict, size=11, color=WHITE, bold=True, align=CENTER, anchor=MID, spacing=80)
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

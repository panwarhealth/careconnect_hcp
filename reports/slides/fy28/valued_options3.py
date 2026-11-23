"""Third set of layout options for the "what HCPs valued most" slide: what
brought HCPs in, what made them sign up, and what did neither.

    python reports/slides/fy28/valued_options3.py <out.pptx> [option_a ...]
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    CARD, GREY, INK, LIGHTBAR, LINE, MUTED, NAVY, PURPLE, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import GREEN, QBR_SOURCE, SOFT_BLUE, SOFT_GREEN  # noqa: E402
from rec1_options import CENTER, MID  # noqa: E402
from pptx.enum.text import PP_ALIGN  # noqa: E402

TITLE = "WHAT HCPs VALUED MOST: practical clinical tools and CPD beat every other format"
SOURCE = QBR_SOURCE + " Glucose quiz: 17 Aug to 30 Sep 2026."
RIGHT = PP_ALIGN.RIGHT


def option_a(prs, layout):
    """Two questions, two answers, and what did neither."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    panels = [
        (0.42, TEAL, "WHAT BROUGHT HCPs IN?", "Clinical tools", "~1,000",
         "unique users for the Zaditen AC Chart in its first month"),
        (6.82, PURPLE, "WHAT MADE THEM SIGN UP?", "CPD", "8.4%",
         "of clicks on the Clinical Audit eDM became sign-ups"),
    ]
    for x, colr, q, answer, big, line in panels:
        rect(s, x, 1.5, 6.1, 3.7)
        text(s, x + 0.45, 1.8, 5.2, 0.3, q, size=12, color=GREY, bold=True, spacing=150)
        text(s, x + 0.45, 2.2, 5.2, 0.6, answer, size=26, color=colr, bold=True)
        text(s, x + 0.45, 2.95, 5.2, 1.2, big, size=58, color=colr, bold=True, anchor=MID)
        text(s, x + 0.45, 4.25, 5.2, 0.7, line, size=14, color=INK)
    rect(s, 0.42, 5.4, 12.5, 1.3, fill=LIGHTBAR, line=None, radius=0.06)
    text(s, 0.87, 5.55, 3.0, 0.3, "WHAT DID LESS?", size=12, color=INK, bold=True, spacing=150)
    text(s, 0.87, 5.9, 11.6, 0.6, [[("Video: ", {"bold": True}), ("0.5% of clicks on the FESS video eDMs became sign-ups.   ", {}),
                                   ("Quiz: ", {"bold": True}), ("48 unique users in six weeks.", {})]], size=14, color=INK)
    source(s, SOURCE)


def option_b(prs, layout):
    """Headline panel and three plain rows (same shape as the compliance slide)."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    rect(s, 0.42, 1.5, 4.6, 5.2, fill=SOFT_GREEN, line=None, radius=0.05)
    text(s, 0.82, 1.9, 3.8, 0.3, "FY27 TO DATE", size=12, color=GREY, bold=True, spacing=150)
    text(s, 0.82, 2.4, 3.8, 1.7, ["Tools", "and CPD"], size=44, color=GREEN, bold=True, anchor=MID)
    text(s, 0.82, 4.4, 3.8, 2.0, "Tools brought HCPs to the site. CPD brought the sign-ups.", size=16, color=INK, bold=True)
    rows = [
        ("BROUGHT HCPs IN", NAVY, "Zaditen AC Chart: ~1,000 unique users in its first month, the top resource of FY27."),
        ("MADE THEM SIGN UP", NAVY, "Clinical Audit: 184 enrolled, and 8.4% of clicks on its eDM became sign-ups."),
        ("DID LESS", GREY, "Video: 0.5% of clicks on the FESS video eDMs became sign-ups. Glucose quiz: 48 unique users in six weeks."),
    ]
    y, h = 1.5, 5.2 / 3
    for i, (head, colr, body) in enumerate(rows):
        if i:
            rect(s, 5.5, y, 7.42, 0.02, fill=LINE, line=None, rounded=False)
        text(s, 5.5, y + 0.3, 7.42, 0.3, head, size=12, color=colr, bold=True, spacing=150)
        text(s, 5.5, y + 0.7, 7.42, 0.9, body, size=15, color=INK if colr == NAVY else GREY)
        y += h
    source(s, SOURCE)


def option_c(prs, layout):
    """Type only: three sentences, no boxes."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    lines = [
        (TEAL, "~1,000", INK, "unique users opened the Zaditen AC Chart in its first month."),
        (PURPLE, "8.4%", INK, "of clicks on the Clinical Audit eDM became sign-ups."),
        (MUTED, "0.5%", GREY, "of clicks on the FESS video eDMs became sign-ups."),
        (MUTED, "48", GREY, "unique users tried the glucose quiz in six weeks."),
    ]
    y = 1.55
    for i, (ncol, big, tcol, rest) in enumerate(lines):
        if i == 2:
            rect(s, 0.42, y + 0.02, 12.5, 0.02, fill=LINE, line=None, rounded=False)
            y += 0.2
        text(s, 0.42, y, 3.3, 1.1, big, size=48 if i < 2 else 34, color=ncol, bold=True, anchor=MID)
        text(s, 3.9, y, 9.0, 1.1, rest, size=20 if i < 2 else 16, color=tcol, bold=i < 2, anchor=MID)
        y += 1.3 if i < 2 else 1.0
    source(s, SOURCE)


def option_d(prs, layout):
    """Tug of war: tools and CPD pull left, video and quiz pull right."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    mid, full = 6.67, 5.3
    text(s, mid - full, 1.5, full, 0.35, "TOOLS AND CPD", size=13, color=GREEN, bold=True, spacing=150)
    text(s, mid + 0.15, 1.5, full, 0.35, "VIDEO AND QUIZ", size=13, color=GREY, bold=True, spacing=150)
    rows = [
        ("UNIQUE USERS", "~1,000", "Zaditen AC Chart, first month", 1.0, "48", "Glucose quiz, six weeks", 0.048),
        ("eDM CLICKS THAT BECAME SIGN-UPS", "8.4%", "Clinical Audit (CPD) eDM", 1.0, "0.5%", "FESS video eDMs", 0.5 / 8.4),
    ]
    y = 2.2
    for head, lbig, lname, lw, rbig, rname, rw in rows:
        text(s, 0.42, y, 12.5, 0.3, head, size=11.5, color=GREY, bold=True, spacing=150, align=CENTER)
        rect(s, mid - full * lw, y + 0.45, full * lw, 0.95, fill=GREEN, line=None, rounded=False)
        rect(s, mid, y + 0.45, max(full * rw, 0.08), 0.95, fill=LIGHTBAR, line=None, rounded=False)
        text(s, mid - full * lw + 0.3, y + 0.45, 3.5, 0.95, lbig, size=32, color=WHITE, bold=True, anchor=MID)
        text(s, mid + full * rw + 0.25, y + 0.45, 2.5, 0.95, rbig, size=32, color=GREY, bold=True, anchor=MID)
        text(s, mid - full, y + 1.5, full - 0.15, 0.35, lname, size=13.5, color=INK)
        text(s, mid + 0.15, y + 1.5, full, 0.35, rname, size=13.5, color=INK)
        rect(s, mid - 0.01, y + 0.35, 0.02, 1.15, fill=INK, line=None, rounded=False)
        y += 2.2
    source(s, SOURCE)


def option_d2(prs, layout):
    """Bars with sign-up numbers: three rows."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    mid, full = 6.67, 5.3
    text(s, mid - full, 1.45, full, 0.35, "TOOLS AND CPD", size=13, color=GREEN, bold=True, spacing=150)
    text(s, mid + 0.15, 1.45, full, 0.35, "VIDEO AND QUIZ", size=13, color=GREY, bold=True, spacing=150)
    rows = [
        ("UNIQUE USERS", "~1,000", "Zaditen AC Chart, first month", 1.0, "48", "Glucose quiz, six weeks", 0.048),
        ("SIGN-UPS FROM THE eDMs", "31", "Clinical Audit (CPD) eDM, TMR", 1.0, "~5", "FESS video eDMs, TMR + MT", 5 / 31),
        ("eDM CLICKS THAT BECAME SIGN-UPS", "8.4%", "Clinical Audit (CPD) eDM", 1.0, "0.5%", "FESS video eDMs", 0.5 / 8.4),
    ]
    y = 1.95
    for head, lbig, lname, lw, rbig, rname, rw in rows:
        text(s, 0.42, y, 12.5, 0.3, head, size=11, color=GREY, bold=True, spacing=150, align=CENTER)
        rect(s, mid - full * lw, y + 0.38, full * lw, 0.75, fill=GREEN, line=None, rounded=False)
        rect(s, mid, y + 0.38, max(full * rw, 0.08), 0.75, fill=LIGHTBAR, line=None, rounded=False)
        text(s, mid - full * lw + 0.3, y + 0.38, 3.5, 0.75, lbig, size=28, color=WHITE, bold=True, anchor=MID)
        text(s, mid + full * rw + 0.25, y + 0.38, 2.5, 0.75, rbig, size=28, color=GREY, bold=True, anchor=MID)
        text(s, mid - full, y + 1.18, full - 0.15, 0.3, lname, size=12.5, color=INK)
        text(s, mid + 0.15, y + 1.18, full, 0.3, rname, size=12.5, color=INK)
        rect(s, mid - 0.01, y + 0.3, 0.02, 0.9, fill=INK, line=None, rounded=False)
        y += 1.6
    source(s, SOURCE + " Sign-ups: TMR Clinical Audit eDM 1 Jul; FESS Clinical Bites eDMs 16 and 17 Sep (estimate above the quiet-week baseline).")


def option_simple(prs, layout):
    """Two plain ranked bar lists: what HCPs used, what got them to sign up."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    charts = [
        (0.42, "TOOLS GET USED", "HCPs who used it", TEAL, [
            ("Zaditen AC Chart", 1000, "~1,000", True),
            ("Clinical Bites videos", 289, "289", False),
            ("Clinical Audit (CPD)", 184, "184", False),
            ("Diabetes Sick Day Plan", 122, "~122", True),
            ("Glucose quiz", 48, "48", False),
        ]),
        (6.82, "CPD GETS SIGN-UPS", "Sign-ups from each eDM campaign", PURPLE, [
            ("Clinical Audit (CPD)", 31, "31", True),
            ("Hydralyte videos", 18, "~18", False),
            ("FESS videos", 5, "~5", False),
            ("Zaditen AC Chart", 2, "~2", False),
        ]),
    ]
    for x, head, sub, colr, bars in charts:
        text(s, x, 1.45, 6.1, 0.5, head, size=24, color=colr, bold=True)
        text(s, x, 1.95, 6.1, 0.3, sub, size=12, color=GREY)
        top = max(v for _, v, _, _ in bars)
        y = 2.5
        for name, v, lab, win in bars:
            text(s, x, y, 2.4, 0.6, name, size=13, color=INK, bold=win, anchor=MID)
            w = 3.0 * v / top
            rect(s, x + 2.5, y + 0.1, max(w, 0.06), 0.4, fill=colr if win else LIGHTBAR, line=None, rounded=False)
            text(s, x + 2.5 + max(w, 0.06) + 0.12, y, 0.9, 0.6, lab, size=14, color=colr if win else GREY, bold=True, anchor=MID)
            y += 0.78
    source(s, QBR_SOURCE + " Left: unique users (AC Chart first month; videos and quiz Jul to Sep; Clinical Audit = enrolments since 19 Mar). "
                           "Right: estimated sign-ups in the two weeks after each Jul to Sep eDM campaign, above a normal week.")


def option_e(prs, layout):
    """Two by two: reach against sign-ups."""
    s = new_slide(prs, layout)
    title(s, size=20, t=TITLE)
    x0, y0, w, h, g = 1.5, 1.5, 5.6, 2.3, 0.15
    cells = [
        (0, 0, SOFT_BLUE, TEAL, "Clinical tools", "Zaditen AC Chart: ~1,000 unique users in its first month, but 0.2% of Zaditen eDM clicks became sign-ups."),
        (1, 0, CARD, MUTED, "Nothing here yet", "The FY28 opportunity: something that brings HCPs in and signs them up."),
        (0, 1, CARD, GREY, "Video and quiz", "FESS video eDMs: 0.5% of clicks became sign-ups. Glucose quiz: 48 unique users in six weeks."),
        (1, 1, SOFT_GREEN, PURPLE, "CPD", "Clinical Audit: 184 enrolled, and 8.4% of clicks on its eDM became sign-ups."),
    ]
    for cx, cy, fill, colr, head, body in cells:
        x, y = x0 + cx * (w + g), y0 + cy * (h + g)
        rect(s, x, y, w, h, fill=fill, line=None, radius=0.05)
        text(s, x + 0.4, y + 0.3, w - 0.8, 0.5, head, size=22, color=colr, bold=True)
        text(s, x + 0.4, y + 0.95, w - 0.8, 1.2, body, size=13.5, color=INK)
    lab = text(s, -1.45, y0 + h + g / 2 - 0.2, 4.75, 0.4, "FEWER  \u2190  HCPs REACHED  \u2192  MORE", size=11, color=GREY, bold=True,
               spacing=150, align=CENTER)
    lab.rotation = -90
    text(s, x0, y0 + 2 * h + g + 0.08, 2 * w + g, 0.3, "FEWER  \u2190  SIGN-UPS  \u2192  MORE", size=11, color=GREY, bold=True,
         spacing=150, align=CENTER)
    source(s, SOURCE)


def main() -> None:
    prs, layout = open_template()
    builds = [option_b, option_a, option_c, option_d, option_d2, option_simple]
    if len(sys.argv) > 2:
        builds = [b for b in builds if b.__name__ in sys.argv[2:]]
    for build in builds:
        build(prs, layout)
    prs.save(sys.argv[1])
    print(f"wrote {sys.argv[1]} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

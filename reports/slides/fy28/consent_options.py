"""Simplified layout options for the compliance slide (one slide per option).

    python reports/slides/fy28/consent_options.py <out.pptx> [option_a ...]
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    AMBER, CARD, GREY, INK, LINE, NAVY, PINK, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import GREEN, SOFT_AMBER, SOFT_BLUE, SOFT_GREEN  # noqa: E402
from rec1_options import CENTER, MID, arrow  # noqa: E402

TITLE = "COMPLIANCE: the journey to a compliant system"
SOURCE = ("Before: consent audit of 1,366 registered HCP accounts (30 Apr 2026). Now: Care Connect consent log, "
          "16 to 23 Nov 2026; Panwar, Care and test accounts excluded.")
FIRST_WEEK = "36 HCPs have answered: 25 Yes, 11 No"


def option_a(prs, layout):
    """Before and now, two panels."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    panels = [
        (0.42, SOFT_AMBER, AMBER, "APRIL 2026", "~1%", "of HCP accounts had marketing consent we could rely on",
         "Marketing emails were paused."),
        (7.3, SOFT_GREEN, GREEN, "SINCE 16 NOV 2026", "Compliant", "Every HCP is asked, and every answer is recorded",
         "We can now email HCPs who said Yes."),
    ]
    for x, fill, colr, head, big, line, small in panels:
        rect(s, x, 1.5, 5.62, 3.9, fill=fill, line=None, radius=0.05)
        text(s, x + 0.45, 1.8, 4.7, 0.3, head, size=12, color=GREY, bold=True, spacing=150)
        text(s, x + 0.45, 2.2, 4.7, 1.2, big, size=58, color=colr, bold=True, anchor=MID)
        text(s, x + 0.45, 3.55, 4.7, 0.8, line, size=15, color=INK, bold=True)
        text(s, x + 0.45, 4.55, 4.7, 0.5, small, size=13, color=INK)
    arrow(s, 6.22, 3.1, 0.9, 0.7, PINK)
    rect(s, 0.42, 5.6, 12.5, 1.1, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.87, 5.6, 11.6, 1.1, [[("First week: ", {"bold": True, "color": NAVY}), (FIRST_WEEK + ".", {})]], size=15, anchor=MID)
    source(s, SOURCE)


def option_b(prs, layout):
    """Three columns, same shape as the Recommendation 1 slide."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    steps = [
        ("WHERE WE STARTED", GREY, "~1% usable consent",
         ["84% of HCPs were never asked", "7% had consent bundled into the T&Cs, which doesn't count",
          "Marketing emails were paused"]),
        ("WHAT WE DID", NAVY, "Rebuilt consent properly",
         ["External lawyers rewrote the Terms of Use and Privacy Policy",
          "Consent is now captured at registration, by pop-up and by email",
          "Live 16 Nov, every HCP emailed 17 Nov"]),
        ("WHERE WE ARE NOW", GREEN, "A compliant system",
         ["Every answer is a recorded Yes or No", "We can now email HCPs who said Yes",
          "First week: " + FIRST_WEEK]),
    ]
    w, gap, x = 3.75, 0.62, 0.42
    for i, (head, colr, big, items) in enumerate(steps):
        rect(s, x, 1.5, w, 5.2)
        rect(s, x, 1.5, w, 0.6, fill=colr, line=None, radius=0.08)
        text(s, x + 0.3, 1.5, w - 0.6, 0.6, head, size=12, color=WHITE, bold=True, spacing=150, anchor=MID)
        text(s, x + 0.3, 2.4, w - 0.6, 0.95, big, size=22, color=colr if colr != GREY else INK, bold=True)
        ty = 3.5
        for item in items:
            rect(s, x + 0.3, ty + 0.1, 0.12, 0.12, fill=colr, line=None, radius=0.5)
            text(s, x + 0.56, ty, w - 0.86, 0.8, item, size=13.5, color=INK)
            ty += 1.0
        if i < 2:
            arrow(s, x + w + 0.08, 3.8, gap - 0.16, 0.55, PINK)
        x += w + gap
    source(s, SOURCE)


def option_c(prs, layout):
    """One sentence and a four-step timeline."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    text(s, 0.42, 1.55, 12.5, 1.1, [[("Since April, Care Connect has gone from ~1% usable consent to a system where every HCP is asked "
                                      "and every answer is recorded.", {})]], size=20, color=NAVY, bold=True)
    points = [
        ("APR 2026", "Audit", "~1% of HCP accounts had consent we could rely on", AMBER),
        ("SEP 2026", "Legal rewrite", "Terms of Use and Privacy Policy redrafted by external lawyers", TEAL),
        ("16 NOV 2026", "Live", "New consent system live for every HCP", TEAL),
        ("FIRST WEEK", "Answers coming in", FIRST_WEEK, GREEN),
    ]
    w = 12.5 / len(points)
    rect(s, 0.42 + 0.2, 3.72, 12.5 - w + 0.1, 0.06, fill=LINE, line=None, rounded=False)
    for i, (when, head, body, colr) in enumerate(points):
        x = 0.42 + i * w
        rect(s, x, 3.5, 0.5, 0.5, fill=colr, line=None, radius=0.5)
        text(s, x, 3.0, w - 0.3, 0.35, when, size=12, color=GREY, bold=True, spacing=150)
        text(s, x, 4.25, w - 0.35, 0.45, head, size=17, color=INK, bold=True)
        text(s, x, 4.78, w - 0.35, 1.2, body, size=13, color=INK)
    source(s, SOURCE)


def option_d(prs, layout):
    """Statement band and three facts."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rect(s, 0.42, 1.5, 12.5, 1.5, fill=NAVY, line=None)
    text(s, 0.87, 1.5, 11.6, 1.5, "Every HCP on Care Connect is now asked for marketing consent, and every answer is recorded.",
         size=20, color=WHITE, bold=True, anchor=MID)
    stats = [
        ("~1%", "of accounts had consent we could rely on in April 2026", AMBER),
        ("16 Nov", "new consent system live, every HCP emailed the next day", TEAL),
        ("36", "HCPs have answered in the first week: 25 Yes, 11 No", GREEN),
    ]
    w, x = (12.5 - 0.6) / 3, 0.42
    for big, line, colr in stats:
        rect(s, x, 3.3, w, 3.4)
        text(s, x + 0.35, 3.55, w - 0.7, 1.4, big, size=60, color=colr, bold=True, anchor=MID)
        text(s, x + 0.35, 5.15, w - 0.7, 1.2, line, size=14.5, color=INK)
        x += w + 0.3
    source(s, SOURCE)


def option_e(prs, layout):
    """Fewest shapes: one headline panel and three plain rows."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rect(s, 0.42, 1.5, 4.6, 5.2, fill=SOFT_GREEN, line=None, radius=0.05)
    text(s, 0.82, 1.9, 3.8, 0.3, "SINCE 16 NOV 2026", size=12, color=GREY, bold=True, spacing=150)
    text(s, 0.82, 2.4, 3.8, 1.2, "Compliant", size=48, color=GREEN, bold=True, anchor=MID)
    text(s, 0.82, 3.9, 3.8, 2.4, "Every HCP is asked for marketing consent, and every answer is recorded.", size=16, color=INK, bold=True)
    rows = [
        ("BEFORE", "~1% of HCP accounts had consent we could rely on. Marketing emails were paused."),
        ("WHAT WE DID", "Legal documents rewritten by external lawyers, consent capture built into the site, every HCP told by email."),
        ("FIRST WEEK", FIRST_WEEK + ". We can now email HCPs who said Yes."),
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

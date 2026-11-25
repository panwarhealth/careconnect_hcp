"""Layout options for the conferences slide (one slide per option).

    python reports/slides/fy28/conference_options.py <out.pptx> [option_a ...]
"""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (  # noqa: E402
    CARD, GREY, INK, LINE, NAVY, PINK, PURPLE, TEAL, WHITE,
    new_slide, open_template, rect, source, text, title,
)
from build_fy28_slides import GREEN, SOFT_BLUE, SOFT_GREEN  # noqa: E402
from rec1_options import CENTER, MID, arrow  # noqa: E402

TITLE = "CONFERENCES: turning stand conversations into Care Connect sign-ups"
SOURCE = "Hours are Panwar Health estimates; cost to be finalised. iPhone price to be quoted (around A$1,150 for the base model)."
ENTRY = [
    ("AT THE STAND", "Swap contacts", "HCPs tap phones with our iPhone, or our host types in their name and email. "
                                      "We create their account after the event."),
    ("ON THE BANNER AND FLYERS", "Scan the QR code", "For HCPs who'd rather not stop. A sign-up page explains the benefits "
                                                   "and checks their AHPRA number as they register."),
]
STEPS = [
    ("Welcome email", "with a link to log in"),
    ("Accept the Terms", "can't be skipped"),
    ("Marketing consent", "Yes or No"),
    ("Keep or change password", "their choice"),
    ("Welcome to Care Connect", "page with relevant resources"),
]


def hours_box(s, x, y, w=2.45, h=0.85):
    rect(s, x, y, w, h, fill=PINK, line=None, radius=0.08)
    text(s, x, y + 0.02, w, h * 0.6, "~31 HRS", size=24, color=WHITE, bold=True, align=CENTER, anchor=MID)
    text(s, x, y + h * 0.55, w, h * 0.4, "+ one iPhone", size=12, color=WHITE, bold=True, align=CENTER, anchor=MID)


def option_a(prs, layout):
    """Two ways in on the left, one path on the right."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    text(s, 0.42, 1.45, 5.0, 0.3, "TWO WAYS IN", size=12, color=GREY, bold=True, spacing=150)
    y = 1.85
    for head, big, body in ENTRY:
        rect(s, 0.42, y, 5.3, 1.75)
        text(s, 0.72, y + 0.2, 4.7, 0.3, head, size=11, color=TEAL, bold=True, spacing=120)
        text(s, 0.72, y + 0.5, 4.7, 0.45, big, size=20, color=INK, bold=True)
        text(s, 0.72, y + 1.0, 4.7, 0.7, body, size=12, color=INK)
        y += 1.95
    arrow(s, 5.9, 3.3, 0.7, 0.6, PINK)
    text(s, 6.8, 1.45, 6.0, 0.3, "ONE PATH FOR EVERYONE", size=12, color=GREY, bold=True, spacing=150)
    y = 1.85
    for i, (head, sub) in enumerate(STEPS):
        colr = GREEN if i == len(STEPS) - 1 else NAVY
        rect(s, 6.8, y + 0.12, 0.5, 0.5, fill=colr, line=None, radius=0.5)
        text(s, 6.8, y + 0.12, 0.5, 0.5, str(i + 1), size=14, color=WHITE, bold=True, align=CENTER, anchor=MID)
        text(s, 7.5, y, 5.4, 0.74, [[(head, {"bold": True}), ("  " + sub, {"color": GREY})]], size=15, color=INK, anchor=MID)
        y += 0.78
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("Every sign-up is tagged by conference, ", {"bold": True, "color": NAVY}),
                                    ("so we can report what each event brought in.", {})]], size=13, anchor=MID)
    hours_box(s, 10.47, 5.85)
    source(s, SOURCE)


def option_b(prs, layout):
    """A left-to-right journey, two doors at the start."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    y = 1.6
    for head, big, body in ENTRY:
        rect(s, 0.42, y, 3.3, 1.55, fill=SOFT_BLUE, line=None, radius=0.06)
        text(s, 0.65, y + 0.18, 2.9, 0.3, head, size=10.5, color=NAVY, bold=True, spacing=100)
        text(s, 0.65, y + 0.5, 2.9, 0.45, big, size=18, color=INK, bold=True)
        y += 1.75
    arrow(s, 3.85, 2.75, 0.55, 0.55, PINK)
    w, x0 = (12.92 - 4.55 - 4 * 0.12) / 5, 4.55
    for i, (head, sub) in enumerate(STEPS):
        x = x0 + i * (w + 0.12)
        colr = GREEN if i == len(STEPS) - 1 else NAVY
        rect(s, x, 1.6, w, 3.3, fill=colr, line=None, radius=0.06)
        text(s, x + 0.15, 1.8, w - 0.3, 0.5, str(i + 1), size=28, color=WHITE, bold=True)
        text(s, x + 0.15, 2.6, w - 0.3, 1.2, head, size=15, color=WHITE, bold=True)
        text(s, x + 0.15, 3.85, w - 0.3, 0.9, sub, size=11.5, color=WHITE)
    rect(s, 0.42, 5.2, 12.5, 0.02, fill=LINE, line=None, rounded=False)
    cols = [
        ("At the stand: ", "HCPs tap phones with our iPhone, or our host types in their name and email."),
        ("QR code: ", "a sign-up page explains the benefits and checks their AHPRA number as they register."),
    ]
    text(s, 0.42, 5.3, 9.6, 0.5, [[(cols[0][0], {"bold": True}), (cols[0][1], {})]], size=12.5, color=INK, anchor=MID)
    text(s, 0.42, 5.8, 9.6, 0.5, [[(cols[1][0], {"bold": True}), (cols[1][1], {})]], size=12.5, color=INK, anchor=MID)
    hours_box(s, 10.47, 5.45)
    source(s, SOURCE + " Every sign-up is tagged by conference for reporting.")


def option_c(prs, layout):
    """Three columns: at the stand, the QR code, what happens next."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    cols = [
        ("AT THE STAND", TEAL, "Swap contacts",
         ["HCPs tap phones with our iPhone", "Or our host types in their name and email", "We create their account after the event"]),
        ("ON THE BANNER AND FLYERS", PURPLE, "Scan the QR code",
         ["For HCPs who'd rather not stop", "A sign-up page explains the benefits", "AHPRA number checked as they register"]),
        ("WHAT HAPPENS NEXT", GREEN, "A guided first login",
         ["Welcome email with a link to log in", "Accept the Terms, then marketing consent",
          "Keep or change their password", "Finish on a Welcome to Care Connect page"]),
    ]
    w, gap, x = 3.75, 0.62, 0.42
    for i, (head, colr, big, items) in enumerate(cols):
        rect(s, x, 1.5, w, 4.15)
        rect(s, x, 1.5, w, 0.6, fill=colr, line=None, radius=0.08)
        text(s, x + 0.3, 1.5, w - 0.6, 0.6, head, size=11.5, color=WHITE, bold=True, spacing=120, anchor=MID)
        text(s, x + 0.3, 2.35, w - 0.6, 0.6, big, size=20, color=colr, bold=True)
        ty = 3.1
        for item in items:
            rect(s, x + 0.3, ty + 0.09, 0.12, 0.12, fill=colr, line=None, radius=0.5)
            text(s, x + 0.56, ty, w - 0.86, 0.55, item, size=13, color=INK)
            ty += 2.25 / len(items)
        if i == 0:
            text(s, x + w, 3.25, gap, 0.5, "or", size=16, color=GREY, bold=True, align=CENTER, anchor=MID)
        if i == 1:
            arrow(s, x + w + 0.08, 3.3, gap - 0.16, 0.55, PINK)
        x += w + gap
    rect(s, 0.42, 5.85, 9.85, 0.85, fill=SOFT_BLUE, line=None, radius=0.08)
    text(s, 0.67, 5.85, 9.4, 0.85, [[("Starting target: ", {"bold": True, "color": NAVY}),
                                    ("20 new HCP sign-ups per conference.", {})]], size=15, anchor=MID)
    hours_box(s, 10.47, 5.85)
    source(s, SOURCE)


def option_d(prs, layout):
    """Fewest shapes: headline panel and three plain rows (same shape as the compliance slide)."""
    s = new_slide(prs, layout)
    title(s, size=22, t=TITLE)
    rect(s, 0.42, 1.5, 4.6, 5.2, fill=SOFT_GREEN, line=None, radius=0.05)
    text(s, 0.82, 1.9, 3.8, 0.3, "FY28 CONFERENCES", size=12, color=GREY, bold=True, spacing=150)
    text(s, 0.82, 2.4, 3.8, 1.7, ["Meet them,", "sign them up"], size=36, color=GREEN, bold=True, anchor=MID)
    text(s, 0.82, 4.3, 3.8, 1.0, "Every HCP we meet gets an easy way into Care Connect.", size=16, color=INK, bold=True)
    text(s, 0.82, 5.5, 3.8, 0.9, [[("~31 hrs", {"bold": True, "color": PINK}), (" to build, plus one iPhone", {})]], size=15, color=INK)
    rows = [
        ("AT THE STAND", "HCPs tap phones with our iPhone, or our host types in their name and email. We create their account after the event."),
        ("ON THE BANNER AND FLYERS", "A QR code for HCPs who'd rather not stop. The sign-up page explains the benefits and checks their AHPRA number."),
        ("THEN, FOR EVERYONE", "Welcome email, accept the Terms, marketing consent, then a Welcome to Care Connect page. Tagged by conference."),
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
    builds = [option_a, option_b, option_c, option_d]
    if len(sys.argv) > 2:
        builds = [b for b in builds if b.__name__ in sys.argv[2:]]
    for build in builds:
        build(prs, layout)
    prs.save(sys.argv[1])
    print(f"wrote {sys.argv[1]} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

"""Merge the captured screens into one PDF with a bookmark tree.

    python3 lib/assemble.py --dir caph0150/out --out caph0150/out/walkthrough.pdf

Reads manifest.json, so pages come out in the order the walk captured them rather than in
whatever order the filenames happen to sort. Group headings become the top level of the bookmarks.

Paper:
  a4        (default) every page scaled to A4 width, height left proportional so no screen is cut
  a4-pages  true A4 pages; a long screen is split across as many as it needs
  native    the raw 1280px-wide captures, untouched

A cover page is only added when --title is passed.
"""
import argparse
import json
import math
import os
import sys
from datetime import date

import fitz

A4_WIDTH = 595.276
A4_HEIGHT = 841.89

parser = argparse.ArgumentParser()
parser.add_argument("--dir", default="out", help="directory the walk wrote")
parser.add_argument("--out", default=None, help="output PDF path")
parser.add_argument("--paper", default="a4", choices=["a4", "a4-pages", "native"])
parser.add_argument("--title", default=None, help="add a cover page with this title")
parser.add_argument("--subtitle", default="Course review copy")
parser.add_argument("--append", nargs="*", default=[], help="extra PDFs to append, in order")
parser.add_argument(
    "--narrow-width",
    type=float,
    default=330,
    help="points wide to draw phone-width captures at, centred on the page, instead of stretching them to A4 width",
)
parser.add_argument(
    "--after",
    nargs=2,
    action="append",
    default=[],
    metavar=("SCREEN_FILE", "PDF"),
    help="insert PDF straight after the screen with this file name, e.g. the document a page's button downloads",
)
args = parser.parse_args()

manifest_path = os.path.join(args.dir, "manifest.json")
if not os.path.exists(manifest_path):
    sys.exit(f"No manifest at {manifest_path}. Run the walk first.")

with open(manifest_path, encoding="utf8") as handle:
    screens = json.load(handle)
if not screens:
    sys.exit("The manifest is empty.")

out_path = args.out or os.path.join(args.dir, "walkthrough.pdf")
doc = fitz.open()
toc = []


def cover(title, subtitle):
    page = doc.new_page(width=A4_WIDTH, height=A4_HEIGHT)
    page.insert_text((60, 300), title, fontname="hebo", fontsize=30)
    if subtitle:
        page.insert_text((60, 340), subtitle, fontname="helv", fontsize=14)
    page.insert_text((60, 380), date.today().strftime("%d %B %Y"), fontname="helv", fontsize=11)


CAPTION_HEIGHT = 58  # room for two lines


def caption_band(page, text):
    """A labelled strip across the top of the page, above the screen."""
    page.draw_rect(fitz.Rect(0, 0, page.rect.width, CAPTION_HEIGHT), color=None, fill=(0.93, 0.97, 0.99))
    # insert_textbox draws nothing at all when the text overflows, so check and shrink.
    for size in (10.5, 9.5, 8.5):
        if page.insert_textbox(
            fitz.Rect(24, 14, page.rect.width - 24, CAPTION_HEIGHT - 6), text, fontname="hebo", fontsize=size, color=(0.05, 0.16, 0.25)
        ) >= 0:
            return
    print(f"  ! caption too long: {text[:60]}")


def place(src_doc, caption=None):
    """Draw one captured screen into the output at the chosen paper size, staying vector."""
    src = src_doc[0]
    top = CAPTION_HEIGHT if caption else 0
    if args.paper == "native":
        doc.insert_pdf(src_doc)
        return

    scale = A4_WIDTH / src.rect.width

    # A phone capture stretched to A4 width prints its text at three times desktop size.
    narrow = src.rect.width < A4_WIDTH and args.narrow_width < A4_WIDTH
    if args.paper == "a4" and narrow:
        scale = args.narrow_width / src.rect.width
        height = src.rect.height * scale
        page = doc.new_page(width=A4_WIDTH, height=top + height + 40)
        left = (A4_WIDTH - args.narrow_width) / 2
        page.show_pdf_page(fitz.Rect(left, top + 20, left + args.narrow_width, top + 20 + height), src_doc, 0)
        if caption:
            caption_band(page, caption)
        return

    if args.paper == "a4":
        page = doc.new_page(width=A4_WIDTH, height=top + src.rect.height * scale)
        page.show_pdf_page(fitz.Rect(0, top, A4_WIDTH, page.rect.height), src_doc, 0)
        if caption:
            caption_band(page, caption)
        return

    # a4-pages: slice the tall capture into A4-high bands, in source coordinates.
    band = A4_HEIGHT / scale
    for index in range(max(1, math.ceil(src.rect.height / band))):
        top = index * band
        bottom = min(src.rect.height, top + band)
        page = doc.new_page(width=A4_WIDTH, height=(bottom - top) * scale)
        page.show_pdf_page(
            page.rect, src_doc, 0, clip=fitz.Rect(0, top, src.rect.width, bottom)
        )


if args.title:
    cover(args.title, args.subtitle)
    toc.append([1, "Cover", 1])

current_group = None
for screen in screens:
    path = os.path.join(args.dir, screen["file"])
    if not os.path.exists(path):
        print(f"  ! missing {screen['file']}, skipped")
        continue
    start = len(doc) + 1
    with fitz.open(path) as src_doc:
        place(src_doc, screen.get("caption"))

    group = screen.get("group")
    if group and group != current_group:
        toc.append([1, group, start])
        current_group = group
    elif not group:
        current_group = None
    toc.append([2 if group else 1, screen["label"], start])

    for screen_file, extra in args.after:
        if screen_file != screen["file"]:
            continue
        if not os.path.exists(extra):
            print(f"  ! missing {extra}, skipped")
            continue
        start = len(doc) + 1
        with fitz.open(extra) as src_doc:
            doc.insert_pdf(src_doc)
        toc.append([3 if group else 2, os.path.splitext(os.path.basename(extra))[0], start])

for extra in args.append:
    if not os.path.exists(extra):
        print(f"  ! missing {extra}, skipped")
        continue
    start = len(doc) + 1
    with fitz.open(extra) as src_doc:
        doc.insert_pdf(src_doc)
    toc.append([1, os.path.splitext(os.path.basename(extra))[0], start])

doc.set_toc(toc)
doc.save(out_path, deflate=True, garbage=4)
print(f"wrote {out_path}, {len(doc)} pages from {len(screens)} screens, paper {args.paper}")

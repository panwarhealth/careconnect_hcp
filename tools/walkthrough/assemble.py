"""Merge the crawled screens into one PDF with a bookmark tree.

    py -X utf8 assemble.py --dir out --out VAPO0005_walkthrough.pdf

Reads manifest.json, so pages come out in the order the crawler walked them rather than in
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
parser.add_argument("--dir", default="out", help="directory the crawler wrote")
parser.add_argument("--out", default=None, help="output PDF path")
parser.add_argument("--paper", default="a4", choices=["a4", "a4-pages", "native"])
parser.add_argument("--title", default=None, help="add a cover page with this title")
parser.add_argument("--subtitle", default="Course review copy")
parser.add_argument("--append", nargs="*", default=[], help="extra PDFs to append, in order")
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
    sys.exit(f"No manifest at {manifest_path}. Run crawl.mjs first.")

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
    page.insert_text((60, 782), "Clinical Studio", fontname="helv", fontsize=9)


def place(src_doc):
    """Draw one captured screen into the output at the chosen paper size, staying vector."""
    src = src_doc[0]
    if args.paper == "native":
        doc.insert_pdf(src_doc)
        return

    scale = A4_WIDTH / src.rect.width

    if args.paper == "a4":
        page = doc.new_page(width=A4_WIDTH, height=src.rect.height * scale)
        page.show_pdf_page(page.rect, src_doc, 0)
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
        place(src_doc)

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

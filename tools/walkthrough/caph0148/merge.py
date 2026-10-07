"""Merges the five captures into the review PDF in the v1 layout: one full-length page each, at
v1's page width, bookmarked like v1.   python3 merge.py out "out/CAPH0148 ... v2.pdf"
"""
import os
import sys

import fitz

out_dir, out_path = sys.argv[1], sys.argv[2]
WIDTH = 1724
pages = [
    ("1_case_study.pdf", os.path.basename(out_path)),
    ("2_activity_1_pop_up.pdf", "Activity 1 pop up.pdf"),
    ("3_pop_up_2.pdf", "Pop up 2.pdf"),
    ("4_pop_up_3_error.pdf", "Pop up 3 error.pdf"),
    ("5_pop_up_3_correct.pdf", "Pop up 3 correct.pdf"),
]
doc = fitz.open()
toc = []
for file, title in pages:
    with fitz.open(os.path.join(out_dir, file)) as src:
        rect = src[0].rect
        page = doc.new_page(width=WIDTH, height=rect.height * WIDTH / rect.width)
        page.show_pdf_page(page.rect, src, 0)
    toc.append([1, title, len(doc)])
doc.set_toc(toc)
doc.save(out_path, deflate=True, garbage=4)
print(f"wrote {out_path}, {len(doc)} pages")

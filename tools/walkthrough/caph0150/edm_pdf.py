"""Slices the eDM screenshots into a bookmarked A4 review PDF, cutting at blank rows.
   usage: edm_pdf.py <output.pdf>"""
import os
import sys

import fitz
from PIL import Image

EMAILS = [
    ('Audit_update_started_email', 'Updates to the Anal Fissure Management Mini Clinical Audit'),
    ('Audit_update_not_started_email', 'Continue your Anal Fissure Management CPD activity—NEW streamlined audit available now'),
]
PAGE_W, PAGE_H, MARGIN = 595, 842, 30
MIN_GAP = 44   # 22px at the 2x screenshot scale
MAX_FIT = 1.15
OUT_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out')


def is_blank_row(img, y):
    row = img.crop((0, y, img.width, y + 1)).getcolors(4096)
    return row is not None and len(row) <= 3 and all(sum(c) >= 690 for _, c in row)


def section_gap(img, top, bottom):
    """Middle of the lowest blank run tall enough to sit between sections, not lines of text."""
    run = 0
    for y in range(bottom, top, -1):
        if is_blank_row(img, y):
            run += 1
        else:
            if run >= MIN_GAP:
                return y + run // 2
            run = 0
    return None


doc, toc = fitz.open(), []
for name, subject in EMAILS:
    img = Image.open(os.path.join(OUT_DIR, f'edm-{name}.png')).convert('RGB')
    slice_h = int((PAGE_H - 2 * MARGIN) * img.width / (PAGE_W - 2 * MARGIN))
    toc.append([1, subject, doc.page_count + 1])
    y = 0
    while y < img.height:
        # A short remainder is scaled to fit rather than orphaned on its own page.
        end = img.height if img.height - y <= slice_h * MAX_FIT else y + slice_h
        if end < img.height:
            end = section_gap(img, y + slice_h // 2, end) or end
        part = img.crop((0, y, img.width, end))
        tmp = os.path.join(OUT_DIR, 'edm-slice.png')
        part.save(tmp)
        page = doc.new_page(width=PAGE_W, height=PAGE_H)
        w = PAGE_W - 2 * MARGIN
        h = part.height * w / part.width
        if h > PAGE_H - 2 * MARGIN:
            w, h = w * (PAGE_H - 2 * MARGIN) / h, PAGE_H - 2 * MARGIN
        x = (PAGE_W - w) / 2
        page.insert_image(fitz.Rect(x, MARGIN, x + w, MARGIN + h), filename=tmp)
        y = end
doc.set_toc(toc)
doc.save(sys.argv[1], deflate=True)
print(sys.argv[1], doc.page_count, 'pages', [t[2] for t in toc])

"""Cuts each band capture (a full-page print) down to its band, from the "crop" field the walk
writes in manifest.json as [top, height] in CSS px.   python3 crop.py out
"""
import json
import os
import sys

import fitz

out_dir = sys.argv[1]
manifest_path = os.path.join(out_dir, "manifest.json")
with open(manifest_path, encoding="utf8") as handle:
    manifest = json.load(handle)

for screen in manifest:
    if "crop" not in screen:
        continue
    path = os.path.join(out_dir, screen["file"])
    top, height = screen.pop("crop")
    with fitz.open(path) as src:
        page = src[0]
        scale = page.rect.width / 1280
        clip = fitz.Rect(0, top * scale, page.rect.width, min(page.rect.height, (top + height) * scale))
        dst = fitz.open()
        dst.new_page(width=clip.width, height=clip.height).show_pdf_page(fitz.Rect(0, 0, clip.width, clip.height), src, 0, clip=clip)
        dst.save(path + ".tmp", deflate=True, garbage=4)
    os.replace(path + ".tmp", path)

with open(manifest_path, "w", encoding="utf8") as handle:
    json.dump(manifest, handle, indent=2)

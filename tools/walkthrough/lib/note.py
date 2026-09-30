"""Writes a text-only note page and puts it first in the manifest, so it opens the PDF.

    python3 note.py out "The chooser screen only shows to users with legacy progress."
"""
import json
import os
import sys

import fitz

out_dir, text = sys.argv[1], sys.argv[2]
doc = fitz.open()
page = doc.new_page(width=595.276, height=549)
page.insert_textbox(fitz.Rect(60, 60, 535, 300), text, fontname="helv", fontsize=12)
doc.save(os.path.join(out_dir, "000_note.pdf"))

manifest_path = os.path.join(out_dir, "manifest.json")
with open(manifest_path, encoding="utf8") as handle:
    manifest = json.load(handle)
if not manifest or manifest[0]["file"] != "000_note.pdf":
    manifest.insert(0, {"file": "000_note.pdf", "label": "Note", "group": None, "section": None, "url": None})
    with open(manifest_path, "w", encoding="utf8") as handle:
        json.dump(manifest, handle, indent=2)

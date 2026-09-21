"""Writes the text-only note page that opens the walkthrough PDF."""
import sys
import fitz

out = sys.argv[1]
text = sys.argv[2] if len(sys.argv) > 2 else "The module chooser screen, shown only to users who have already interacted with the legacy Mini Clinical Audit."
doc = fitz.open()
page = doc.new_page(width=595.276, height=549)
page.insert_textbox(fitz.Rect(60, 60, 535, 300), text, fontname="helv", fontsize=12)
doc.save(out)

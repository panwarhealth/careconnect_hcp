"""JAS26 eDM reconciliation slide, lifted from the review deck with the sign-up total corrected to a range.

    reports/.venv/bin/python reports/slides/build_jas26_reconciliation_slide.py

Reads the review deck (never writes to it), keeps only the reconciliation slide, edits the sign-up cells, bullet and source line.
"""
from pathlib import Path
from pptx import Presentation

SRC = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 all updated slides - Rob (review).pptx")
DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 eDM reconciliation slide - Rob.pptx")

BULLET1 = ("About half of the 95 new registrations this QTR (46–56) can be attributed to eDM activity; the rest match the quiet-period rate of "
           "about 0.5 a day (48 over the quarter). The Clinical Audit eDM (1 Jul) again converted best: 31 sign-ups, 8.4% of unique clicks")
SOURCE = ("Source: publisher reports + GA4, JAS26 (send day to 30 Sep). Sign-ups per campaign = sign-ups in the 14 days after the send minus the quiet-period rate "
          "(6 Jul–16 Aug, 0.52/day), or GA4-tagged sign-ups if higher. Raw sign-ups per window: Audit 38, Hydralyte 25, Zaditen 3, AJP 4, FESS 12; Zaditen and AJP ran below "
          "the quiet rate, hence the range. Conversion off unique clicks; on MT's per-link unique clicks (537 / 419) Hydralyte converts 1.6%, FESS 0.5%.")


def set_text(tf, new: str) -> None:
    """Replace a text frame's first paragraph text, keeping its first run's formatting."""
    para = tf.paragraphs[0]
    runs = para.runs
    runs[0].text = new
    for r in runs[1:]:
        r._r.getparent().remove(r._r)


def main() -> None:
    prs = Presentation(str(SRC))
    keep = next(i for i, s in enumerate(prs.slides) if any(sh.has_text_frame and "RECONCILIATION" in sh.text_frame.text for sh in s.shapes))
    sldIdLst = prs.slides._sldIdLst
    for i, sldId in reversed(list(enumerate(list(sldIdLst)))):
        if i != keep:
            prs.part.drop_rel(sldId.rId)
            sldIdLst.remove(sldId)
    s = prs.slides[0]
    for sh in list(s.shapes):
        if sh.has_table:
            cell = sh.table.cell(len(sh.table.rows) - 1, 6)
            set_text(cell.text_frame, "46–56")
        elif sh.has_text_frame and sh.text_frame.text.startswith("Of the 95 new registrations"):
            set_text(sh.text_frame, BULLET1)
        elif sh.has_text_frame and sh.text_frame.text.startswith("Source: publisher reports"):
            set_text(sh.text_frame, SOURCE)
        elif sh.has_text_frame and sh.text_frame.text.strip() == "TO BE UPDATED":
            sh._element.getparent().remove(sh._element)
    # The table renders taller than its nominal row heights; nudge the blocks below it down.
    from pptx.util import Inches
    shift = {"TextBox 3": 0.12, "TextBox 4": 0.14, "Rounded Rectangle 5": 0.08, "TextBox 6": 0.08, "Source": 0.08}
    for sh in s.shapes:
        if sh.name in shift:
            sh.top = sh.top + Inches(shift[sh.name])
    prs.save(str(DEST))
    print(f"wrote {DEST}")


if __name__ == "__main__":
    main()

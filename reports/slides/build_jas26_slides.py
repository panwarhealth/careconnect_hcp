"""Build Rob's JAS26 quarterly-review slides as a standalone pptx for pasting
into the SharePoint master deck (CAPH0107).

    reports/.venv/bin/python reports/slides/build_jas26_slides.py

Built on the AMJ26 deck's own master + DEFAULT layout so pasted slides look
native. Figures come from reports/out/JAS26/ CSVs where a CSV exists; the
rest are the JAS26 pulls recorded in those CSVs' notes.
"""
from __future__ import annotations

import copy
import csv
import sys
from datetime import date, timedelta
from pathlib import Path

from lxml import etree
from pptx import Presentation
from pptx.chart.data import CategoryChartData
from pptx.dml.color import RGBColor
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION, XL_LEGEND_POSITION
from pptx.enum.dml import MSO_THEME_COLOR
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import MSO_ANCHOR, PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Emu, Inches, Pt

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "out" / "JAS26"
TEMPLATE = Path("/mnt/c/Users/User/Downloads/CAPH0107 Care x PH - AMJ '26 HCP Results (Presented 16.07.26).pptx")
DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 Care Connect slides - Rob.pptx")

NAVY = RGBColor(0x15, 0x36, 0x6B)
TEAL = RGBColor(0x16, 0x72, 0x6B)
INK = RGBColor(0x1F, 0x29, 0x37)
GREY = RGBColor(0x6B, 0x72, 0x80)
MUTED = RGBColor(0x9C, 0xA3, 0xAF)
LINE = RGBColor(0xE5, 0xE7, 0xEB)
CARD = RGBColor(0xF8, 0xFA, 0xFC)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
LIGHTBAR = RGBColor(0xD1, 0xD5, 0xDB)
SKY = RGBColor(0xBF, 0xE3, 0xEE)
BLUE = RGBColor(0x1F, 0x77, 0xC4)
AMBER = RGBColor(0xD9, 0x77, 0x06)
RED = RGBColor(0xC0, 0x1E, 0x1E)
PINK = RGBColor(0xB5, 0x3C, 0x96)
PURPLE = RGBColor(0x6A, 0x2C, 0x9A)
PEACH = RGBColor(0xF8, 0xB8, 0x94)
ORANGE = RGBColor(0xF2, 0x7A, 0x2A)
TEAL2 = RGBColor(0x40, 0xB0, 0xC8)
MIDBLUE = RGBColor(0x4F, 0x81, 0xC7)
FONT = "Arial"


def csv_rows(name: str) -> list[dict]:
    with open(OUT / name, newline="", encoding="utf-8") as f:
        return list(csv.DictReader(f))


# ── primitives ──────────────────────────────────────────────────────────────
def _font(run, size, color=INK, bold=False, italic=False, spacing=None):
    run.font.name = FONT
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.italic = italic
    if isinstance(color, RGBColor):
        run.font.color.rgb = color
    else:
        run.font.color.theme_color = color
    if spacing:
        run._r.get_or_add_rPr().set("spc", str(spacing))


def text(slide, x, y, w, h, paras, size=11, color=INK, bold=False, italic=False,
         align=PP_ALIGN.LEFT, anchor=MSO_ANCHOR.TOP, spacing=None, line_spacing=None, name=None):
    """paras: str, or list of paragraphs; a paragraph is str or list of (text, overrides) runs."""
    box = slide.shapes.add_textbox(Inches(x), Inches(y), Inches(w), Inches(h))
    if name:
        box.name = name
    tf = box.text_frame
    tf.word_wrap = True
    tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
    tf.vertical_anchor = anchor
    if isinstance(paras, str):
        paras = [paras]
    for i, para in enumerate(paras):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.alignment = align
        if line_spacing:
            p.line_spacing = line_spacing
        runs = [(para, {})] if isinstance(para, str) else para
        for t, o in runs:
            r = p.add_run()
            r.text = t
            _font(r, o.get("size", size), o.get("color", color), o.get("bold", bold),
                  o.get("italic", italic), o.get("spacing", spacing))
    return box


def bullets(slide, x, y, w, h, items, size=12, color=INK, gap=4):
    box = text(slide, x, y, w, h, items, size=size, color=color)
    for p in box.text_frame.paragraphs:
        pPr = p._p.get_or_add_pPr()
        pPr.set("marL", str(Inches(0.22)))
        pPr.set("indent", str(-Inches(0.22)))
        bu = etree.SubElement(pPr, qn("a:buChar"))
        bu.set("char", "•")
        p.space_after = Pt(gap)
    return box


def rect(slide, x, y, w, h, fill=CARD, line=LINE, line_w=0.75, rounded=True, radius=0.04, name=None):
    shp = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE if rounded else MSO_SHAPE.RECTANGLE,
                                 Inches(x), Inches(y), Inches(w), Inches(h))
    if rounded:
        shp.adjustments[0] = radius
    if fill is None:
        shp.fill.background()
    else:
        shp.fill.solid()
        shp.fill.fore_color.rgb = fill
    if line is None:
        shp.line.fill.background()
    else:
        shp.line.color.rgb = line
        shp.line.width = Pt(line_w)
    shp.shadow.inherit = False
    if name:
        shp.name = name
    return shp


def title(slide, t, size=24):
    text(slide, 0.32, 0.36, 12.6, 0.95, t, size=size, color=MSO_THEME_COLOR.ACCENT_4, bold=True,
         anchor=MSO_ANCHOR.MIDDLE, name="Title")


def subtitle(slide, t, y=1.36, italic=False, size=13):
    text(slide, 0.32, y, 12.6, 0.5, t, size=size, color=INK, italic=italic)


def label(slide, x, y, w, t):
    text(slide, x, y, w, 0.3, t.upper(), size=11.5, color=GREY, bold=True, spacing=200)


def source(slide, t):
    text(slide, 0.32, 6.86, 12.4, 0.36, t, size=8.5, color=GREY, italic=True, name="Source")


def stat_card(slide, x, y, w, h, caption, value, note, value_color=NAVY, value_size=30):
    rect(slide, x, y, w, h)
    compact = h < 1.45
    if caption:
        text(slide, x + 0.22, y + 0.12, w - 0.44, 0.3, caption, size=11.5 if compact else 12, color=GREY)
        vy, ny = (0.36, 0.86) if compact else (0.42, 1.02)
    else:
        vy, ny = 0.15, 0.68
    text(slide, x + 0.22, y + vy, w - 0.44, 0.55, value, size=value_size, color=value_color, bold=True)
    if note:
        text(slide, x + 0.22, y + ny, w - 0.44, h - ny - 0.05, note.split("\n"), size=10 if compact else 10.5, color=GREY)


def _cell_border(cell, color="E5E7EB", w=9525):
    tcPr = cell._tc.get_or_add_tcPr()
    for tag in ("a:lnL", "a:lnR", "a:lnT", "a:lnB"):
        old = tcPr.find(qn(tag))
        if old is not None:
            tcPr.remove(old)
    for i, tag in enumerate(("a:lnL", "a:lnR", "a:lnT", "a:lnB")):
        ln = etree.Element(qn(tag))
        ln.set("w", str(w))
        sf = etree.SubElement(ln, qn("a:solidFill"))
        etree.SubElement(sf, qn("a:srgbClr")).set("val", color)
        tcPr.insert(i, ln)


def table(slide, x, y, w, col_w, rows, row_h=0.36, header_h=None, size=10.5, header_size=10.5,
          align=None, bold_cols=(), color_cells=None, fills=None, wrap_header=True):
    """rows[0] is the header. align: per-column PP_ALIGN. color_cells: {(r,c): RGBColor}."""
    nrows, ncols = len(rows), len(rows[0])
    header_h = header_h or row_h
    shp = slide.shapes.add_table(nrows, ncols, Inches(x), Inches(y), Inches(w),
                                 Inches(header_h + row_h * (nrows - 1)))
    tbl = shp.table
    tblPr = tbl._tbl.tblPr
    for attr in ("firstRow", "bandRow"):
        tblPr.set(attr, "0")
    style = tblPr.find(qn("a:tableStyleId"))
    if style is not None:
        style.text = "{5940675A-B579-460E-94D1-54222C63F5DA}"  # No Style, Table Grid
    total = sum(col_w)
    for i, cw in enumerate(col_w):
        tbl.columns[i].width = Inches(w * cw / total)
    for r in range(nrows):
        tbl.rows[r].height = Inches(header_h if r == 0 else row_h)
        for c in range(ncols):
            cell = tbl.cell(r, c)
            val = rows[r][c]
            cell.margin_left = cell.margin_right = Inches(0.1)
            cell.margin_top = cell.margin_bottom = Inches(0.03)
            cell.vertical_anchor = MSO_ANCHOR.MIDDLE
            cell.fill.solid()
            if r == 0:
                cell.fill.fore_color.rgb = NAVY
            elif fills and (r, c) in fills:
                cell.fill.fore_color.rgb = fills[(r, c)]
            else:
                cell.fill.fore_color.rgb = WHITE
            tf = cell.text_frame
            tf.word_wrap = True
            p = tf.paragraphs[0]
            p.alignment = (align[c] if align else (PP_ALIGN.LEFT if c == 0 else PP_ALIGN.RIGHT))
            run = p.add_run()
            run.text = str(val)
            colr = WHITE if r == 0 else (color_cells or {}).get((r, c), INK)
            _font(run, header_size if r == 0 else size, colr, bold=(r == 0 or c in bold_cols))
            _cell_border(cell)
    return tbl


def style_chart(chart, size=10):
    chart.font.name = FONT
    chart.font.size = Pt(size)
    chart.font.color.rgb = INK


def axis_quiet(axis, gridlines=False, size=10):
    axis.has_major_gridlines = gridlines
    if gridlines:
        axis.major_gridlines.format.line.color.rgb = LINE
        axis.major_gridlines.format.line.width = Pt(0.75)
    axis.format.line.color.rgb = LIGHTBAR
    axis.tick_labels.font.size = Pt(size)
    axis.tick_labels.font.color.rgb = INK


def set_plot_layout(chart, px, py, pw, ph):
    plot = chart._chartSpace.find(qn("c:chart")).find(qn("c:plotArea"))
    lay = plot.find(qn("c:layout"))
    if lay is None:
        lay = etree.Element(qn("c:layout"))
        plot.insert(0, lay)
    for child in list(lay):
        lay.remove(child)
    ml = etree.SubElement(lay, qn("c:manualLayout"))
    for tag, val in (("c:layoutTarget", "inner"), ("c:xMode", "edge"), ("c:yMode", "edge"),
                     ("c:x", str(px)), ("c:y", str(py)), ("c:w", str(pw)), ("c:h", str(ph))):
        etree.SubElement(ml, qn(tag)).set("val", val)


def no_title(chart):
    chart.has_title = False
    atd = chart._chartSpace.find(qn("c:chart")).find(qn("c:autoTitleDeleted"))
    if atd is not None:
        atd.set("val", "1")


def fmt_int(n) -> str:
    return f"{int(n):,}"


# ── deck scaffolding ────────────────────────────────────────────────────────
def open_template():
    prs = Presentation(str(TEMPLATE))
    layout = prs.slides[18].slide_layout  # AMJ slide 19 uses the DEFAULT layout
    sld_ids = prs.slides._sldIdLst
    for sld_id in list(sld_ids):
        prs.part.drop_rel(sld_id.rId)
        sld_ids.remove(sld_id)
    return prs, layout


def new_slide(prs, layout):
    s = prs.slides.add_slide(layout)
    for ph in list(s.placeholders):
        ph._element.getparent().remove(ph._element)
    return s


# ── slides ──────────────────────────────────────────────────────────────────
def slide_weekly(prs, layout):
    s = new_slide(prs, layout)
    title(s, "WEEKLY SITE USERS VS SIGN-UPS (L24M)")
    rows = csv_rows("weekly_signups_users_l24m_oct24_sep26.csv")
    cd = CategoryChartData()
    cats = [date.fromisoformat(r["week_start"]) for r in rows]
    cd.categories = [f"{d.day}/{d.month}/{d.strftime('%y')}" for d in cats]
    cd.add_series("Weekly Site Users", [int(r["users"]) for r in rows])
    cd.add_series("Weekly Sign-ups", [int(r["signups"]) for r in rows])
    gx, gy, gw, gh = 0.4, 1.55, 12.55, 5.2
    gf = s.shapes.add_chart(XL_CHART_TYPE.LINE, Inches(gx), Inches(gy), Inches(gw), Inches(gh), cd)
    ch = gf.chart
    style_chart(ch, 9)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.legend.font.size = Pt(11)
    for ser, col, wdt in ((ch.series[0], PINK, 2.25), (ch.series[1], PURPLE, 2.25)):
        ser.format.line.color.rgb = col
        ser.format.line.width = Pt(wdt)
        ser.smooth = False
        ser.marker.style = None
        ser.marker.format.fill.background()
        ser.marker.format.line.fill.background()
    va = ch.value_axis
    va.maximum_scale, va.minimum_scale, va.major_unit = 1600, 0, 200
    axis_quiet(va, gridlines=True, size=9)
    va.tick_labels.number_format = "#,##0"
    va.tick_labels.number_format_is_linked = False
    ca = ch.category_axis
    axis_quiet(ca, size=8)
    ca_el = ca._element
    for tag, val in (("c:tickLblSkip", "2"), ("c:tickMarkSkip", "2")):
        el = ca_el.find(qn(tag))
        if el is None:
            el = etree.SubElement(ca_el, qn(tag))
        el.set("val", val)
    txPr = ca._element.find(qn("c:txPr"))
    if txPr is not None:
        txPr.find(qn("a:bodyPr")).set("rot", "-2700000")
    # fix plot-area geometry so callouts can be placed against the data
    px, py, pw, ph = 0.055, 0.1, 0.935, 0.72
    set_plot_layout(ch, px, py, pw, ph)

    n = len(rows)
    def pt(i, v):
        x = gx + gw * (px + pw * (i + 0.5) / n)
        y = gy + gh * (py + ph * (1 - v / 1600))
        return x, y

    jas_start = next(i for i, d in enumerate(cats) if d >= date(2026, 6, 29))
    split = next(i for i, d in enumerate(cats) if d >= date(2025, 9, 29))
    for i, lab in ((0, "OND24 – JAS25"), (split, "OND25 – JAS26")):
        x, _ = pt(i, 0)
        text(s, x, 1.42, 2.6, 0.3, lab, size=13, bold=True, italic=True, color=INK)
    # JAS26 shading
    x0, _ = pt(jas_start - 0.5, 0)
    x1, _ = pt(n - 0.5, 0)
    shade = rect(s, x0, gy + gh * py, x1 - x0, gh * ph, fill=RGBColor(0xEE, 0xF4, 0xFB), line=None, rounded=False)
    s.shapes._spTree.remove(shade._element)
    s.shapes._spTree.insert(2, shade._element)
    text(s, x0 + 0.05, gy + gh * (py + ph) - 0.3, x1 - x0 - 0.1, 0.25, "JAS26", size=10, bold=True, color=NAVY, align=PP_ALIGN.CENTER)

    # numbered markers on the JAS peaks, keyed in the empty space above 2025's lower weeks
    markers = [
        (date(2026, 6, 29), "TMR Clinical Audit eDM 01/07", 0.35),
        (date(2026, 8, 17), "MT Hydralyte Clinical Bites eDM 20/08", 0.35),
        (date(2026, 8, 24), "TMR Hydralyte Clinical Bites eDM 25/08", 0.75),
        (date(2026, 8, 31), "AJP FESS Solus eDM 02/09", 1.15),
        (date(2026, 9, 7), "IQVIA + Insight Zaditen eDMs 08/09", -0.05),
        (date(2026, 9, 14), "TMR + MT FESS Clinical Bites eDMs 16–17/09", 0.35),
    ]
    by_date = {d: i for i, d in enumerate(cats)}
    teal = RGBColor(0x40, 0xB0, 0xC8)
    for n_, (d, lab, lift) in enumerate(markers, 1):
        i = by_date[d]
        x, y = pt(i, int(rows[i]["users"]))
        my = y - lift - 0.14 if lift > 0 else y - 0.14
        mx = x + 0.12 if lift <= 0 else x - 0.14
        if lift > 0:
            conn = s.shapes.add_connector(1, Inches(x), Inches(y), Inches(x), Inches(my + 0.28))
            conn.line.color.rgb = teal
            conn.line.width = Pt(1)
        dot = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(mx), Inches(my), Inches(0.28), Inches(0.28))
        dot.fill.solid()
        dot.fill.fore_color.rgb = NAVY
        dot.line.color.rgb = WHITE
        dot.line.width = Pt(1)
        text(s, mx, my, 0.28, 0.28, str(n_), size=9, bold=True, color=WHITE, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
    kx, ky = 1.65, 2.3
    rect(s, kx, ky, 4.6, 0.3 + 0.27 * len(markers), fill=WHITE, line=teal, line_w=1.25, rounded=False)
    text(s, kx + 0.15, ky + 0.07, 4.3, 0.25, "JAS26 SENDS", size=9.5, bold=True, color=GREY, spacing=150)
    for n_, (d, lab, lift) in enumerate(markers, 1):
        yy = ky + 0.33 + 0.27 * (n_ - 1)
        dot = s.shapes.add_shape(MSO_SHAPE.OVAL, Inches(kx + 0.15), Inches(yy), Inches(0.22), Inches(0.22))
        dot.fill.solid()
        dot.fill.fore_color.rgb = NAVY
        dot.line.fill.background()
        text(s, kx + 0.15, yy, 0.22, 0.22, str(n_), size=8, bold=True, color=WHITE, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        text(s, kx + 0.45, yy, 4.0, 0.22, lab, size=9.5, color=INK, anchor=MSO_ANCHOR.MIDDLE)
    source(s, "Source: WordPress DB (sign-ups, excludes internal-staff and test accounts) + GA4 property 306115293 (totalUsers, summed per day). "
              "Weekly buckets Mon–Sun, Australia/Sydney timezone, 30 Sep 2024 – 27 Sep 2026 (104 weeks); on Sydney time JAS has 96 sign-ups vs 95 on the database slide (UTC). "
              "Callouts for earlier quarters are on the AMJ26 version of this chart.")


def slide_db(prs, layout):
    s = new_slide(prs, layout)
    title(s, "JAS 26 added +95 new registrations, taking FY27 to 249 – 71% of the FY27 KPI with half the year to go")
    cd = CategoryChartData()
    cd.categories = ["FY26 (full year)", "FY27 YTD (AMJ + JAS)"]
    cd.add_series("Starting database", (975, 1307))
    cd.add_series("New registrations (FY26 / AMJ 26)", (332, 154))
    cd.add_series("New registrations (JAS 26)", (None, 95))
    gx, gy, gw, gh = 0.57, 1.5, 7.4, 4.95
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_STACKED, Inches(gx), Inches(gy), Inches(gw), Inches(gh), cd)
    ch = gf.chart
    px, py, pw, ph = 0.09, 0.12, 0.89, 0.76
    set_plot_layout(ch, px, py, pw, ph)
    style_chart(ch, 11)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 90
    ch.plots[0].overlap = 100
    for ser, col in zip(ch.series, (LIGHTBAR, NAVY, TEAL)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "#,##0;;"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.CENTER
        ser.data_labels.font.size = Pt(13)
        ser.data_labels.font.bold = True
        ser.data_labels.font.color.rgb = INK if col == LIGHTBAR else WHITE
    ch.series[1].name  # keep
    va = ch.value_axis
    va.maximum_scale, va.minimum_scale, va.major_unit = 1800, 0, 200
    axis_quiet(va, gridlines=True)
    axis_quiet(ch.category_axis, size=11)
    for i, (tot, lab, col) in enumerate(((1307, "Total: 1,307", GREY), (1556, "Total: 1,555*", NAVY))):
        cx = gx + gw * (px + pw * (i + 0.5) / 2)
        cy = gy + gh * (py + ph * (1 - tot / 1800))
        text(s, cx - 1.2, cy - 0.4, 2.4, 0.35, lab, size=14, bold=True, color=col, align=PP_ALIGN.CENTER)
    # legend override: series 2 label reads "AMJ" for the FY26 bar too, so clarify under the chart
    text(s, 0.6, 6.47, 7.3, 0.3, "FY26 bar: 332 new registrations across the full year.  FY27 bar: 154 in AMJ + 95 in JAS.",
         size=10, italic=True, color=TEAL, align=PP_ALIGN.CENTER)
    stat_card(s, 8.37, 1.35, 4.4, 1.6, "Total database", "1,555",
              "+19% on FY26 close (1,307). One account removed since AMJ (1,461 + 95 − 1)")
    stat_card(s, 8.37, 3.1, 4.4, 1.6, "New registrations, JAS 26", "95",
              "vs 63 in JAS 25 (+51% YoY); quieter than AMJ (154), which had the ADG Audit eDM", value_color=TEAL)
    stat_card(s, 8.37, 4.85, 4.4, 1.6, "% of FY27 KPI (year to date)", "71%",
              "249 new registrations vs KPI of 349 for FY27; 100 to go in OND + JFM", value_color=TEAL)
    source(s, "Source: WordPress DB (prod) excluding internal-staff and test accounts. FY26 = Apr 2025–Mar 2026 (975 + 332 new). "
              "FY27 YTD = Apr–Sep 2026 (1,307 + 154 AMJ + 95 JAS). *1,307 + 154 + 95 = 1,556; 1,555 at 30 Sep after one account was removed. JAS 25 = Jul–Sep 2025.")


def slide_traffic(prs, layout):
    s = new_slide(prs, layout)
    title(s, "Active Users and Sessions more than doubled vs JAS 25, driven by a busy eDM quarter – "
             "but Engaged Time fell as new audiences skimmed")
    subtitle(s, "Engaged time fell because more of this quarter's visitors were to quick look-up landing points. Nine eDMs sent new audiences to single resources, "
                "like eye-care professionals opening the Zaditen chart, and with no Clinical Audit promotion there were fewer long course sessions.", size=12)
    rows = {r["period"]: r for r in csv_rows("traffic_yoy_fy27q2.csv")}
    py, pa, pj = rows["FY26 Q2"], rows["FY27 Q1"], rows["FY27 Q2"]
    labels = ("JAS25", "AMJ26", "JAS26")
    cd = CategoryChartData()
    cd.categories = ["Active Users", "Sessions", "Pageviews"]
    for lab, r in zip(labels, (py, pa, pj)):
        cd.add_series(lab, (int(r["active_users"]), int(r["sessions"]), int(r["pageviews"])))
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(0.4), Inches(1.95), Inches(7.9), Inches(3.0), cd)
    ch = gf.chart
    style_chart(ch, 10)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 60
    ch.plots[0].overlap = -10
    for ser, col in zip(ch.series, (LIGHTBAR, SKY, NAVY)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "#,##0"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
        ser.data_labels.font.size = Pt(10)
        ser.data_labels.font.bold = col == NAVY
    va = ch.value_axis
    va.maximum_scale, va.minimum_scale, va.major_unit = 14000, 0, 2000
    axis_quiet(va, gridlines=True)
    va.tick_labels.number_format = "#,##0"
    va.tick_labels.number_format_is_linked = False
    axis_quiet(ch.category_axis, size=10.5)

    cd2 = CategoryChartData()
    cd2.categories = ["Avg Engaged Time / User"]
    secs = [float(r["avg_engaged_time_per_user_s"]) for r in (py, pa, pj)]
    for lab, v in zip(labels, secs):
        cd2.add_series(lab, (v,))
    gf2 = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(8.55), Inches(1.95), Inches(4.4), Inches(3.0), cd2)
    ch2 = gf2.chart
    style_chart(ch2, 10)
    ch2.has_legend = True
    ch2.legend.position = XL_LEGEND_POSITION.TOP
    ch2.legend.include_in_layout = False
    ch2.plots[0].gap_width = 40
    ch2.plots[0].overlap = -10
    for ser, col, r in zip(ch2.series, (LIGHTBAR, SKY, NAVY), (py, pa, pj)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        dl = ser.points[0].data_label
        dl.has_text_frame = True
        dl.text_frame.text = r["avg_engaged_time_per_user"]
        dl.position = XL_LABEL_POSITION.OUTSIDE_END
        dl.text_frame.paragraphs[0].runs[0].font.size = Pt(10)
        dl.text_frame.paragraphs[0].runs[0].font.bold = col == NAVY
    va2 = ch2.value_axis
    va2.maximum_scale, va2.minimum_scale, va2.major_unit = 300, 0, 50
    axis_quiet(va2, gridlines=True)
    axis_quiet(ch2.category_axis, size=10.5)

    yoy = rows["YoY change"]
    qoq = rows["QoQ change"]
    def pctc(v):
        return TEAL if v.startswith("+") else AMBER
    trs = [["", "JAS25", "AMJ26", "JAS26", "YoY (vs JAS25)", "QoQ (vs AMJ26)"]]
    keys = [("Active Users", "active_users"), ("Sessions", "sessions"), ("Pageviews", "pageviews"),
            ("Avg Engaged Time / User", "avg_engaged_time_per_user_s")]
    colors = {}
    for i, (lab, k) in enumerate(keys, 1):
        vals = [r["avg_engaged_time_per_user"] if k.endswith("_s") else fmt_int(r[k]) for r in (py, pa, pj)]
        y_, q_ = yoy[k].replace("-", "−"), qoq[k].replace("-", "−")
        trs.append([lab, *vals, y_, q_])
        colors[(i, 4)] = pctc(yoy[k])
        colors[(i, 5)] = pctc(qoq[k])
    table(s, 0.55, 5.02, 12.25, [3.2, 1.8, 1.8, 1.8, 1.8, 1.8], trs, row_h=0.3, size=10.5, header_size=10.5,
          bold_cols=(0, 4, 5), color_cells=colors)
    source(s, "Source: GA4 property 306115293, traffic_engagement_yoy.py — activeUsers, sessions, screenPageViews, userEngagementDuration ÷ activeUsers. "
              "Whole-property totals (includes ~3% staging/test-host sessions in JAS26, ~1% in JAS25). JAS26 also includes automated "
              "email-security link checks from the IQVIA Zaditen send (est. 350–650 of the 3,605 users).")


def slide_sources(prs, layout):
    s = new_slide(prs, layout)
    title(s, "TRAFFIC SOURCE ANALYSIS – email drove 72% of JAS26 traffic, now pointing mostly to "
             "Resources and the new Tools & Videos content")
    ch = {r["channel_group"]: r for r in csv_rows("jas26_channel_split.csv")}
    label(s, 0.42, 1.38, 6.2, "TRAFFIC SOURCES (JAS26)")
    cards = [("Email", "Publisher eDMs + Mailchimp", ch["Email"], True),
             ("Direct", "Includes email opens with stripped UTMs", ch["Direct"], False),
             ("Other", "Organic, referral, social, paid, AI", ch["Other (organic/referral/social/paid)"], False),
             ("Unassigned", "GA4 couldn't attribute", ch["Unassigned"], False)]
    y = 1.75
    for name, sub, r, hero in cards:
        rect(s, 0.42, y, 5.95, 1.12, fill=WHITE, line=NAVY if hero else LINE, line_w=1.5 if hero else 0.75)
        lines = [[(name, {"size": 14, "bold": True})], [(sub, {"size": 10, "italic": True, "color": GREY})],
                 [(f"{fmt_int(r['sessions'])} sessions", {"size": 14, "bold": True, "color": NAVY})]]
        if name != "Unassigned":
            lines.append([(f"from {fmt_int(r['users'])} unique users", {"size": 10, "color": GREY})])
        text(s, 0.62, y + 0.1, 4.0, 0.95, lines, size=12)
        pill = rect(s, 5.05, y + 0.3, 1.0, 0.52, fill=NAVY if hero else MUTED, line=None, radius=0.08)
        text(s, 5.05, y + 0.3, 1.0, 0.52, f"{float(r['pct_of_total_sessions'].rstrip('%')):.0f}%",
             size=14, bold=True, color=WHITE, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        y += 1.24
    arr = s.shapes.add_shape(MSO_SHAPE.RIGHT_ARROW, Inches(6.5), Inches(1.95), Inches(0.75), Inches(0.62))
    arr.fill.solid()
    arr.fill.fore_color.rgb = BLUE
    arr.line.fill.background()
    text(s, 6.4, 2.62, 1.0, 0.3, "EMAIL TRAFFIC", size=9, italic=True, color=GREY, align=PP_ALIGN.CENTER)

    label(s, 7.45, 1.38, 5.5, "SECTION ACTIVITY — RANKED BY EMAIL SHARE")
    names = {"Resources": "Resources/Factsheets", "Tools & Videos": "Tools & Videos (incl. Clinical Bites)",
             "Articles & blog": "Articles & Blog", "The Course (module + MCA)": "Anal Fissure Clinical Audit",
             "Sample ordering": "Sample Ordering", "Login & Signup": "Login & Signup"}
    amj = {r["section"]: r for r in csv_rows("fy27q1_section_touch_newdefs.csv")}
    secs = [r for r in csv_rows("fy27q2_section_touch.csv") if r["section"] in names]
    secs.sort(key=lambda r: -float(r["email_share_of_section_pct"]))
    y = 1.75
    for r in secs:
        hero = float(r["email_share_of_section_pct"]) >= 70
        rect(s, 7.45, y, 5.45, 0.8, fill=WHITE, line=NAVY if hero else LINE, line_w=1.5 if hero else 0.75)
        a = amj[r["section"]]
        text(s, 7.62, y + 0.06, 4.1, 0.7, [
            [(names[r["section"]], {"size": 12.5, "bold": True, "color": NAVY})],
            [(f"{fmt_int(r['email_sessions'])} of {fmt_int(r['total_sessions'])} sessions from email", {"size": 10})],
            [(f"{fmt_int(r['email_users'])} of {fmt_int(r['total_users'])} unique users · AMJ26: {fmt_int(a['total_sessions'])} sessions",
              {"size": 9, "italic": True, "color": GREY})]], size=10)
        rect(s, 11.95, y + 0.17, 0.8, 0.46, fill=NAVY if hero else MUTED, line=None, radius=0.08)
        text(s, 11.95, y + 0.17, 0.8, 0.46, f"{float(r['email_share_of_section_pct']):.0f}%", size=13, bold=True,
             color=WHITE, align=PP_ALIGN.CENTER, anchor=MSO_ANCHOR.MIDDLE)
        y += 0.86
    source(s, "Source: GA4 property 306115293, JAS26 (Jul–Sep 2026). Section-touch: sessions touching any page in a section count for it; multi-section sessions "
              "counted in each. Section definitions widened this quarter to include the new Clinical Bites/video pages (Tools & Videos) and tracked chart/leaflet "
              "links (Resources); AMJ26 comparison re-run on the same definitions. Homepage/brand/misc excluded from ranking (3,726 sessions, 75% email).")


def slide_edm(prs, layout):
    s = new_slide(prs, layout)
    title(s, "EDM RECONCILIATION – JAS26 publisher results vs Google Analytics capture")
    rows = {r["send"]: r for r in csv_rows("jas26_edm_reconciliation_by_send.csv")}
    grp = {r["group"]: r for r in csv_rows("jas26_edm_signups_by_campaign.csv")}
    X = "xxx"

    def g(label_):
        return rows[label_]

    spec = [
        ("MT Solus eDM — FESS Clinical Bites", "17 Sep", "MT Solus eDM - FESS Clinical Bites", "FESS Clinical Bites"),
        ("TMR Solus eDM — FESS Clinical Bites", "16 Sep", "TMR Solus eDM - FESS Clinical Bites", None),
        ("IQVIA Solus eDM — Zaditen (GP + ophthal)", "8 Sep", "IQVIA Solus eDM - Zaditen (GP + ophthal)", "Zaditen"),
        ("Insight Solus eDM — Zaditen", "8 Sep", "Insight Solus eDM - Zaditen", None),
        ("mivision newsletter placements — Zaditen", "1 & 8 Sep", "mivision newsletter banners - Zaditen", None),
        ("AJP Solus eDM — FESS", "2 Sep", "AJP Solus eDM - FESS", "FESS (AJP)"),
        ("TMR Solus eDM — Hydralyte Clinical Bites", "25 Aug", "TMR Solus eDM - Hydralyte Clinical Bites", "Hydralyte Clinical Bites"),
        ("MT Solus eDM — Hydralyte Clinical Bites", "20 Aug", "MT Solus eDM - Hydralyte Clinical Bites", None),
        ("TMR Solus eDM — Clinical Audit", "1 Jul", "TMR Solus eDM - Clinical Audit", "Rectogesic Clinical Audit"),
    ]
    hdr = ["eDM", "Sent", "Publisher Total Clicks", "Publisher Unique Clicks", "GA4 sessions", "Click capture",
           "Est. sign-ups", "Conversion rate", "Media Cost", "Cost per Conversion"]
    trs = [hdr]
    colors, fills = {}, {}
    group_rows = {}
    for i, (lab, sent, key, group) in enumerate(spec, 1):
        r = g(key)
        tot = r["publisher_total_clicks"] or X
        uniq = r["publisher_unique_clicks"] or X
        if lab.startswith("IQVIA"):
            tot = "n/r"
        if lab.startswith(("Insight", "mivision")):
            uniq = "n/r"
        cap = r["click_capture_pct"] or ("97%*" if lab.startswith("IQVIA") else "–")
        row = [lab, sent, fmt_int(tot) if tot.isdigit() else tot, fmt_int(uniq) if uniq.isdigit() else uniq,
               fmt_int(r["ga4_sessions"]), cap, "", "", X, X]
        if lab.startswith("mivision"):
            row[2], row[5] = row[2] + "†", row[5] + "†"
        if group:
            gr = grp[group]
            row[6] = str(gr["est_signups"])
            row[7] = gr["conversion_rate_vs_unique_clicks"] or "–"
            group_rows[group] = i
        trs.append(row)
        for c in (4, 5, 6, 7):
            fills[(i, c)] = RGBColor(0xDB, 0xEE, 0xF4)
        for c in range(len(hdr)):
            if trs[i][c] == X:
                colors[(i, c)] = RED
    total_sessions = sum(int(rows[k[2]]["ga4_sessions"]) for k in spec)
    trs.append(["TOTAL JAS", "", "", "", fmt_int(total_sessions), "", "56", "", X, X])
    n = len(trs)
    tbl = table(s, 0.42, 1.3, 12.5, [3.35, 0.75, 1.05, 1.05, 0.95, 0.9, 0.9, 1.0, 0.95, 1.05], trs, row_h=0.33,
                header_h=0.5, size=10, header_size=10,
                align=[PP_ALIGN.LEFT] + [PP_ALIGN.CENTER] * 9, bold_cols=(4, 5, 6, 7), color_cells=colors, fills=fills)
    # merge sign-up/conversion cells across sends sharing one estimate
    for top, span in ((1, 2), (3, 3), (7, 2)):
        for c in (6, 7):
            tbl.cell(top, c).merge(tbl.cell(top + span - 1, c))
    for c in range(len(hdr)):
        cell = tbl.cell(n - 1, c)
        cell.fill.solid()
        cell.fill.fore_color.rgb = NAVY
        for p in cell.text_frame.paragraphs:
            for r_ in p.runs:
                r_.font.color.rgb = WHITE
                r_.font.bold = True
    text(s, 0.45, 5.26, 12.4, 0.4, [
        "*IQVIA reports unique clicks only (GP + ophthalmology lists, incl. reminder wave); capture = GA4 sessions ÷ unique clicks.  "
        "MT 'unique clicks' = unique clickers (people); TMR = unique clicks summed per link, so the two aren't directly comparable.  "
        "Sign-ups estimated per campaign (merged cells) because sends overlapped.  †mivision clicks are all clicks in 4 newsletter sends (1 & 8 Sep, optometry + ophthalmology), not only on Zaditen."],
        size=8.5, italic=True, color=GREY)
    label(s, 0.42, 5.68, 6, "WHAT THIS TELLS US")
    rect(s, 0.42, 5.97, 12.5, 0.84)
    bullets(s, 0.65, 6.03, 12.1, 0.78, [
        "Of the 95 new registrations this QTR, ~56 (59%) can be attributed to eDM activity — the Clinical Audit eDM (1 Jul) again converted best: 31 sign-ups, 8.4% of unique clicks",
        "Zaditen eDMs drove the most traffic (~1,700 sessions, 97% click capture) but almost no sign-ups: an eye-care audience opening a chart, not registering",
        "Video-series eDMs converted 2.2% (Hydralyte) and 0.7% (FESS) of unique clicks",
    ], size=10.5, gap=2)
    source(s, "Source: publisher reports + GA4, JAS26 (send day to 30 Sep). Sign-ups: max(GA4-tagged sign-ups, DB spike), DB spike = 14-day post-send sign-ups minus a quiet-period "
              "baseline (6 Jul–16 Aug, 0.52/day). Conversion rate calculated off unique clicks; on MT's per-link unique clicks (537 / 419) Hydralyte converts 1.6% and FESS 0.5%. "
              "Sessions run to 30 Sep, so exceed the CAPH0126 / CAPH0122 toplines already circulated (shorter windows).")


def _bites_slide(prs, layout, series, title_t, sub_t, launch, peak_note):
    s = new_slide(prs, layout)
    title(s, title_t)
    subtitle(s, sub_t, italic=True, size=12)
    ser = next(r for r in csv_rows("jas26_clinical_bites_series.csv") if r["series"] == series)
    eps = [r for r in csv_rows("jas26_clinical_bites_episodes.csv") if r["series"] == series]
    paths = [r for r in csv_rows("jas26_clinical_bites_paths.csv") if r["series"] == series]
    daily = {r["date"]: int(r["video_plays"]) for r in csv_rows("jas26_clinical_bites_daily.csv") if r["series"] == series}
    srcs = [r for r in csv_rows("jas26_clinical_bites_sources.csv") if r["series"] == series]

    stat_card(s, 0.42, 1.95, 2.55, 1.3, "GPs who watched", ser["distinct_viewers_any_episode"],
              f"{ser['landing_page_users']} visited the series landing page", value_size=26)
    stat_card(s, 3.12, 1.95, 2.55, 1.3, "Episodes per viewer", f"{float(ser['episodes_started_per_viewer']):.1f}",
              f"of {ser['episodes']} episodes; most in one sitting", value_size=26)
    stat_card(s, 0.42, 3.38, 2.55, 1.3, "Finished an episode", ser["distinct_viewers_finished_any_episode"],
              f"of the {ser['distinct_viewers_any_episode']} who pressed play", value_color=TEAL, value_size=26)
    email = sum(int(r["sessions"]) for r in srcs if r["medium"] == "email")
    tot = sum(int(r["sessions"]) for r in srcs)
    stat_card(s, 3.12, 3.38, 2.55, 1.3, "Visits from eDMs", f"{email / tot * 100:.0f}%",
              f"{email} of {tot} visits to the series pages", value_color=TEAL, value_size=26)

    label(s, 0.42, 4.82, 5.3, "EPISODES")
    short = {"why-sick-day-planning-is-important-in-diabetes": "Why sick day planning matters",
             "what-goes-in-a-sick-day-plan-for-people-with-diabetes": "What goes in a sick day plan",
             "medication-management-during-sick-days": "Medication management",
             "dehydration-during-sick-days-the-role-of-ors": "Dehydration and the role of ORS",
             "preparing-a-sick-day-management-kit": "Preparing a sick day kit",
             "not-another-sinus-infection": "Not another sinus infection",
             "are-steroids-safe-for-my-child": "Are steroids safe for my child?",
             "expecting-and-congested": "Expecting and congested"}
    trs = [["Episode", "Played", "Finished", "Finish rate"]]
    for r in eps:
        trs.append([f"Ep {r['episode']}: {short[r['page'].strip('/').split('/')[-1]]}", r["viewers_pressed_play"],
                    r["viewers_watched_to_end"], r["completion_rate"]])
    table(s, 0.42, 5.12, 5.25, [3.0, 0.7, 0.75, 0.8], trs, row_h=0.26, header_h=0.28, size=9, header_size=9)

    label(s, 6.0, 1.98, 6.9, "DAILY VIDEO PLAYS SINCE LAUNCH")
    d0, d1 = launch, date(2026, 9, 30)
    days = [d0 + timedelta(days=i) for i in range((d1 - d0).days + 1)]
    cd = CategoryChartData()
    cd.categories = [f"{d.day}/{d.month}" for d in days]
    cd.add_series("Video plays", [daily.get(d.isoformat(), 0) for d in days])
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(5.95), Inches(2.25), Inches(6.95), Inches(2.3), cd)
    ch = gf.chart
    style_chart(ch, 8)
    no_title(ch)
    ch.has_legend = False
    ch.plots[0].gap_width = 40
    ch.series[0].format.fill.solid()
    ch.series[0].format.fill.fore_color.rgb = NAVY
    axis_quiet(ch.value_axis, gridlines=True, size=8)
    axis_quiet(ch.category_axis, size=8)
    ca_el = ch.category_axis._element
    for tag, val in (("c:tickLblSkip", "3"), ("c:tickMarkSkip", "3")):
        etree.SubElement(ca_el, qn(tag)).set("val", val)
    text(s, 6.0, 4.55, 6.9, 0.3, peak_note, size=9.5, italic=True, color=TEAL)

    own_edm = {"Hydralyte - Diabetes Sick Day Management": {"CAPH0126": None},
               "FESS - Allergic Rhinitis Rounds": {"CAPH0122": None, "9445dc6c42-EMAIL_CAMPAIGN_2026_08_25_07_42": "AJP"}}[series]
    pub = {"MedicineToday": "Medicine Today", "TheMedicalRepublic": "The Medical Republic"}
    groups = {}
    for r in srcs:
        n = int(r["sessions"])
        if r["campaign"] in own_edm:
            key = f"eDM: {own_edm[r['campaign']] or pub.get(r['source'], r['source'])}"
        elif r["medium"] == "email":
            key = "Other eDMs (other brands, welcome email)"
        elif r["medium"] in ("qr", "print"):
            key = "QR codes on print"
        elif r["medium"] == "(none)":
            key = "Direct (untagged link or bookmark)"
        else:
            key = "Other (search, referral, banner)"
        groups[key] = groups.get(key, 0) + n
    label(s, 6.0, 4.85, 3.6, f"{tot} VISITS, BY SOURCE")
    order = sorted(groups.items(), key=lambda kv: (not kv[0].startswith("eDM:"), kv[0].startswith("Other ("), -kv[1]))
    trs2 = [["Source", "Visits"]] + [[k, str(v)] for k, v in order]
    table(s, 6.0, 5.12, 3.55, [2.75, 0.8], trs2, row_h=0.2, header_h=0.24, size=8.5, header_size=8.5)

    label(s, 9.8, 4.85, 3.1, "ONWARD CLICKS")
    dls = {}
    for r in paths:
        if r["what"].startswith("Resource downloaded"):
            dls[r["came_from"]] = dls.get(r["came_from"], 0) + int(r["page_views"])
    nice = {"CAPH0089-Hydralyte-APP-2026_Sick-Days-LB_2pp-A4_V3.2.pdf": "Sick-day leave-behind PDF",
            "CAPH0051_Hydralyte-Patient-Leaflet_A4_2pp_V3.0_HR.pdf": "Hydralyte patient leaflet",
            "NAC035-AR-Chart-WEB.pdf": "AR treatments chart",
            "caph0109-fess-patient-leaflet.pdf": "FESS patient leaflet"}
    audit = {"Hydralyte - Diabetes Sick Day Management": 11, "FESS - Allergic Rhinitis Rounds": 13}[series]
    trs3 = [["Where to", "Clicks"]] + [[nice.get(k, k), str(v)] for k, v in sorted(dls.items(), key=lambda x: -x[1])]
    trs3.append(["Clinical Audit pop-up clicks", str(audit)])
    table(s, 9.8, 5.12, 3.1, [2.3, 0.8], trs3, row_h=0.2, header_h=0.24, size=8.5, header_size=8.5)
    source(s, "Source: GA4, JAS26 (Vimeo player events on the episode pages: video_start / video_complete). Visits = GA4 sessions that touched the series landing "
              "or episode pages, by the tracking tag on the link that started the visit.")
    return s


def slide_bites(prs, layout):
    _bites_slide(prs, layout, "Hydralyte - Diabetes Sick Day Management",
                 "HYDRALYTE CLINICAL BITES – 71 GPs watched the diabetes sick-day series, averaging 2.3 episodes each, almost all on eDM day",
                 "Viewing is driven by the eDMs and happens in one sitting: 73 plays by 30 GPs on 20 Aug (MT send), a second bump on 26 Aug (TMR), then near zero.",
                 date(2026, 8, 17), "Peaks: 20 Aug MT eDM (73 plays, 30 viewers) · 25–26 Aug TMR eDM (44 plays) · little viewing outside send days")
    _bites_slide(prs, layout, "FESS - Allergic Rhinitis Rounds",
                 "FESS CLINICAL BITES – 59 GPs watched Allergic Rhinitis Rounds; Ep 1 carried the series",
                 "Most viewers watched Ep 1 only (1.5 episodes per viewer); 146 of 180 visits came from the three FESS eDMs (Medicine Today, TMR, AJP).",
                 date(2026, 9, 1), "Peaks: 16 Sep TMR eDM (31 plays) · 17 Sep MT eDM (40 plays) · fades within 5 days")


def slide_bites_timing(prs, layout):
    s = new_slide(prs, layout)
    title(s, "CLINICAL BITES VIEWING – GPs watch when the eDM lands, at lunch and after hours; 4 in 10 plays are on a phone")
    rows = csv_rows("jas26_clinical_bites_timing.csv")
    series = [("Hydralyte - Diabetes Sick Day Management", "Hydralyte series", NAVY),
              ("FESS - Allergic Rhinitis Rounds", "FESS series", TEAL2)]
    def get(ser, dim):
        return [r for r in rows if r["series"] == ser and r["dimension"] == dim]

    label(s, 0.42, 1.5, 7.6, "SHARE OF VIDEO PLAYS BY TIME OF DAY")
    blocks = [r["bucket"] for r in get(series[0][0], "time of day")]
    cd = CategoryChartData()
    cd.categories = [b.replace("-", "–") for b in blocks]
    for key, name, _ in series:
        cd.add_series(name, [int(r["pct"]) / 100 for r in get(key, "time of day")])
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(0.42), Inches(1.8), Inches(7.7), Inches(4.85), cd)
    ch = gf.chart
    style_chart(ch, 10)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 55
    ch.plots[0].overlap = -5
    for ser, (_, _, col) in zip(ch.series, series):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "0%"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
        ser.data_labels.font.size = Pt(9)
    va = ch.value_axis
    va.maximum_scale, va.minimum_scale, va.major_unit = 0.30, 0, 0.05
    va.tick_labels.number_format = "0%"
    va.tick_labels.number_format_is_linked = False
    axis_quiet(va, gridlines=True)
    axis_quiet(ch.category_axis, size=10)

    label(s, 8.45, 1.5, 4.5, "DEVICE")
    cd2 = CategoryChartData()
    cd2.categories = [name for _, name, _ in series]
    dev = {key: {r["bucket"]: int(r["pct"]) / 100 for r in get(key, "device")} for key, _, _ in series}
    cd2.add_series("Desktop", [dev[k].get("desktop", 0) + dev[k].get("tablet", 0) for k, _, _ in series])
    cd2.add_series("Mobile", [dev[k].get("mobile", 0) for k, _, _ in series])
    gf2 = s.shapes.add_chart(XL_CHART_TYPE.BAR_STACKED_100, Inches(8.4), Inches(1.75), Inches(4.5), Inches(1.6), cd2)
    ch2 = gf2.chart
    style_chart(ch2, 10)
    ch2.has_legend = True
    ch2.legend.position = XL_LEGEND_POSITION.TOP
    ch2.legend.include_in_layout = False
    ch2.plots[0].gap_width = 45
    ch2.plots[0].overlap = 100
    for ser, col in zip(ch2.series, (LIGHTBAR, PINK)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "0%"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.CENTER
        ser.data_labels.font.size = Pt(10)
        ser.data_labels.font.bold = True
        ser.data_labels.font.color.rgb = INK if col == LIGHTBAR else WHITE
    ch2.category_axis.reverse_order = True
    axis_quiet(ch2.category_axis, size=9.5)
    ch2.value_axis.visible = False
    ch2.value_axis.has_major_gridlines = False

    rect(s, 8.45, 3.6, 4.45, 2.2)
    text(s, 8.65, 3.72, 4.1, 0.3, "WHAT THIS TELLS US", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 8.65, 4.1, 4.1, 1.65, [
        "Biggest viewing window is late morning, when the eDMs land",
        "Almost 3 in 10 plays happen after 5pm",
        "FESS also peaks at lunchtime",
        "~40% of plays are on a phone: keep episodes short",
    ], size=11, gap=6)
    source(s, "Source: GA4 video_start events on the episode pages, JAS26; hours in property time (Australia/Sydney). n = 194 Hydralyte plays, 97 FESS plays, "
              "so directional. Desktop includes 1 tablet play (FESS).")


def slide_quiz(prs, layout):
    s = new_slide(prs, layout)
    title(s, "GUESS THE GLUCOSE QUIZ – 48 GPs took a look, engaging on par with our articles; "
             "the quiz itself doesn't record answers yet")
    rows = {r["item"]: r for r in csv_rows("jas26_glucose_quiz.csv")}
    label(s, 0.42, 1.55, 6, "ENGAGEMENT — GUESS THE GLUCOSE CHALLENGE")
    cd = CategoryChartData()
    cd.categories = ["Landed on the quiz", "Stayed 15s+", "Scrolled to the bottom"]
    cd.add_series("People", (48, 38, 10))
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_CLUSTERED, Inches(0.42), Inches(1.9), Inches(6.2), Inches(2.6), cd)
    ch = gf.chart
    style_chart(ch, 11)
    no_title(ch)
    ch.has_legend = False
    ch.plots[0].gap_width = 45
    ser = ch.series[0]
    ser.format.fill.solid()
    ser.format.fill.fore_color.rgb = BLUE
    ser.data_labels.show_value = True
    ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
    ser.data_labels.font.bold = True
    ch.category_axis.reverse_order = True
    axis_quiet(ch.category_axis, size=11)
    axis_quiet(ch.value_axis, gridlines=True, size=9)
    ch.value_axis.maximum_scale = 60
    stat_card(s, 0.42, 4.75, 3.0, 1.85, "Avg engaged time / person", "1m 12s",
              "same as the Zaditen allergy-eye article; other articles 1m 05s – 1m 08s")
    stat_card(s, 3.62, 4.75, 3.0, 1.85, "Onward clicks", "8",
              "6 to the Clinical Audit (via pop-up), 2 to Order Samples", value_color=TEAL)
    label(s, 7.0, 1.55, 6, "WHERE THE TRAFFIC CAME FROM")
    trs = [["Source", "People", "Publisher unique clicks"],
           ["Medicine Today Hydralyte eDM (20 Aug)", "29", "91"],
           ["Medical Republic Hydralyte eDM (25 Aug)", "18", "86"],
           ["Other (IQVIA Zaditen, direct, NZ Doctor)", "4", "–"]]
    table(s, 7.0, 1.9, 5.9, [3.6, 0.9, 1.4], trs, row_h=0.42, size=11, header_size=11, bold_cols=(1,))
    rect(s, 7.0, 3.85, 5.9, 2.75)
    text(s, 7.22, 3.98, 5.5, 0.3, "WHY NO COMPLETION OR ANSWER DATA", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 7.22, 4.35, 5.5, 2.2, [
        "The quiz runs entirely in the page and sends nothing to GA4, so completions, scores and which foods GPs picked against Hydralyte were never captured",
        "Scrolling to the bottom (10 people) is the closest proxy, and undercounts: the results panel sits mid-page",
        "Recommendation: add answer and completion tracking (as built for the travel case study) before the next quiz or re-promotion",
    ], size=10.5, gap=4)
    source(s, "Source: GA4, quiz live 17 Aug 2026; figures 17 Aug – 30 Sep, prod hostname only. 'Stayed 15s+' = 15-second engaged-view event; 'Scrolled to the bottom' = "
              "90% scroll event. Publisher unique clicks from MT / TMR click maps for the quiz link; the gap to GA4 reflects email-scanner clicks.")


def slide_leaflet_and_chart(prs, layout):
    s = new_slide(prs, layout)
    title(s, "PRINT & INSERT ASSETS ON CARE CONNECT – the AC Treatments Chart reached 1,634 HCPs; "
             "the FESS detailing leaflet QR is just starting to register")
    # left: AC chart
    label(s, 0.42, 1.5, 6.3, "ALLERGIC CONJUNCTIVITIS TREATMENTS CHART (ZADITEN)")
    stat_card(s, 0.42, 1.85, 3.0, 1.55, "HCPs who opened the chart", "1,634", "3,135 opens via tracked links, Jul–Sep")
    stat_card(s, 3.62, 1.85, 3.0, 1.55, "Downloads from Resources", "6", "12 downloads via the resource card", value_color=TEAL)
    trs = [["Came via", "Opens", "People"],
           ["IQVIA Zaditen eDM (GP + ophthal)", "2,057", "1,075"],
           ["Insight (optometry eDM + insert)", "708", "398"],
           ["mivision (optometry)", "171", "78"],
           ["AJP / AJP Daily", "120", "69"],
           ["Australian Pharmacist", "40", "21"],
           ["MT + TMR FESS eDMs (button)", "31", "14"]]
    table(s, 0.42, 3.6, 6.2, [3.6, 1.2, 1.2], trs, row_h=0.36, size=10.5, header_size=10.5, bold_cols=(1,))
    # right: FESS detailing leaflet
    label(s, 7.0, 1.5, 6, "FESS DETAILING LEAFLET (CAPH0128, REPS)")
    stat_card(s, 7.0, 1.85, 2.85, 1.55, "QR scans to Care Connect", "4", "JAS (Sep), all to the AR Clinical Bites page; 1–11 Oct: 6 visits from 2 people")
    stat_card(s, 10.05, 1.85, 2.85, 1.55, "PDF downloads", "3", "from the Resources page, JAS", value_color=TEAL)
    rect(s, 7.0, 3.6, 5.9, 2.9)
    text(s, 7.22, 3.72, 5.5, 0.3, "WHAT THIS TELLS US", size=11.5, bold=True, color=GREY, spacing=200)
    bullets(s, 7.22, 4.1, 5.5, 2.35, [
        "Every leaflet scan landed on the Allergic Rhinitis Clinical Bites page, so the QR is doing its job; volumes will build as reps hand it out",
        "The leaflet's CPD-article QR goes to AJP, not Care Connect, so isn't counted here",
        "The AC chart is our most-opened single asset this quarter, almost entirely through eye-care sends (IQVIA, Insight, mivision)",
    ], size=10.5, gap=4)
    source(s, "Source: GA4, JAS26 (1 Jul – 30 Sep 2026). Chart opens = page views of the tracked /allergic-conjunctivitis-chart/ link before it redirects to the PDF; "
              "Resources card clicks link straight to the PDF and are counted as downloads. Leaflet QR tagged utm_campaign=CAPH0128.")


def slide_mca_funnel(prs, layout):
    s = new_slide(prs, layout)
    title(s, "Without a dedicated Audit eDM, JAS still added 14 audit submissions and 14 approvals – "
             "the Audit itself is converting better")
    f = {r["stage"][:2]: r for r in [dict(zip(["stage", "ytd", "q"], row)) for row in
                                       list(csv.reader(open(OUT / "jas26_mca_funnel.csv", encoding="utf-8")))[2:]]}
    text(s, 0.32, 1.33, 12.6, 1.0, [
        "Two biggest optimisation opportunities remain:",
        [("•  Converting interest into enrolment – 1,000 landing-page viewers, 184 enrolled (18%)", {})],
        [("•  Audit completion after starting – now 47% submit (was 32% in AMJ); approvals keep pace with submissions", {})],
    ], size=12.5)
    stages = [
        ("1.", "Viewed Landing Page", None),
        ("2.", "Enrolled into Course (requires registration)", 18),
        ("3.", "Started the Module component", 72),
        ("4.", "Completed the Module component", 72),
        ("5.", "Commenced the Audit component", 56),
        ("6.", "Submitted the Audit component", 47),
        ("8.", "Audits Approved (certificate issued)", 80),
    ]
    ytd_cert, q_cert = 20, 14
    x0, maxw, y = 1.42, 7.8, 2.55
    colors = [(RGBColor(0x1E, 0x3A, 0x5F), MIDBLUE)] * 3 + [(RGBColor(0x0E, 0x96, 0x90), TEAL2)] + [(ORANGE, PEACH)] * 3
    for (key, lab, conv), (dark, light) in zip(stages, colors):
        if key == "8.":
            ytd, q = ytd_cert, q_cert
        else:
            ytd, q = int(f[key]["ytd"]), int(f[key]["q"])
        w = max(0.12, maxw * ytd / 1000)
        rect(s, x0, y, w, 0.5, fill=light, line=None, rounded=False)
        rect(s, x0, y, max(0.05, maxw * q / 1000), 0.5, fill=dark, line=None, rounded=False)
        bold = key in ("4.", "6.", "8.")
        inside = w > 4
        t = f"{fmt_int(ytd)} ({q} in JAS) {lab}"
        text(s, x0 + (1.2 if inside else w + 0.12), y, 9.5, 0.5, t, size=15 if not inside else 15,
             bold=bold, color=WHITE if inside else INK, anchor=MSO_ANCHOR.MIDDLE)
        if conv is not None:
            text(s, 0.35, y - 0.32, 0.9, 0.4, f"{conv}%", size=15, bold=True, italic=True,
                 color=RED if conv < 30 else INK, anchor=MSO_ANCHOR.MIDDLE)
            arc = s.shapes.add_shape(MSO_SHAPE.CURVED_RIGHT_ARROW, Inches(0.95), Inches(y - 0.42), Inches(0.38), Inches(0.42))
            arc.fill.solid()
            arc.fill.fore_color.rgb = MUTED
            arc.line.fill.background()
        y += 0.6
    text(s, 9.4, 2.55, 3.5, 0.9, [[("Dark = new in JAS", {"bold": True, "color": NAVY})],
                                   [("Light = total since launch (19 Mar)", {"color": GREY})]], size=11)
    source(s, "Source: WordPress DB (prod) + GA4 landing-page users, real HCPs only, 19 Mar – 30 Sep 2026; JAS = 1 Jul – 30 Sep. Re-pulled on today's data for both "
              "quarters, so AMJ figures differ slightly from the AMJ slide (some audit entries changed since July). Approvals = certificates issued.")


def slide_popup(prs, layout):
    s = new_slide(prs, layout)
    title(s, "CPD POP-UP – live since 6 Aug, seen by 666 visitors in JAS; 48 clicked through, mostly eDM readers on the new video pages")
    stat_card(s, 0.42, 1.55, 3.0, 1.6, "Shown to", "666", "people (936 times), 6 Aug – 30 Sep")
    stat_card(s, 3.62, 1.55, 3.0, 1.6, "Clicked 'start the activity'", "48", "7% of people shown; 43 arrived from an eDM", value_color=TEAL)
    stat_card(s, 6.82, 1.55, 3.0, 1.6, "Dismissed", "412", "62% closed it")
    stat_card(s, 10.02, 1.55, 2.9, 1.6, "Enrolments, 6 Aug – 30 Sep", "11", "no Audit eDM running, so the pop-up is the likely driver", value_color=TEAL)
    label(s, 0.42, 3.4, 6, "WHERE PEOPLE CLICKED IT")
    trs = [["Page", "Clicked"], ["FESS Clinical Bites landing page", "13"], ["Hydralyte Clinical Bites landing page", "11"],
           ["Guess the Glucose quiz", "6"], ["Zaditen 'spot the allergy eye' article", "4"], ["Homepage", "4"], ["Other pages", "16"]]
    table(s, 0.42, 3.75, 6.2, [4.8, 1.4], trs, row_h=0.36, size=11, header_size=11, bold_cols=(1,))
    label(s, 7.0, 3.4, 6, "BEFORE VS AFTER THE POP-UP")
    trs2 = [["", "1 Jul – 5 Aug\n(no pop-up)", "6 Aug – 30 Sep\n(pop-up live)"],
            ["Days", "36", "56"],
            ["Enrolments into the module", "22*", "11"],
            ["Module started (pre-survey)", "25", "10"],
            ["Audits submitted", "7", "7"]]
    table(s, 7.0, 3.75, 5.9, [2.9, 1.5, 1.5], trs2, row_h=0.36, header_h=0.55, size=11, header_size=10.5)
    text(s, 7.0, 5.95, 5.9, 0.7, "*Includes the TMR Clinical Audit eDM on 1 Jul. The pop-up is the only Audit promotion live from 6 Aug, "
                                 "but GA4 can't tie a pop-up click to a specific enrolment (no user ID), so the 11 is an upper bound.",
         size=9.5, italic=True, color=GREY)
    source(s, "Source: GA4 popup_shown / popup_dismissed / popup_cta_click events (popup caph0150-mca-activity) + WordPress DB enrolments (course 95553 access) and form entries, "
              "real HCPs only, 6 Aug – 30 Sep. Page rows count people per page, so sum above 48 where someone clicked on more than one page. Pop-up shows to all logged-out visitors and to logged-in members with no MCA activity.")


def slide_completer_feedback(prs, layout):
    s = new_slide(prs, layout)
    title(s, "Feedback continues to be positive from course completers, suggesting the content is relevant and valuable to the audience")
    fb = {r[0]: r[1] for r in list(csv.reader(open(OUT / "jas26_mca_eval_feedback.csv", encoding="utf-8")))[2:]}
    mod = {"rec": fb["Would you recommend this activity to a colleague? (said Yes)"],
           "chg": fb["Will you change something in your practice? (said Yes)"],
           "content": next(v for q, v in fb.items() if q.startswith("Content")),
           "lms": next(v for q, v in fb.items() if q.startswith("Learning Management")),
           "delivery": next(v for q, v in fb.items() if q.startswith("Delivery"))}
    rows = csv_rows("jas26_mca_activity_eval_distributions.csv")
    n = int(rows[0]["n_answered"])
    def apct(prefix, resp):
        k = sum(int(r["count"]) for r in rows if r["question"].startswith(prefix) and r["response"] == resp)
        return f"{round(k / n * 100)}%"
    aud = {"rec": apct("3. Would you likely recommend", "Yes"), "chg": apct("4. Would you likely change", "Yes"),
           "content": apct("Content –", "Entirely met"), "lms": apct("Learning Management System", "Entirely met"),
           "delivery": apct("Delivery –", "Entirely met")}
    GREEN = RGBColor(0x00, 0xA0, 0x4C)
    spec = [
        ("rec", [[("would recommend this activity to a colleague", {"bold": True, "size": 13})],
                 [("Module: ", {"bold": True, "italic": True}), ("Useful, practical, easy to follow and concise; a common GP condition that is rarely taught", {"italic": True})],
                 [("Audit: ", {"bold": True, "italic": True}), ("Relevant, well structured and well planned; an important, under-recognised topic", {"italic": True})]], 1.25),
        ("chg", [[("will change something in their practice", {"bold": True, "size": 13})],
                 [("Module: ", {"bold": True, "italic": True}), ("More screening and visual examination, sitz baths, correct and longer GTN use, patient education", {"italic": True})],
                 [("     'No' responders mostly felt their practice already followed the guidelines", {"italic": True, "size": 11})],
                 [("Audit: ", {"bold": True, "italic": True}), ("More screening, 4-week follow-up and recalls, sitz baths and lignocaine, guideline-based management", {"italic": True})]], 1.55),
        ("content", [[("selected ‘Entirely met’ – Content is current, contemporary, evidence-based", {"bold": True, "size": 13})]], 0.55),
        ("lms", [[("selected ‘Entirely met’ – LMS is user friendly and easily navigated", {"bold": True, "size": 13})]], 0.55),
        ("delivery", [[("selected ‘Entirely met’ – Delivery is engaging and interactive", {"bold": True, "size": 13})]], 0.55),
    ]
    x0, y0, w = 0.62, 1.75, 12.1
    shp = s.shapes.add_table(len(spec) + 1, 3, Inches(x0), Inches(y0), Inches(w), Inches(0.5 + sum(h for _, _, h in spec)))
    tbl = shp.table
    tblPr = tbl._tbl.tblPr
    for attr in ("firstRow", "bandRow"):
        tblPr.set(attr, "0")
    style = tblPr.find(qn("a:tableStyleId"))
    if style is not None:
        style.text = "{5940675A-B579-460E-94D1-54222C63F5DA}"
    for i_, cw in enumerate((1.45, 1.45, 9.2)):
        tbl.columns[i_].width = Inches(cw)
    def fill_cell(cell, paras, size=12, color=INK, align=PP_ALIGN.LEFT, bg=WHITE):
        cell.fill.solid()
        cell.fill.fore_color.rgb = bg
        cell.vertical_anchor = MSO_ANCHOR.MIDDLE
        cell.margin_left = cell.margin_right = Inches(0.12)
        tf = cell.text_frame
        tf.word_wrap = True
        for j, para in enumerate(paras):
            p = tf.paragraphs[0] if j == 0 else tf.add_paragraph()
            p.alignment = align
            if j > 0:
                p.space_before = Pt(3)
            for t, o in ([(para, {})] if isinstance(para, str) else para):
                r = p.add_run()
                r.text = t
                _font(r, o.get("size", size), o.get("color", color), o.get("bold", False), o.get("italic", False))
        _cell_border(cell, color="1E3A5F", w=12700)
    tbl.rows[0].height = Inches(0.5)
    for c, h in enumerate(("Module", "Audit", "Evaluation")):
        fill_cell(tbl.cell(0, c), [[(h, {"bold": True, "size": 13})]], color=WHITE,
                  align=PP_ALIGN.CENTER if c < 2 else PP_ALIGN.LEFT, bg=NAVY)
    for r_, (key, paras, h) in enumerate(spec, 1):
        tbl.rows[r_].height = Inches(h)
        fill_cell(tbl.cell(r_, 0), [[(mod[key], {"bold": True, "size": 18})]], color=GREEN, align=PP_ALIGN.CENTER)
        fill_cell(tbl.cell(r_, 1), [[(aud[key], {"bold": True, "size": 18})]], color=GREEN, align=PP_ALIGN.CENTER)
        fill_cell(tbl.cell(r_, 2), paras, size=11.5)
    source(s, f"Source: post-learning survey (module, n=95) and activity evaluation (audit, n={n}, submitted audits only), real HCPs only, year to date 19 Mar – 30 Sep 2026 "
              "(includes 28 module and 13 audit completers new in JAS). Comment summaries drawn from completers' free-text reasons.")


def slide_module_feedback(prs, layout):
    s = new_slide(prs, layout)
    title(s, "POST-LEARNING SURVEY – Learning Module completers remain strongly positive, now 95 HCPs since launch")
    def titled(name):
        return list(csv.reader(open(OUT / name, encoding="utf-8")))[2:]
    k, kj = titled("jas26_mca_knowledge_shift.csv"), titled("jas26_mca_knowledge_shift_jas_only.csv")
    it, itj = titled("jas26_mca_intent.csv"), titled("jas26_mca_intent_jas_only.csv")
    rk, rkj = titled("jas26_mca_risk_shift.csv"), titled("jas26_mca_risk_shift_jas_only.csv")
    fb = {r[0]: r[1] for r in titled("jas26_mca_eval_feedback.csv")}
    fbj = {r[0]: r[1] for r in titled("jas26_mca_eval_feedback_jas_only.csv")}
    gtn, gtnj = next(r for r in it if r[0].startswith("GTN")), next(r for r in itj if r[0].startswith("GTN"))
    subtitle(s, "Year to date since launch, including 28 new completers this quarter. Completers finish with greater confidence, see more of their "
                "patients as at risk, and are more likely to use conservative therapies including GTN.", y=1.3, italic=True, size=12)
    def sh(v):
        return v.replace("-", "−") + " pp"
    def shc(v):
        return AMBER if v.startswith("-") or v == "+0" else TEAL

    label(s, 0.42, 2.15, 6, "CONFIDENCE IN MANAGEMENT")
    trs = [["Confidence area (% agree/strongly agree)", "Pre", "Post", "Shift"]] + \
          [[r[0], f"{r[1]}%", f"{r[2]}%", sh(r[3])] for r in k]
    cc = {(i_, 3): shc(r[3]) for i_, r in enumerate(k, 1)}
    table(s, 0.42, 2.45, 6.3, [3.6, 0.8, 0.8, 1.1], trs, row_h=0.36, size=10.5, header_size=10.5,
          color_cells=cc, bold_cols=(3,))
    keys = [("Would you recommend this activity to a colleague? (said Yes)", "would recommend"),
            ("Will you change something in your practice? (said Yes)", "will change practice"),
            (next(q for q in fb if q.startswith("Content")), "content current & evidence-based")]
    for i_, (q, cap) in enumerate(keys):
        stat_card(s, 0.42 + i_ * 2.15, 5.15, 2.0, 1.55, "", fb[q], cap, value_size=24)

    label(s, 7.05, 2.15, 6, "USE OF INTERVENTIONS")
    trs2 = [["Treatment", "More likely", "Neutral", "Less likely"]] + \
           [[r[0].replace("Correct bowel movement behaviours", "Correct bowel habits"), f"{r[1]}%", f"{r[2]}%", f"{r[3]}%"] for r in it]
    cc2 = {(i_, 1): TEAL for i_ in range(1, len(trs2) - 1)}
    cc2[(len(trs2) - 1, 3)] = TEAL
    table(s, 7.05, 2.45, 5.85, [2.85, 1.0, 1.0, 1.0], trs2, row_h=0.3, size=10.5, header_size=10.5, color_cells=cc2)
    label(s, 7.05, 4.82, 6, "PATIENT-RISK PERCEPTION")
    names = ["Below 10%", "10–30%", "30–50%", "Above 50%"]
    trs3 = [["% of patients seen as 'at risk'", "Pre", "Post", "Shift"]] + \
           [[n, f"{r[1]}%", f"{r[2]}%", sh(r[3])] for n, r in zip(names, rk)]
    cc3 = {(1, 3): AMBER, (2, 3): TEAL, (3, 3): TEAL, (4, 3): TEAL}
    table(s, 7.05, 5.12, 5.85, [2.85, 1.0, 1.0, 1.0], trs3, row_h=0.3, size=10.5, header_size=10.5, color_cells=cc3,
          bold_cols=(3,))
    source(s, "Source: pre- and post-course surveys, real HCPs only, staff/test excluded. Year to date = cumulative 19 Mar – 30 Sep 2026, n=95 module completers "
              "(same cohort asked before and after), of whom 28 completed in JAS. 'More likely' combines 'much more' + "
              "'somewhat more'. Corticosteroid shift downward is the clinically correct direction.")


def slide_audit_feedback(prs, layout):
    s = new_slide(prs, layout)

    def load(name):
        rows = csv_rows(name)
        n = int(rows[0]["n_answered"])
        def count(prefix, responses):
            return sum(int(r["count"]) for r in rows if r["question"].startswith(prefix) and r["response"] in responses)
        return n, count

    n, count = load("jas26_mca_activity_eval_distributions.csv")
    nj, countj = load("jas26_mca_activity_eval_distributions_jas_only.csv")
    met = ("Entirely met",)
    agree = ("Strongly agree", "Agree")
    title(s, f"The {n} HCPs who completed the full Clinical Audit continue to rate it highly – all {n} would recommend it")
    rec, recj = count("3. Would you likely recommend", ("Yes",)), countj("3. Would you likely recommend", ("Yes",))
    chg, chgj = count("4. Would you likely change", ("Yes",)), countj("4. Would you likely change", ("Yes",))
    imp, impj = count("7. As a result of this activity", ("Extremely important",)), countj("7. As a result of this activity", ("Extremely important",))
    subtitle(s, f"Year to date since launch, including {nj} new completers this quarter. {chg} of {n} will change something in their practice — screening "
                "more at-risk patients, more consistent follow-up at 4 weeks, and adding sitz baths and lignocaine.", y=1.3, italic=True, size=12)
    label(s, 0.42, 2.0, 6, "AUDIT OBJECTIVES & ACTIVITY QUALITY")
    obj = [("Retrospective analysis — review past fissure patients against the treatment algorithm", "Identify adult patients previously"),
           ("Prospective analysis — screen three at-risk patients and record outcomes", "Identify three adult patients"),
           ("Reflection — identify and plan practice improvements", "Reflect on opportunities"),
           ("Content — current, evidence-based, relevant to general practice", "Content –"),
           ("LMS — user friendly and easily navigated", "Learning Management System"),
           ("Delivery — engaging / interactive", "Delivery –")]
    trs = [["Rated 'entirely met'", "Result"]] + [[lab, f"{count(p, met)} of {n}"] for lab, p in obj]
    table(s, 0.42, 2.3, 6.3, [5.0, 1.3], trs, row_h=0.4, size=10.5, header_size=10.5,
          color_cells={(i_, 1): TEAL for i_ in range(1, 5)}, bold_cols=(1,))
    for i_, (v, cap) in enumerate(((f"{rec} / {n}", "would recommend to a colleague"),
                                   (f"{chg} / {n}", "will change something in their practice"),
                                   (f"{imp} / {n}", "rate early detection 'extremely important'"))):
        stat_card(s, 0.42 + i_ * 2.15, 5.3, 2.0, 1.45, "", v, cap, value_size=24)
    label(s, 7.05, 2.0, 6, "CONFIDENCE & INTENT AFTER THE AUDIT")
    st = [("Confident identifying fissures on visual examination", "I am confident in identifying"),
          ("Confident distinguishing fissures from other anorectal conditions", "I am confident distinguishing"),
          ("Familiar with guideline recommendations for management", "I am familiar with guideline"),
          ("Intend to routinely screen using the new protocol", "I intend to use my new screening"),
          ("Can apply learning from the audit immediately", "I feel I can apply"),
          ("Comfortable asking patients about anal symptoms", "I am comfortable asking")]
    trs2 = [["Statement (agree or strongly agree)", "Result"]] + [[lab, f"{count(p, agree)} of {n}"] for lab, p in st]
    table(s, 7.05, 2.3, 5.85, [4.6, 1.25], trs2, row_h=0.4, size=10.5, header_size=10.5,
          color_cells={(i_, 1): TEAL for i_ in range(1, 7)}, bold_cols=(1,))
    quotes = ["“Brought the need for screening to my attention, probably large numbers of cases undiagnosed due to patient stigma/embarassment.”",
              "“Education for how GTN works with anatomy reinforces why we manage this way”",
              "“make sure every patient is followed up at 4/52 time”"]
    y = 5.2
    for q in quotes:
        h = 0.52 if len(q) > 80 else 0.36
        rect(s, 7.05, y, 5.85, h, fill=CARD, line=NAVY, line_w=1.25)
        text(s, 7.2, y, 5.6, h, q, size=10, italic=True, color=NAVY, anchor=MSO_ANCHOR.MIDDLE)
        y += h + 0.08
    text(s, 7.05, y - 0.02, 5.85, 0.2, "— JAS audit completers, verbatim from the activity evaluation", size=8.5, color=GREY)
    source(s, f"Source: activity evaluation survey completed at the end of the Mini Clinical Audit, real HCPs with a submitted audit only. Year to date = "
              f"19 Mar – 30 Sep 2026 (n={n}, of whom {nj} completed in JAS). Counts shown rather than percentages given the sample size.")


def slide_samples(prs, layout):
    s = new_slide(prs, layout)
    title(s, "SAMPLE ORDERS – a back-end fix on 12 Aug restored repeat ordering: 58 orders reached Care in the 7 weeks after, vs 7 in the 6 weeks before")
    months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep"]
    received = [5, 7, 12, 1, 8, 8, 6, 32, 27]
    lost = [5, 5, 9, 8, 12, 15, 17, 8, 0]
    cd = CategoryChartData()
    cd.categories = [f"{m} 26" for m in months]
    cd.add_series("Orders received by Care", received)
    cd.add_series("Repeat orders lost (overwritten, never sent)", lost)
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_STACKED, Inches(0.42), Inches(1.5), Inches(7.6), Inches(4.9), cd)
    ch = gf.chart
    style_chart(ch, 10)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 55
    ch.plots[0].overlap = 100
    for ser, col in zip(ch.series, (NAVY, PEACH)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "0;;"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.CENTER
        ser.data_labels.font.size = Pt(10)
        ser.data_labels.font.bold = True
        ser.data_labels.font.color.rgb = WHITE if col == NAVY else INK
    axis_quiet(ch.value_axis, gridlines=True)
    axis_quiet(ch.category_axis, size=10)
    ch.value_axis.maximum_scale = 45
    text(s, 5.6, 1.95, 2.3, 0.5, "Fix live 12 Aug", size=10.5, bold=True, color=TEAL, align=PP_ALIGN.CENTER)
    stat_card(s, 8.37, 1.35, 4.5, 1.6, "Orders reaching Care, JAS 26", "65",
              "vs 17 in AMJ 26 and 68 in JAS 25 – back to last year's level")
    stat_card(s, 8.37, 3.1, 4.5, 1.6, "Returning customers who re-ordered since the fix", "43",
              "50 repeat orders that, before 12 Aug, would have been silently lost", value_color=TEAL)
    rect(s, 8.37, 4.85, 4.5, 1.6)
    text(s, 8.59, 4.97, 4.1, 0.3, "WHAT HAPPENED", size=11.5, bold=True, color=GREY, spacing=200)
    text(s, 8.59, 5.3, 4.1, 1.1, "Since Dec 2024 the order form re-opened a returning customer's previous order and saved over it, "
                                 "with no new entry and no email to Care. 143+ practices lost at least one repeat order. "
                                 "Found via HCP contact-us messages; the 14 verified lost orders were passed on for fulfilment.",
         size=10, color=INK)
    source(s, "Source: Formidable form 55 (Order Samples), prod DB, real HCPs only. 'Received' = new entries (each triggers the fulfilment email). 'Lost' = entries "
              "re-submitted by the same HCP over their earlier order (lower bound: only the last overwrite per practice survives). Hydralyte eDMs (Aug) also linked to Order Samples.")


def slide_seo(prs, layout):
    s = new_slide(prs, layout)
    title(s, "SEO – since Care Connect opened to search engines on 23 Jul it has appeared in Google 2,948 times; clicks are still small (18)")
    wk = csv_rows("jas26_gsc_weekly.csv")
    cats = []
    for r in wk:
        d = date.fromisoformat(r["week_start"])
        cats.append(f"{d.day}/{d.month}")
    cats[0] += "*"
    cats[-1] += "*"

    label(s, 0.42, 1.5, 7.6, "GOOGLE IMPRESSIONS PER WEEK")
    cd = CategoryChartData()
    cd.categories = cats
    cd.add_series("Impressions", [int(r["impressions"]) for r in wk])
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(0.42), Inches(1.8), Inches(7.6), Inches(3.0), cd)
    ch = gf.chart
    style_chart(ch, 10)
    no_title(ch)
    ch.has_legend = False
    ch.plots[0].gap_width = 45
    ser = ch.series[0]
    ser.format.fill.solid()
    ser.format.fill.fore_color.rgb = NAVY
    ser.data_labels.show_value = True
    ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
    ser.data_labels.font.size = Pt(10)
    ser.data_labels.font.bold = True
    axis_quiet(ch.value_axis, gridlines=True, size=9)
    axis_quiet(ch.category_axis, size=9)
    ch.value_axis.maximum_scale = 500

    label(s, 0.42, 4.95, 7.6, "CLICKS FROM GOOGLE AND AI ASSISTANTS PER WEEK")
    cd2 = CategoryChartData()
    cd2.categories = cats
    cd2.add_series("Google clicks", [int(r["clicks"]) for r in wk])
    cd2.add_series("AI assistants (ChatGPT)", [int(r["ai_sessions"]) for r in wk])
    gf2 = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_STACKED, Inches(0.42), Inches(5.2), Inches(7.6), Inches(1.6), cd2)
    ch2 = gf2.chart
    style_chart(ch2, 10)
    no_title(ch2)
    ch2.has_legend = True
    ch2.legend.position = XL_LEGEND_POSITION.TOP
    ch2.legend.include_in_layout = False
    ch2.legend.font.size = Pt(9)
    ch2.plots[0].gap_width = 45
    ch2.plots[0].overlap = 100
    for ser2, col in zip(ch2.series, (TEAL2, PINK)):
        ser2.format.fill.solid()
        ser2.format.fill.fore_color.rgb = col
        ser2.data_labels.show_value = True
        ser2.data_labels.number_format = "0;;"
        ser2.data_labels.number_format_is_linked = False
        ser2.data_labels.position = XL_LABEL_POSITION.CENTER
        ser2.data_labels.font.size = Pt(9)
        ser2.data_labels.font.bold = True
        ser2.data_labels.font.color.rgb = WHITE
    axis_quiet(ch2.value_axis, gridlines=False, size=8)
    ch2.value_axis.visible = False
    axis_quiet(ch2.category_axis, size=9)
    ch2.value_axis.maximum_scale = 5

    stat_card(s, 8.37, 1.35, 4.5, 1.6, "Google impressions vs clicks, 23 Jul – 30 Sep", "2,948 → 18",
              "0.6% of impressions became a click; average position 12 (page 2)", value_size=26)
    stat_card(s, 8.37, 3.1, 4.5, 1.6, "Search + AI visits to the site", "34 + 4",
              "34 from search engines (Google 28, Bing 5, Yahoo 1) and 4 from ChatGPT since go-live", value_color=TEAL, value_size=26)
    rect(s, 8.37, 4.85, 4.5, 1.9)
    text(s, 8.59, 4.97, 4.1, 0.3, "WHAT PEOPLE SEARCHED", size=11.5, bold=True, color=GREY, spacing=200)
    text(s, 8.59, 5.3, 4.1, 1.4, "Most impressions are for 'Care Connect' / HCP-portal searches, many after other portals with the same name. "
                                 "Clinical terms are still small; best is 'zaditen eye drops single use' (position 6.8, 3 clicks). "
                                 "Search Console is now connected, so from OND we can track which clinical searches find us.",
         size=10, color=INK)
    source(s, "Source: Google Search Console (impressions, clicks, position: Google only) and GA4 Organic Search / AI Assistant channels (all engines), 23 Jul – 30 Sep 2026. "
              "*Part weeks: go-live week from 23 Jul; last week 28–30 Sep.")


def main() -> None:
    prs, layout = open_template()
    for build in (slide_weekly, slide_db, slide_traffic, slide_sources, slide_edm, slide_bites, slide_bites_timing, slide_quiz,
                  slide_leaflet_and_chart, slide_mca_funnel, slide_popup, slide_completer_feedback, slide_module_feedback,
                  slide_audit_feedback, slide_samples, slide_seo):
        build(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

"""JAS26 'Spotlight on eDM activity' review slides (AMJ26 slides 15-16 format):
publisher results table + click breakdown per send, one slide per brand.

    reports/.venv/bin/python reports/slides/build_jas26_spotlight_slides.py [out.pptx]

Figures are transcribed from the publisher reports on the JAS 2026 Trello card
(MT / TMR xlsx click maps, IQVIA + Insight PDFs, mivision xlsx, AJP consolidated
PDF). Media cost, CPM and CPC are left as xxx for Maria.

Definitions match the AMJ spotlights: open rate = unique opens / total sends,
CTR = total clicks / total opens, unique CTR = unique clicks / unique opens.
MT unique clicks are summed per link (as AMJ did), so they compare with TMR.
"""
from __future__ import annotations

import sys
from pathlib import Path

from pptx.chart.data import CategoryChartData
from pptx.dml.color import RGBColor
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION, XL_LEGEND_POSITION
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (INK, LINE, NAVY, RED, WHITE, axis_quiet, bullets, fmt_int, new_slide,  # noqa: E402
                                no_title, open_template, rect, source, style_chart, table, text, title)

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 eDM spotlights - Rob (review).pptx")
X = "xxx"
MT_FILL = RGBColor(0xDB, 0xEE, 0xF4)
TMR_FILL = RGBColor(0xF6, 0xE6, 0xF1)
GREY_FILL = RGBColor(0xEE, 0xEF, 0xF2)
MT_DARK, MT_LIGHT = RGBColor(0x1E, 0x3A, 0x5F), RGBColor(0x40, 0xB0, 0xC8)
TMR_DARK, TMR_LIGHT = RGBColor(0x6A, 0x2C, 0x9A), RGBColor(0xB5, 0x3C, 0x96)


def pct(a, b) -> str:
    return f"{a / b * 100:.1f}%" if a and b else "n/r"


def results_table(s, y, rows, fills, row_h=0.5):
    hdr = ["Publisher", "Brand", "Subject line", "Sent", "Total sends", "Unique opens", "Open rate", "Total opens",
           "Total clicks", "CTR", "Unique clicks", "Unique CTR", "Media Cost", "CPM", "CPC"]
    trs = [hdr]
    colors, cell_fills = {}, {}
    for i, r in enumerate(rows, 1):
        uo, to, tc, uc = r["unique_opens"], r["total_opens"], r["total_clicks"], r["unique_clicks"]
        row = [r["publisher"], r["brand"], r["subject"], r["sent"], r["sends"],
               fmt_int(uo) if isinstance(uo, int) else uo, r.get("open_rate") or pct(uo, int(r["sends"].split()[0].replace(",", ""))),
               fmt_int(to) if isinstance(to, int) else to, fmt_int(tc) if isinstance(tc, int) else tc,
               r.get("ctr") or (pct(tc, to) if isinstance(tc, int) and isinstance(to, int) else "n/r"),
               fmt_int(uc) if isinstance(uc, int) else uc,
               r.get("uctr") or (pct(uc, uo) if isinstance(uc, int) and isinstance(uo, int) else "n/r"), X, X, X]
        trs.append(row)
        for c in range(len(hdr)):
            cell_fills[(i, c)] = fills[i - 1]
            if row[c] == X:
                colors[(i, c)] = RED
    tbl = table(s, 0.42, y, 12.5, [0.95, 0.8, 2.75, 0.85, 0.95, 0.75, 0.65, 0.75, 0.7, 0.55, 0.75, 0.65, 0.7, 0.55, 0.5], trs,
                row_h=row_h, header_h=0.5, size=9, header_size=9,
                align=[PP_ALIGN.CENTER] * len(hdr), bold_cols=(5, 7, 8, 10), color_cells=colors, fills=cell_fills)
    return tbl


def click_chart(s, x, y, w, h, heading, links, dark, light):
    """links: [(label, unique, total)] — unique as the solid bar, the rest of total stacked above, total labelled."""
    rect(s, x, y, w, h, fill=WHITE, line=LINE)
    text(s, x, y + 0.1, w, 0.3, heading, size=12.5, bold=True, color=RGBColor(0x55, 0x5B, 0x66), align=PP_ALIGN.CENTER)
    cd = CategoryChartData()
    cd.categories = [lab for lab, _, _ in links]
    cd.add_series("Unique Clicks", [u for _, u, _ in links])
    cd.add_series("Total Clicks", [t - u for _, u, t in links])
    pad = max(t for _, _, t in links) * 0.1
    cd.add_series("label", [pad for _ in links])
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_STACKED, Inches(x + 0.1), Inches(y + 0.4), Inches(w - 0.2), Inches(h - 0.5), cd)
    ch = gf.chart
    style_chart(ch, 9)
    no_title(ch)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.TOP
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 70
    ch.plots[0].overlap = 100
    uniq, rest, lab = ch.series
    lab.format.fill.background()
    lab.format.line.fill.background()
    for ser, col in ((uniq, dark), (rest, light)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
    uniq.data_labels.show_value = True
    uniq.data_labels.position = XL_LABEL_POSITION.INSIDE_END
    uniq.data_labels.font.size = Pt(9)
    uniq.data_labels.font.bold = True
    uniq.data_labels.font.color.rgb = WHITE
    top = max(t for _, _, t in links)
    for i, (_, u, _) in enumerate(links):
        if u < top * 0.15:
            uniq.points[i].data_label.has_text_frame = True
            uniq.points[i].data_label.text_frame.text = ""
    for i, (_, _, total) in enumerate(links):
        dl = lab.points[i].data_label
        dl.has_text_frame = True
        dl.text_frame.text = fmt_int(total)
        dl.position = XL_LABEL_POSITION.INSIDE_BASE
        run = dl.text_frame.paragraphs[0].runs[0]
        run.font.size = Pt(9)
        run.font.bold = True
        run.font.color.rgb = INK
    from lxml import etree
    from pptx.oxml.ns import qn
    leg = ch.legend._element
    le = etree.SubElement(leg, qn("c:legendEntry"))
    etree.SubElement(le, qn("c:idx")).set("val", "2")
    etree.SubElement(le, qn("c:delete")).set("val", "1")
    leg.remove(le)
    leg.insert(1, le)
    axis_quiet(ch.value_axis, gridlines=True, size=8)
    axis_quiet(ch.category_axis, size=8)


def slide_hydralyte(prs, layout):
    s = new_slide(prs, layout)
    title(s, "SPOTLIGHT ON HYDRALYTE EDM ACTIVITY")
    bullets(s, 0.55, 1.2, 12.3, 1.2, [
        [("Both sends drove most clicks to the ", {}), ("sick-day action plan and the Clinical Bites videos", {"bold": True}),
         ("; the glucose quiz was third on both", {})],
        [("MT: ", {"bold": True}), ("higher open rate (48.6% vs 43.7%) and more site visits (276 vs 191 GA4 sessions)", {})],
        [("TMR: ", {"bold": True}), ("slightly higher click-through on opens (7.9% vs 7.4%), with the Clinical Bites link its top performer", {})],
    ], size=12, gap=3)
    rows = [
        dict(publisher="MT", brand="Hydralyte", subject="NEW Expert Video Series: Diabetes sick day management with Deb Hawthorne",
             sent="Thu 20/08/26", sends="15,902", unique_opens=7727, total_opens=12315, total_clicks=912, unique_clicks=537),
        dict(publisher="TMR", brand="Hydralyte", subject="NEW Expert Video Series: Diabetes sick day management with Deb Hawthorne",
             sent="Tue 25/08/26", sends="15,731", unique_opens=6870, total_opens=11144, total_clicks=883, unique_clicks=601),
    ]
    results_table(s, 2.2, rows, [MT_FILL, TMR_FILL], row_h=0.5)
    mt = [("Sick-day action plan", 129, 219), ("Clinical Bites videos", 107, 203), ("Glucose quiz", 91, 160),
          ("Patient leaflet", 72, 124), ("NDSS guide (external)", 47, 67), ("Order samples", 26, 32)]
    tmr = [("Clinical Bites videos", 169, 267), ("Sick-day action plan", 149, 226), ("Glucose quiz", 86, 116),
           ("GI tolerability article", 82, 106), ("Patient leaflet", 76, 98), ("Homepage", 22, 51)]
    click_chart(s, 0.42, 3.75, 6.15, 3.0, "CLICK BREAKDOWN – Medicine Today", mt, MT_DARK, MT_LIGHT)
    click_chart(s, 6.77, 3.75, 6.15, 3.0, "CLICK BREAKDOWN – The Medical Republic", tmr, TMR_DARK, TMR_LIGHT)
    source(s, "Source: publisher reports (MT 20 Aug, TMR report 15 Sep). Open rate = unique opens ÷ total sends; CTR = total clicks ÷ total opens; unique CTR = unique clicks ÷ "
              "unique opens. MT unique clicks summed per link (537; 210 people clicked). Top 6 links shown per send. GA4 sessions from the eDM reconciliation slide.")


def slide_fess(prs, layout):
    s = new_slide(prs, layout)
    title(s, "SPOTLIGHT ON FESS EDM ACTIVITY")
    bullets(s, 0.55, 1.2, 12.3, 1.2, [
        [("The ", {}), ("Allergic Rhinitis Clinical Bites", {"bold": True}),
         (" were the top link on both GP sends, with the AR chart and FESS patient leaflet close behind: resources still pull", {})],
        [("TMR: ", {"bold": True}), ("more clicks than MT (656 vs 517); MT's final report added 17 clicks after the initial one", {})],
        [("AJP (pharmacists): ", {"bold": True}), ("290 unique clicks but no measurable sign-ups; the Zaditen banner link on all three sends was dead until 8 Oct (now redirected)", {})],
    ], size=12, gap=3)
    rows = [
        dict(publisher="TMR", brand="FESS", subject="NEW Expert Video Series: Allergic Rhinitis Rounds",
             sent="Wed 16/09/26", sends="15,748", unique_opens="n/r", open_rate="n/r*", total_opens=10190, total_clicks=656,
             unique_clicks=529, uctr="n/r"),
        dict(publisher="MT", brand="FESS", subject="NEW Expert Video Series: Allergic Rhinitis Rounds",
             sent="Thu 17/09/26", sends="15,738", unique_opens=7299, total_opens=11942, total_clicks=517, unique_clicks=419),
        dict(publisher="AJP", brand="FESS", subject="Solus eDM to pharmacists (AR chart, leaflet, Clinical Bites)",
             sent="Wed 02/09/26", sends="28,137", unique_opens=10769, total_opens=16252, total_clicks=325, unique_clicks=290),
    ]
    results_table(s, 2.2, rows, [TMR_FILL, MT_FILL, GREY_FILL], row_h=0.45)
    mt = [("AR Clinical Bites", 113, 149), ("AR treatments chart", 87, 108), ("FESS patient leaflet", 85, 100),
          ("Allergy Analyser", 62, 72), ("AC chart (Zaditen)", 58, 64), ("Zaditen banner†", 10, 18)]
    tmr = [("AR Clinical Bites", 131, 161), ("FESS patient leaflet", 118, 160), ("AR treatments chart", 106, 132),
           ("Allergy Analyser", 84, 96), ("AC chart (Zaditen)", 72, 73), ("Homepage", 15, 29)]
    click_chart(s, 0.42, 4.2, 6.15, 2.58, "CLICK BREAKDOWN – Medicine Today", mt, MT_DARK, MT_LIGHT)
    click_chart(s, 6.77, 4.2, 6.15, 2.58, "CLICK BREAKDOWN – The Medical Republic", tmr, TMR_DARK, TMR_LIGHT)
    source(s, "Source: publisher reports (TMR 23 Sep, MT final, AJP consolidated Jul–Sep). *TMR reports total opens only, so open rate and unique CTR aren't comparable. "
              "MT unique clicks summed per link (419; 156 people clicked). †Linked to a page that didn't exist; redirected to the AC chart on 8 Oct.")


def slide_zaditen(prs, layout):
    s = new_slide(prs, layout)
    title(s, "SPOTLIGHT ON ZADITEN EDM ACTIVITY")
    bullets(s, 0.55, 1.2, 12.3, 1.2, [
        [("IQVIA", {"bold": True}), (" delivered the reach: 8,928 GP and 666 ophthalmology unique opens, and 1,151 unique clicks, nearly all of which reached the site (97%)", {})],
        [("Ophthalmologists were the most engaged list: ", {}), ("59% open rate, 14.3% of openers clicked", {"bold": True})],
        [("Optometry sends (Insight, mivision) added ~600 site visits; across all Zaditen sends ~1,700 visits to the AC chart but only ~2 sign-ups", {})],
    ], size=12, gap=3)
    rows = [
        dict(publisher="IQVIA", brand="Zaditen", subject="NEW AC Treatment Chart to guide allergy eyedrop selection (GPs)",
             sent="Tue 08/09/26", sends="17,749 +9,084 reminder", unique_opens=8928, open_rate="51.7%", total_opens="n/r", total_clicks="n/r",
             unique_clicks=1056, uctr="11.8%"),
        dict(publisher="IQVIA", brand="Zaditen", subject="As above (ophthalmologists)",
             sent="Tue 08/09/26", sends="1,162 +500 reminder", unique_opens=666, open_rate="59.0%", total_opens="n/r", total_clicks="n/r",
             unique_clicks=95, uctr="14.3%"),
        dict(publisher="Insight", brand="Zaditen", subject="NEW AC Treatment Chart to guide allergy eyedrop selection",
             sent="Tue 08/09/26", sends="5,393", unique_opens="n/r", open_rate="43.8%", total_opens="n/r", total_clicks=533,
             unique_clicks="n/r", ctr="n/r"),
        dict(publisher="mivision", brand="Zaditen", subject="Sponsored content + high-impact banner (optometry + ophthal)",
             sent="01 & 08/09/26", sends="14,900", unique_opens=5843, open_rate="39.2%", total_opens="n/r", total_clicks="619‡",
             unique_clicks="n/r", ctr="n/r"),
    ]
    results_table(s, 2.2, rows, [MT_FILL, MT_FILL, TMR_FILL, GREY_FILL], row_h=0.45)
    rect(s, 0.42, 4.75, 12.5, 2.0, fill=WHITE, line=LINE)
    text(s, 0.42, 4.82, 12.5, 0.3, "CLICKS AND SITE VISITS BY PUBLISHER", size=12.5, bold=True, color=RGBColor(0x55, 0x5B, 0x66), align=PP_ALIGN.CENTER)
    cd = CategoryChartData()
    cd.categories = ["IQVIA (GP)", "IQVIA (ophthal)", "Insight", "mivision"]
    cd.add_series("Publisher clicks", (1056, 95, 533, 619))
    cd.add_series("GA4 site visits", (1114, 0, 528, 81))
    gf = s.shapes.add_chart(XL_CHART_TYPE.BAR_CLUSTERED, Inches(0.6), Inches(5.12), Inches(12.1), Inches(1.6), cd)
    ch = gf.chart
    style_chart(ch, 9)
    no_title(ch)
    ch.has_legend = True
    ch.legend.position = XL_LEGEND_POSITION.RIGHT
    ch.legend.include_in_layout = False
    ch.plots[0].gap_width = 40
    ch.category_axis.reverse_order = True
    for ser, col in zip(ch.series, (NAVY, MT_LIGHT)):
        ser.format.fill.solid()
        ser.format.fill.fore_color.rgb = col
        ser.data_labels.show_value = True
        ser.data_labels.number_format = "#,##0;;"
        ser.data_labels.number_format_is_linked = False
        ser.data_labels.position = XL_LABEL_POSITION.OUTSIDE_END
        ser.data_labels.font.size = Pt(9)
    axis_quiet(ch.category_axis, size=9)
    ch.value_axis.visible = False
    ch.value_axis.has_major_gridlines = False
    source(s, "Source: IQVIA interim metrics (GP + ophthalmology lists, incl. reminder wave), Insight Marketing Insights Report Sept, mivision Metrics Report. Open rates as reported "
              "by each publisher. IQVIA GA4 visits combine both lists. ‡All clicks across 4 newsletter sends, not only on Zaditen content.")


def main() -> None:
    prs, layout = open_template()
    for build in (slide_hydralyte, slide_fess, slide_zaditen):
        build(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest} ({len(prs.slides)} slides)")


if __name__ == "__main__":
    main()

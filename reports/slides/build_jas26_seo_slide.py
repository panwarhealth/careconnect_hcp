"""JAS26 SEO slide, remade: search impressions, clicks from all search engines, clicks from AI answer bots.

    reports/.venv/bin/python reports/slides/build_jas26_seo_slide.py [out.pptx]

Impressions come from Google Search Console (Google only; Bing Webmaster Tools is not connected, so Bing
impressions are unknown). Clicks come from GA4 organic-search sessions (all engines) and AI-assistant referrals.
"""
from __future__ import annotations

import sys
from datetime import date
from pathlib import Path

from pptx.chart.data import CategoryChartData
from pptx.enum.chart import XL_CHART_TYPE, XL_LABEL_POSITION, XL_LEGEND_POSITION
from pptx.util import Inches, Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_jas26_slides import (GREY, INK, NAVY, PINK, TEAL, TEAL2, WHITE, axis_quiet, csv_rows, label, new_slide,  # noqa: E402
                                no_title, open_template, rect, source, stat_card, style_chart, text, title)

DEST = Path("/mnt/c/Users/User/Downloads/CAPH0107 JAS26 SEO slide - Rob (remake).pptx")


def slide_seo(prs, layout):
    s = new_slide(prs, layout)
    wk = csv_rows("jas26_seo_weekly_all_engines.csv")
    imp = sum(int(r["google_impressions"]) for r in wk)
    clicks = sum(int(r["search_clicks_all_engines"]) for r in wk)
    ai = sum(int(r["ai_clicks"]) for r in wk)
    title(s, f"SEO – since the site was submitted to Google and Bing on 23 Jul: {imp:,} search impressions, "
             f"{clicks} clicks from search engines and {ai} from AI answer bots")
    cats = []
    for r in wk:
        d = date.fromisoformat(r["week_start"])
        cats.append(f"{d.day}/{d.month}")
    cats[0] += "*"
    cats[-1] += "*"

    label(s, 0.42, 1.5, 7.6, "SEARCH IMPRESSIONS PER WEEK")
    cd = CategoryChartData()
    cd.categories = cats
    cd.add_series("Impressions", [int(r["google_impressions"]) for r in wk])
    gf = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, Inches(0.42), Inches(1.8), Inches(7.6), Inches(2.9), cd)
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

    label(s, 0.42, 4.85, 7.6, "CLICKS FROM SEARCH ENGINES AND AI ANSWER BOTS PER WEEK")
    cd2 = CategoryChartData()
    cd2.categories = cats
    cd2.add_series("Search engines (all)", [int(r["search_clicks_all_engines"]) for r in wk])
    cd2.add_series("AI answer bots", [int(r["ai_clicks"]) for r in wk])
    gf2 = s.shapes.add_chart(XL_CHART_TYPE.COLUMN_STACKED, Inches(0.42), Inches(5.1), Inches(7.6), Inches(1.7), cd2)
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
    ch2.value_axis.maximum_scale = 14

    stat_card(s, 8.37, 1.35, 4.5, 1.45, "Search impressions, 23 Jul – 30 Sep", f"{imp:,}",
              "times a Care Connect page appeared in Google results (Bing doesn't report impressions to us yet)", value_size=26)
    stat_card(s, 8.37, 2.95, 4.5, 1.45, "Clicks from search engines", f"{clicks}",
              "Google 28, Bing 5, Yahoo 1", value_color=TEAL, value_size=26)
    stat_card(s, 8.37, 4.55, 4.5, 1.0, "Clicks from AI answer bots", f"{ai}", "all from ChatGPT", value_color=PINK, value_size=22)
    top = sorted(csv_rows("jas26_gsc_queries.csv"), key=lambda r: -int(r["impressions"]))[:14]
    rect(s, 8.37, 5.7, 4.5, 1.05)
    text(s, 8.59, 5.78, 4.1, 0.3, "WHAT PEOPLE SEARCHED (TOP TERMS)", size=11, bold=True, color=GREY, spacing=200)
    text(s, 8.59, 6.06, 4.1, 0.68, ", ".join(r["query"] for r in top), size=8.5, color=INK)
    source(s, "Source: Google Search Console (impressions, Google only) and GA4 organic-search sessions (all engines) and AI-assistant referrals, 23 Jul – 30 Sep 2026. "
              "*Part weeks: go-live week from 23 Jul; last week 28–30 Sep. GA4 misses a few clicks that Search Console records (ad blockers).")


def main() -> None:
    prs, layout = open_template()
    slide_seo(prs, layout)
    dest = Path(sys.argv[1]) if len(sys.argv) > 1 else DEST
    prs.save(str(dest))
    print(f"wrote {dest}")


if __name__ == "__main__":
    main()

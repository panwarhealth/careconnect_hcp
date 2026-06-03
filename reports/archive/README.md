# archive/ — not maintained

Reference only. These scripts are **not** kept in sync with `lib/` and may use
the old inline-credentials pattern. Kept because they're either worked examples
of a one-off pull or the source of a specific past deliverable. Don't run them
without checking; lift the query and re-run it through a `lib`-backed script
instead.

## Contents

**One-shot probes / checks** (built to answer a single question, now answered)
- `discover_urls.py`, `discover_urls2.py` — landing-page discovery passes.
- `ga4_qr_check.py`, `ga4_qr_realtime.py` — FY27 sample-bag QR campaign probes.
- `ga4_mca_fix_check.py` — post-fix MCA traffic check.
- `read_third_party_edms.py` — probe to find the third-party eDM xlsx structure
  (superseded by `edm/extract.py`).

**Superseded versions**
- `ga4_article_depth.py` — v1, replaced by `ga4/article_depth.py` (was `_v2`).
- `ga4_device_analysis_fy25.py` — FY25 device cut, folded into `ga4/device.py`.

**Campaign-specific deliverables** (built for one campaign report)
- `ga4_caph0062_audit.py`, `ga4_caph0062_topline.py`,
  `build_caph0062_comparison_docx.py`, `build_caph0062_topline_docx.py` — CAPH0062.
- `db_mca_all_data.py`, `db_mca_outcomes.py` — MCA audit/outcomes pulls.

**pre-refactor-originals/**
The original top-level versions of scripts that were rewritten onto `lib/`
during the 2026-06 refactor (`ga4_pull.py` → `ga4/headline.py`,
`ga4_funnel_fy26.py` → `ga4/funnel.py`, `db_deep_dive.py` → `db/deep_dive.py`,
`mailchimp_pull.py` → `mailchimp/campaigns.py`,
`weekly_signups_users.py` → `ga4/weekly_signups.py`,
`ga4_landing_page_v3_fy26.py` → `ga4/landing_pages.py`). Kept until the `lib`
versions have been validated against a live run; delete them once confirmed.

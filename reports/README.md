# reports/ — analytics & reporting scripts

Pull data for quarterly / annual reviews from **GA4**, the **WordPress DB**, and
**Mailchimp**, write CSVs/markdown to `out/`, and (under WSL) copy them to
Windows Downloads.

All scripts share one library (`lib/`) for credentials, queries, output, and
reporting periods. There is **no inline credential or property-ID duplication** —
that all lives in `lib/`.

## Layout

```
reports/
├── lib/            # shared foundation — imported, not run directly
│   ├── ga4.py        GA4 client (OAuth refresh) + run_report/order/filter helpers
│   ├── db.py         run SQL against the local docker `db` (MySQL) container
│   ├── mailchimp.py  Mailchimp Marketing client from .env
│   ├── periods.py    Care Pharma FY calendar + --period/--start/--end parsing
│   └── io.py         out/ dirs, CSV writers, WSL→Windows-Downloads copy
├── ga4/            # GA4 report scripts
├── db/             # DB report (deep_dive.py) + standalone queries/*.sql
├── mailchimp/      # Mailchimp pulls + GA4×Mailchimp URL merge
├── edm/            # third-party eDM xlsx parsing → CSV/markdown
├── ga4_auth.py     # one-time GA4 OAuth bootstrap (see below)
├── out/            # generated CSV/MD output (gitignored)
└── archive/        # one-shot probes, campaign-specific reports, superseded scripts
```

## Setup (once)

```bash
cd reports
python -m venv .venv && source .venv/bin/activate   # if .venv doesn't exist
pip install -r requirements.txt
```

**Secrets** (never committed):
- **Mailchimp** — set `MAILCHIMP_API_KEY` and `MAILCHIMP_DC` in the repo `.env`
  (see `../.env.example`). `MYSQL_ROOT_PASSWORD` is already there for DB pulls.
- **GA4** — needs an OAuth refresh token at `../.secrets/ga4-token.json`
  (gitignored). Create it once:
  ```bash
  python reports/ga4_auth.py     # opens an OAuth URL, saves the refresh token
  ```
  Requires `../.secrets/ga4-oauth-client.json` (the OAuth client) to exist first.

The DB scripts require the local stack to be up: `docker compose up -d`.

## Reporting periods

Care Pharma's FY runs **April–March**; the label is the year it *ends*:

| `--period` | window | note |
|---|---|---|
| `FY26`   | 2025-04-01 .. 2026-03-31 | full fiscal year |
| `FY26Q1` | 2025-04-01 .. 2025-06-30 | Apr–Jun |
| `FY26Q2` | 2025-07-01 .. 2025-09-30 | Jul–Sep |
| `FY26Q3` | 2025-10-01 .. 2025-12-31 | Oct–Dec |
| `FY26Q4` | 2026-01-01 .. 2026-03-31 | Jan–Mar (the old "JFM 2026") |

Parameterized scripts accept `--period FY27Q1` **or** `--start/--end` for an
arbitrary window, and stamp the period slug into output filenames so reruns of
different quarters don't overwrite each other. Quick check:
`python lib/periods.py FY27Q1`.

## Running a quarterly review

```bash
source .venv/bin/activate
Q=FY26Q4                       # the quarter you're reporting on

# GA4 headline + funnel + sections + landing pages
python ga4/headline.py       --period $Q
python ga4/funnel.py         --period $Q
python ga4/section_combined.py --period $Q
python ga4/landing_pages.py  --period $Q

# DB (full-history pulls; not period-bound) — needs docker up
python db/deep_dive.py

# Mailchimp campaigns in the window
python mailchimp/campaigns.py --period $Q

# Weekly signups vs users trend (defaults to the 2-year window)
python ga4/weekly_signups.py
```

Output lands in `out/{ga4,db,mailchimp,third_party_edms}/` and, on WSL, is
copied to Windows Downloads.

## Script catalogue

`--period` = parameterized (rerun any quarter).
`fixed FY26` = window/narrative is locked to the FY26 analysis it was built for;
re-point dates carefully before reusing (see the `# TODO: parameterize` note in-file).
`relative` = uses a rolling window (e.g. last 90 days), not an FY.

### ga4/
| script | what | dates |
|---|---|---|
| `headline.py` | users/sessions/pageviews, top pages, channel + YoY, source/medium | `--period` |
| `funnel.py` | channel summary + email pageviews by section + top email pages | `--period` |
| `section_combined.py` / `section_touch.py` | section-level engagement (touch methodology) | `--period` |
| `landing_pages.py` | sessions bucketed by landing-page section (v3) | `--period` |
| `article_depth.py` | per-article pageviews + 15s reads + 90% scroll | `--period` |
| `device.py` | device category + engagement-by-device + OS breakdown (merged) | `--period` |
| `email_skew.py` / `email_skew_users.py` | email-channel section skew vs site | `--period` |
| `mist_sources.py` | MIST tool traffic sources | `--period` |
| `downloads_tools.py` | downloads + interactive-tool engagement | `--period` |
| `weekly_signups.py` | weekly DB sign-ups vs GA4 users, campaign-annotated | `--start/--end` (2-yr default) |
| `attribution.py` | registration attribution, JFM/FY YoY pairs | fixed FY26 |
| `blog_attribution.py` | per-blog-post source/medium/campaign | fixed FY26 |
| `edm_resources.py` | eDM-driven resources/tools spikes | fixed FY26 |
| `edm_signup_attribution.py` | eDM→signup attribution vs DB ground-truth | fixed FY26 |
| `paywall.py` | pre/post gate-removal traffic split (Dec-2025 event) | fixed FY26 |
| `attribution_check.py` | UTM-coverage sanity check | relative (30d) |
| `daily_trend.py` | daily totals, look for cliffs | relative (90d) |
| `smoke_test.py` | verify GA4 token + property work | relative (7d) |

### db/
- `deep_dive.py` — signups, forms, LearnDash, RCP, demographics, activity (full-history; needs docker `db` up).
- `queries/*.sql` — standalone SQL snippets (run via `docker compose exec -T db mysql ...` or paste into a client).

### mailchimp/
- `campaigns.py` — campaigns sent in a period (`--period`).
- `campaigns_deep.py` — deep per-campaign pull (fixed FY26+Apr window; see in-file TODO).
- `url_merge.py` — per-URL merge of Mailchimp click-details with GA4 attribution (`--period`, custom default window).
- `smoke_test.py` — verify the Mailchimp API key.

### edm/
- `extract.py` — parse third-party eDM xlsx/xls into a metrics CSV.
- `build_md.py` — build the Claude Work markdown from the MC + GA4 merge.

## Conventions for new scripts

1. Start with the path header, then import from `lib`:
   ```python
   import sys
   from pathlib import Path
   sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
   from lib import ga4, io, periods
   ```
2. Get the client from `lib` (`ga4.client()` / `mailchimp.client()` / `db.query()`).
   Never re-implement the OAuth refresh or hardcode the property ID
   (`ga4.PROPERTY_ID`).
3. Take `periods.add_arguments()` / `from_args()` for dates; put `period.slug` in
   output filenames.
4. Write via `io.write_csv` / `io.write_dicts` to `io.out_dir(...)`; copy to
   Downloads with `io.copy_to_downloads`.
5. **No secrets in code.** Mailchimp + DB creds come from `.env`; GA4 from
   `../.secrets/`. Both are gitignored.

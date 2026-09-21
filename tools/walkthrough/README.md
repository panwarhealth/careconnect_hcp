# Clinical Audit 2026 walkthrough

Walks the 2026 Clinical Audit end to end on the local site as a test user, checks the
interactive behaviour, and renders every screen to a vector PDF for reviewer sign-off.
Pages come out in this order: note, module chooser, course page, Step 1A, 1B, 2A, 2B, 3
(all filled in), activity evaluation (filled in), thank-you, certificate.

## Run

```bash
docker compose up -d                 # local site on http://localhost:8080
cd tools/walkthrough
npm install                          # first time only
npx playwright install chromium-headless-shell   # first time only
./build.sh                           # ~4 minutes
```

The PDF lands in `out/` and a dated copy in the Windows Downloads folder.

`build.sh` runs, in order: `reset.php` (wipes the test user's 2026 audit progress),
`walk.mjs` (fills and captures every screen, prints PASS/FAIL checks, writes `out/checks.json`),
`approve.php` (approves the audit as the reviewer would, which issues the certificate),
`note.py` (the opening note page) and `assemble.py` (merges `out/manifest.json` into one PDF).

Options: `node walk.mjs --user Rob-Panwar --pass staging123 --headed`.
The test user needs legacy audit progress for the chooser screen to show both cards;
Rob-Panwar has it in the seeded DB. Set the local password first if needed:
`docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; wp_set_password("staging123", 25027);'`

## Test data

Step 1B counts live in `NUMBERS` in `walk.mjs` (20 identified, 12 diagnosed, groups add up
to 12). Boxes not listed are left blank on purpose to exercise the fill-with-zero on Next.
Step 1B question 3 picks the first three areas with a reason each (the form needs at least three).

## Local only

The reset and approval steps run PHP inside the wordpress container via `php.sh`, so the walk
does not run against staging or prod.

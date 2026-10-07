# Walkthroughs

Walks a feature end to end on the local site as a test user, checks its behaviour (PASS/FAIL),
and renders every screen to a vector PDF for reviewer sign-off. Vector means real text, so
reviewers can select and annotate it.

## Layout

```
tools/walkthrough/
├── lib/                 # shared, job-independent
│   ├── session.mjs      # args, browser, login, goto, PASS/FAIL checks, php() runner
│   ├── vec.mjs          # captures the current page as one full-height vector PDF
│   ├── php.sh           # runs a PHP script inside the local wordpress container
│   ├── note.py          # text-only note page, put first in the PDF
│   └── assemble.py      # merges out/manifest.json into one bookmarked PDF
└── <job>/               # one folder per job, e.g. caph0150/
    ├── walk.mjs         # the walk: navigate, interact, check(), rec.shot()
    ├── build.sh         # walk + note + assemble + copy to Windows Downloads
    └── *.php            # optional setup/teardown run via php(), e.g. reset progress
```

Each job writes to its own `<job>/out/` (gitignored).

## First run

```bash
docker compose up -d                              # local site on http://localhost:8080
cd tools/walkthrough
npm install                                       # first time only
npx playwright install chromium-headless-shell    # first time only
./caph0150/build.sh
```

Every walk takes `--site`, `--user`, `--pass` and `--headed` (watch the browser). Defaults are
the local site and `Rob-Panwar` / `staging123`. Set a local password if needed:
`docker compose exec -T wordpress php -r 'require "/var/www/html/wp-load.php"; wp_set_password("staging123", 25027);'`

## Starting a new job

Copy `caph0150/walk.mjs` and `build.sh` as a template, keep the imports and `main()` shape,
and replace the steps. The minimum walk is:

```js
import { createRecorder } from '../lib/vec.mjs';
import { parseArgs, createChecks, launch, login, goto } from '../lib/session.mjs';

const args = parseArgs();
const { check, write } = createChecks();
const rec = createRecorder(OUT);
const { browser, page } = await launch({ headed: args.headed });
await login(page, args);
await goto(page, args.site + '/some-page/');
check('heading shows', await page.locator('h1').count() > 0);
await rec.shot(page, 'Some page');
rec.write();
write(OUT);
await browser.close();
```

## Local only

`php()` runs inside the local wordpress container, so walks that reset or approve anything
do not run against staging or prod.

## Jobs

- `caph0150/`: 2026 Clinical Audit. Note, module chooser, course page, Steps 1A-3 filled in,
  activity evaluation, thank-you, certificate. The test user needs legacy audit progress for the
  chooser to show both cards (Rob-Panwar has it in the seeded DB). Step 1B counts are in
  `NUMBERS`; boxes not listed are left blank on purpose to exercise the fill-with-zero on Next.
- `caph0148/`: travel & diabetes case study. `checks.mjs` plays it at four screen sizes (PASS/FAIL
  only); `build.sh` makes the review PDF: each stage, every pop-up over the page, the sorting tick
  and wrong-card states, then the completed page. Sections are cut from full-page prints by
  `crop.py`, because the theme sizes some blocks to the window height.

// Walks the 2026 Clinical Audit end to end as a test user and records every screen
// as a vector PDF, in the order the review PDF presents them:
//   chooser, course page, Step 1A..3 (filled), evaluation (filled), thank-you, certificate.
//
//   node walk.mjs [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123] [--out out] [--headed]
//
// Local only: the reset and approval steps run PHP inside the wordpress container.
import { chromium } from 'playwright';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createRecorder } from './lib/vec.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));

function parseArgs(argv) {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    const t = argv[i];
    if (!t.startsWith('--')) continue;
    if (argv[i + 1] && !argv[i + 1].startsWith('--')) args[t.slice(2)] = argv[++i];
    else args[t.slice(2)] = true;
  }
  return args;
}
const args = parseArgs(process.argv.slice(2));
const SITE = (args.site || 'http://localhost:8080').replace(/\/$/, '');
const USER = args.user || 'Rob-Panwar';
const PASS = args.pass || 'staging123';
const OUT = path.resolve(here, args.out || 'out');
const COURSE = '/courses/clinical-audit-anal-fissure-management/';
const LESSON = COURSE + 'lessons/complete-clinical-audit/';
const QUIZ = COURSE + 'quizzes/clinical-audit-activity-evaluation/';
const TEXT = 'Test answer (walkthrough)';

// Step 1B counts. Anything not listed is left blank and becomes 0 on Next.
const NUMBERS = {
  'v2-khh7w': 20, 'v2-9962s': 12,
  'v2-sbk2o': 7, 'v2-knwub': 5,
  'v2-83nfc': 7, 'v2-8ufcu': 5,
  'v2-nnv4f': 10, 'v2-o2g6g': 2,
  'v2-i8tk5': 8, 'v2-8xypo': 3,
  'v2-49s4j': 10, 'v2-kake1': 10, 'v2-gpxbt': 2,
  'v2-d8up2': 6, 'v2-vhyvg': 9, 'v2-wk7ti': 6, 'v2-498m6': 4, 'v2-rl3i9': 7, 'v2-pbvfj': 8, 'v2-2xfec': 5,
  'v2-byfrr': 8, 'v2-ipb6g': 7, 'v2-ob91k': 1, 'v2-komlb': 3
};
// Step 1B question 3: at least three areas, each with a reason.
const AREAS = ['v2-or0v0', 'v2-nl84o', 'v2-qx8vp'];
const REASONS = { 'v2-q65tg': 'Reason (walkthrough)', 'v2-wr612': 'Reason (walkthrough)', 'v2-vlg1l': 'Reason (walkthrough)' };

const checks = [];
function check(name, ok, detail = '') {
  checks.push({ name, ok: !!ok, detail });
  console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  (' + detail + ')' : ''}`);
}

function php(script, ...a) {
  const out = execFileSync('bash', [path.join(here, 'php.sh'), script, ...a], { encoding: 'utf8' });
  console.log('  php:', out.trim());
  return out;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function login(page) {
  await page.goto(SITE + '/log-in/', { waitUntil: 'load' });
  await page.fill('#rcp_user_login', USER);
  await page.fill('#rcp_user_pass', PASS);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), page.click('#rcp_login_submit')]);
  const loggedIn = await page.evaluate(() => document.body.classList.contains('logged-in'));
  if (!loggedIn) throw new Error('login failed for ' + USER);
}

async function dismissPopups(page) {
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('.modal.show .close, [class*="popup"] [aria-label="Close"], .hcp-popup__close')) el.click();
  }).catch(() => {});
}

async function goto(page, route) {
  await page.goto(SITE + route, { waitUntil: 'load' });
  await sleep(600);
  await dismissPopups(page);
}

/** Fields on the current Formidable page, from the plugin's key -> id map. */
async function fieldMap(page) {
  return page.evaluate(() => (window.hcpAuditV2 && window.hcpAuditV2.fields) || {});
}

/** One pass over the visible inputs. Returns how many controls it touched. */
async function fillPass(page, fields) {
  const idByKey = fields;
  const keyById = Object.fromEntries(Object.entries(idByKey).map(([k, v]) => [String(v), k]));
  const plan = await page.evaluate(({ keyById, AREAS, idByKey }) => {
    const visible = (el) => el.getClientRects().length > 0 && !el.disabled && !el.readOnly && !/frm_verify/.test(el.className) && el.type !== 'hidden';
    const keyOf = (el) => {
      const m = (el.name || '').match(/item_meta\[(\d+)\]/);
      return m ? keyById[m[1]] || null : null;
    };
    const labelOf = (el) => {
      const l = el.id ? document.querySelector(`label[for="${el.id}"]`) : null;
      return (l ? l.textContent : el.parentElement.textContent).trim();
    };
    const form = document.querySelector('form.frm-show-form');
    const out = { radios: [], checks: [], texts: [], numbers: [] };
    if (!form) return out;
    const radioGroups = {};
    for (const el of form.querySelectorAll('input[type="radio"]')) {
      if (!visible(el)) continue;
      (radioGroups[el.name] = radioGroups[el.name] || []).push({ id: el.id, label: labelOf(el), checked: el.checked });
    }
    for (const opts of Object.values(radioGroups)) {
      if (opts.some((o) => o.checked)) continue;
      let pick = opts.findIndex((o) => /recommended/i.test(o.label));
      if (pick < 0) pick = Math.min(1, opts.length - 1);
      out.radios.push(opts[pick].id);
    }
    const boxGroups = {};
    for (const el of form.querySelectorAll('input[type="checkbox"]')) {
      if (!visible(el) || /frm_verify/.test(el.className)) continue;
      (boxGroups[el.name] = boxGroups[el.name] || []).push({ id: el.id, label: labelOf(el), checked: el.checked, key: keyOf(el) });
    }
    const areaIds = new Set(AREAS.map((k) => String(idByKey[k])));
    for (const opts of Object.values(boxGroups)) {
      if (opts.some((o) => o.checked)) continue;
      const m = (opts[0].id.match(/field_[^-]*/) || [''])[0];
      const isArea = opts[0].key && AREAS.includes(opts[0].key);
      let pick = isArea ? opts[0] : opts.find((o) => !/^other/i.test(o.label)) || opts[0];
      out.checks.push(pick.id);
    }
    for (const el of form.querySelectorAll('textarea, input[type="text"], input[type="number"]')) {
      if (!visible(el) || el.value.trim() !== '') continue;
      const key = keyOf(el);
      if (el.type === 'number') out.numbers.push({ id: el.id, key });
      else out.texts.push({ id: el.id, key, tag: el.tagName.toLowerCase() });
    }
    return out;
  }, { keyById, AREAS, idByKey });

  let touched = 0;
  // Formidable reuses an id between a field and another field's option, so selectors carry the control type.
  for (const id of plan.radios) { await page.click('input[type="radio"]#' + CSS.escape(id)); touched++; }
  for (const id of plan.checks) { await page.click('input[type="checkbox"]#' + CSS.escape(id)); touched++; }
  for (const t of plan.texts) {
    const sel = (t.tag === 'textarea' ? 'textarea#' : 'input[type="text"]#') + CSS.escape(t.id);
    await page.fill(sel, REASONS[t.key] || TEXT); touched++;
  }
  for (const n of plan.numbers) {
    if (NUMBERS[n.key] === undefined) continue;
    await page.fill('input[type="number"]#' + CSS.escape(n.id), String(NUMBERS[n.key])); touched++;
  }
  return touched;
}

async function fillPage(page) {
  const fields = await fieldMap(page);
  for (let i = 0; i < 5; i++) {
    const n = await fillPass(page, fields);
    if (!n) break;
    await sleep(250);
  }
}

// Formidable keeps the current page as the order of its page-break field; the last page has none.
const PAGE_BREAKS = { 79: 1, 387: 2, 537: 3, 605: 4 };
async function currentPage(page) {
  const order = await page.evaluate(() => document.querySelector('form.frm-show-form input[name^="frm_page_order_"]')?.value || '');
  if (!order) return (await page.locator('form.frm-show-form').count()) ? 5 : 0;
  return PAGE_BREAKS[order] || 0;
}

const NEXT = 'form.frm-show-form button.frm_button_submit:has-text("Next")';
const SUBMIT = 'form.frm-show-form button.frm_button_submit:has-text("Submit"), form.frm-show-form .frm_final_submit';

async function next(page) {
  const before = await currentPage(page);
  await page.locator(NEXT).first().click();
  await page.waitForFunction((b) => {
    const o = document.querySelector('form.frm-show-form input[name^="frm_page_order_"]')?.value || '';
    return ({ 79: 1, 387: 2, 537: 3, 605: 4 }[o] || (document.querySelector('form.frm-show-form') ? 5 : 0)) !== b;
  }, before, { timeout: 30000 }).catch(() => {});
  await sleep(800);
  await page.evaluate(() => window.scrollTo(0, 0));
  return (await currentPage(page)) !== before;
}

const CSS = { escape: (s) => s.replace(/([^a-zA-Z0-9_-])/g, '\\$1') };

async function unpressedCheck(page) {
  return page.$('.hcp-check:not(.hcp-stage-hidden *) .hcp-check__btn:visible');
}

async function main() {
  fs.rmSync(OUT, { recursive: true, force: true });
  const local = /localhost|127\.0\.0\.1/.test(SITE);
  if (!local) throw new Error('walk.mjs runs against the local site only (reset and approval need the container).');

  const ids = JSON.parse(php('reset.php', USER).trim().split('\n').pop());
  const rec = createRecorder(OUT, { width: 1280, settle: 1200 });
  const browser = await chromium.launch({ headless: !args.headed });
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  page.on('pageerror', (e) => console.log('  ! page error:', e.message));

  await login(page);

  // 1. Chooser (this user has legacy progress, so both cards show).
  await goto(page, '/clinical-audit/');
  check('chooser shows both audit cards', await page.locator('text=Mini Clinical Audit (legacy)').count() > 0 && await page.locator('text=Clinical Audit: Anal Fissure Management').count() > 0);
  await rec.shot(page, 'Module chooser');

  // 2. Course page.
  await goto(page, COURSE);
  await rec.shot(page, 'Course page');

  // 3. Lesson, Step 1A.
  await goto(page, LESSON);
  check('lesson opens on Step 1A', (await currentPage(page)) === 1);
  await fillPage(page);
  await rec.shot(page, 'Step 1A');
  check('Step 1A advances', await next(page));

  // 4. Step 1B.
  await fillPage(page);
  check('1B intro bolds total patient cohort', await page.locator('em strong:has-text("total patient cohort")').count() > 0);
  check('1B count intro says leave the box blank', await page.locator('text=leave the box blank').count() > 0);
  // Group sum check fires on Next only, then clears on edit.
  const f = await fieldMap(page);
  await page.fill(`input[name^="item_meta[${f['v2-knwub']}]"]:visible`, '4');
  check('1B sum mismatch silent while typing', (await page.locator('.hcp-sum-error:visible').count()) === 0);
  const blanks = () => page.locator('form.frm-show-form input[type="number"]:visible:not([readonly])').evaluateAll((els) => els.filter((e) => e.value === '').length);
  const blankBefore = await blanks();
  check('1B count boxes left blank before Next', blankBefore > 0, `${blankBefore} blank`);
  await page.locator(NEXT).first().click();
  await sleep(600);
  check('1B sum mismatch blocks Next', (await page.locator('.hcp-sum-error:visible').count()) > 0 && (await currentPage(page)) === 2, await page.locator('.hcp-sum-error').first().textContent().catch(() => ''));
  check('1B blank count boxes filled with 0 on Next', (await blanks()) === 0);
  await page.fill(`input[name^="item_meta[${f['v2-knwub']}]"]:visible`, '5');
  await sleep(300);
  check('1B sum error clears on edit', (await page.locator('.hcp-sum-error:visible').count()) === 0);
  await rec.shot(page, 'Step 1B');
  check('Step 1B advances', await next(page));

  // 5. Step 2A: staged reveal, Check gate, then everything pressed.
  check('2A headings read Patient N of 3', (await page.locator('h3:has-text("Patient 1 of 3")').count()) > 0 && (await page.locator('h3:has-text("Patient 3 of 3")').count()) > 0);
  const hiddenAtStart = await page.locator('.hcp-stage-hidden').count();
  check('2A later stages hidden at start', hiddenAtStart > 0, `${hiddenAtStart} hidden`);
  check('2A Patient 2 hidden at start', await page.locator('.frm_section_heading.hcp-stage-hidden').count() >= 2);
  await page.locator(NEXT).first().click();
  await sleep(500);
  check('2A Next blocked until Checks pressed', (await page.locator('.hcp-check-gate:visible').count()) === 1 && (await currentPage(page)) === 3);
  check('2A only the first Check flagged', (await page.locator('.hcp-check.is-pending').count()) === 1);
  let presses = 0;
  for (let i = 0; i < 60; i++) {
    await fillPage(page);
    const pending = page.locator('.hcp-check:not(:has(.hcp-check__feedback.is-open)) .hcp-check__btn:visible');
    if (!(await pending.count())) break;
    const btn = pending.first();
    const open = await page.evaluate(() => [...document.querySelectorAll('.hcp-check')].filter((c) => c.querySelector('.hcp-check__feedback.is-open')).length);
    await btn.click();
    await sleep(300);
    const openAfter = await page.evaluate(() => [...document.querySelectorAll('.hcp-check')].filter((c) => c.querySelector('.hcp-check__feedback.is-open')).length);
    if (openAfter === open) { // the question above was not answered; stop looping forever
      const prompt = await page.locator('.hcp-check__prompt.is-open:visible').count();
      if (prompt) { await fillPage(page); await btn.click(); await sleep(300); }
    }
    presses++;
    if (presses === 1) {
      check('2A gate message clears after a Check', (await page.locator('.hcp-check-gate').count()) === 0 && (await page.locator('.hcp-check.is-pending').count()) === 0);
    }
    const allOpen = await page.evaluate(() => [...document.querySelectorAll('.hcp-check')].every((c) => c.querySelector('.hcp-check__feedback.is-open')));
    if (allOpen) break;
  }
  const totalChecks = await page.locator('.hcp-check').count();
  const openChecks = await page.locator('.hcp-check__feedback.is-open').count();
  check('2A every Check pressed', totalChecks > 0 && openChecks === totalChecks, `${openChecks}/${totalChecks}`);
  check('2A nothing left hidden', (await page.locator('.hcp-stage-hidden').count()) === 0);
  check('2A BMI uses superscript', (await page.locator('text=kg/m²').count()) >= 2);
  check('2A follow-up call says glyceryl trinitrate', (await page.locator('text=starting the glyceryl trinitrate').count()) > 0);
  const fbStyle = await page.locator('.hcp-check__feedback.is-open').first().evaluate((el) => { const s = getComputedStyle(el); return s.backgroundColor + ' | ' + s.borderTopColor + ' | ' + s.color; });
  check('2A feedback box white, teal keyline, black text', fbStyle === 'rgb(255, 255, 255) | rgb(0, 147, 147) | rgb(0, 0, 0)', fbStyle);
  await rec.shot(page, 'Step 2A');
  check('Step 2A advances', await next(page));

  // 6. Step 2B.
  await fillPage(page);
  check('2B lists the areas chosen in 1B', (await page.locator('.hcp-auto-areas li:not(.hcp-auto-areas__empty)').count()) >= 3);
  await rec.shot(page, 'Step 2B');
  check('Step 2B advances', await next(page));

  // 7. Step 3 and submit.
  await fillPage(page);
  await rec.shot(page, 'Step 3');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 60000 }).catch(() => {}),
    page.locator(SUBMIT).first().click()
  ]);
  await sleep(1500);
  check('audit submitted', (await page.locator(NEXT).count()) === 0);

  // 8. Evaluation.
  await goto(page, QUIZ);
  check('evaluation names the audit', (await page.locator('text=Anal Fissures: Breaking the Cycle and the Stigma').count()) > 0);
  await fillPage(page);
  await rec.shot(page, 'Activity evaluation');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load', timeout: 60000 }).catch(() => {}),
    page.locator(SUBMIT).first().click()
  ]);
  await sleep(1500);
  check('evaluation thank-you names the Clinical Audit', (await page.locator('text=Thank you for completing the Clinical Audit').count()) > 0);
  await rec.shot(page, 'Evaluation thank-you');

  // 9. Reviewer approval, then the certificate.
  const approved = php('approve.php', USER, 'Panwar-education');
  check('reviewer approval completes the course', /approved .* complete=yes/.test(approved), approved.trim());
  const certUrl = `${SITE}/certificate/?course=${ids.course}&user=${ids.uid}`;
  const res = await context.request.get(certUrl, { maxRedirects: 5 });
  const body = await res.body();
  const isPdf = body.slice(0, 4).toString() === '%PDF';
  check('certificate PDF issued', res.ok() && isPdf, `${res.status()} ${res.headers()['content-type'] || ''} ${body.length} bytes`);
  if (isPdf) fs.writeFileSync(path.join(OUT, 'certificate.pdf'), body);

  rec.write();
  fs.writeFileSync(path.join(OUT, 'checks.json'), JSON.stringify(checks, null, 2));
  const failed = checks.filter((c) => !c.ok);
  console.log(`\n${checks.length - failed.length}/${checks.length} checks passed`);
  if (failed.length) console.log('FAILED: ' + failed.map((c) => c.name).join('; '));
  await browser.close();
}

main().catch((e) => { console.error(e); process.exit(1); });

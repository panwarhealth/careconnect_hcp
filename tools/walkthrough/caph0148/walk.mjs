// Plays the travel & diabetes case study end to end as a test user and records it as vector PDF
// pages for the client review copy: each stage as it appears, every pop-up over the page, the
// sorting activity's tick and wrong-card states, then the whole finished page.
//
//   node walk.mjs [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123] [--out out] [--headed]
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { createRecorder } from '../lib/vec.mjs';
import { parseArgs, createChecks, launch, login, goto, sleep } from '../lib/session.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const args = parseArgs();
const OUT = path.resolve(here, args.out || 'out');
const WIDTH = 1280;
const { check, write: writeChecks } = createChecks();

/**
 * Captures one band of the page as a vector PDF page. The whole page is printed, then cut to the
 * band by crop.py (build.sh), so nothing has to be shifted into place. The window is first made as
 * tall as the page: the theme sizes some blocks to the window height, and a print page that differs
 * from the window would lay them out differently from where they were measured. A pop-up is moved
 * from fixed into the page flow, centred over the section behind it, as the reader sees it.
 */
function createBands(rec) {
  return async function band(page, label, { sel, pad = 32, popupOver = null, fromTop = false, group }) {
    await page.evaluate(() => document.fonts?.ready);
    await sleep(500);
    for (let i = 0; i < 4; i++) {
      const h = await page.evaluate(() => document.documentElement.scrollHeight);
      if (page.viewportSize().height === h) break;
      await page.setViewportSize({ width: WIDTH, height: h });
      await sleep(400);
    }
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    const box = await page.evaluate(({ sel, pad, popupOver, fromTop }) => {
      window.scrollTo(0, 0);
      const r = document.querySelector(popupOver || sel).getBoundingClientRect();
      if (fromTop) return { top: 0, height: r.bottom + pad };
      if (!popupOver) return { top: Math.max(0, r.top - pad), height: r.height + pad * 2 };
      const top = Math.max(0, r.top + r.height / 2 - 450);
      document.querySelector('.cs-modal').style.cssText = `position:absolute;top:${top}px;left:0;right:0;bottom:auto;height:900px;`;
      return { top, height: 900 };
    }, { sel, pad, popupOver, fromTop });
    const file = `${String(rec.manifest.length + 1).padStart(3, '0')}_${label.toLowerCase().replace(/[^a-z0-9]+/g, '_').slice(0, 50)}.pdf`;
    await page.pdf({ path: path.join(OUT, file), width: `${WIDTH}px`, height: `${height + 4}px`, printBackground: true, pageRanges: '1' });
    await page.evaluate(() => { document.querySelector('.cs-modal').style.cssText = ''; });
    await page.setViewportSize({ width: WIDTH, height: 900 });
    rec.manifest.push({ file, label, group, section: null, url: page.url(), crop: [box.top, box.height] });
    console.log(`  [${String(rec.manifest.length).padStart(3, '0')}] ${label}`);
  };
}

async function main() {
  const rec = createRecorder(OUT, { width: WIDTH });
  const band = createBands(rec);
  const { browser, page } = await launch({ headed: !!args.headed, width: WIDTH, height: 900 });
  await login(page, args);
  await goto(page, args.site + '/blog/travelling-with-diabetes/');
  await page.waitForSelector('[data-cs]');
  // Print ignores backface-visibility, so the flip cards would show both faces at once.
  await page.addStyleTag({ content: '.cs-flip:not(.is-flipped) .cs-flip__back, .cs-flip.is-flipped .cs-flip__front { visibility: hidden; }' });

  const sec = (name) => `[data-cs-sec][data-name="${name}"]`;
  const modalOpen = () => page.locator('.cs-modal:not([hidden])').count().then((c) => c === 1);
  const modalText = () => page.locator('.cs-modal__body').textContent().then((t) => t.replace(/ /g, ' ').trim());
  const modalContinue = async () => { await page.click('.cs-modal .cs-btn'); await sleep(900); };
  // A pop-up shot shows the window the reader sees: the section behind it, centred.
  const popup = async (label, behind, group) => { await sleep(500); await band(page, label, { popupOver: behind, group }); };
  const section = (label, sel, group) => band(page, label, { sel, group });

  /* intro and Meet Jess */
  await band(page, 'Intro', { sel: sec('intro'), fromTop: true, group: 'Start' });
  await page.click(`${sec('intro')} [data-cs-continue]`); await sleep(1000);
  check('progress bar names the steps', (await page.textContent('[data-cs-progress]')).replace(/\u00a0/g, ' ').includes('Identify the risks'));
  await section('Meet your patient', sec('meet_jess'), 'Start');

  /* Identify the risks */
  await page.click(`${sec('meet_jess')} [data-cs-continue]`); await sleep(1200);
  await page.waitForFunction(() => [...document.querySelectorAll('.cs-flip img')].every((i) => i.complete && i.naturalWidth));
  await section('Clue cards', sec('investigate'), 'Identify the risks');
  for (const c of await page.$$('.cs-flip')) { await c.click(); await sleep(150); }
  await sleep(700);
  await section('Clue cards turned over', sec('investigate'), 'Identify the risks');
  await page.click(`${sec('investigate')} [data-cs-continue]`); await sleep(1000);
  await section('Factors question', sec('factors_question'), 'Identify the risks');
  await page.click('.cs-opt:has-text("Medications")');
  await page.click(`${sec('factors_question')} [data-cs-check]`);
  check('wrong factor pop-up', await modalOpen() && (await modalText()).startsWith('Perhaps'));
  await popup('Pop-up: incorrect', sec('factors_question'), 'Identify the risks');
  await modalContinue();
  await page.click('.cs-opt:has-text("All the above")');
  await page.click(`${sec('factors_question')} [data-cs-check]`);
  check('right factor pop-up', (await modalText()).startsWith('Great choice'));
  await popup('Pop-up: Great choice!', sec('factors_question'), 'Identify the risks');
  await modalContinue();

  /* Decide the discussion */
  await section('Sorting activity', sec('discuss'), 'Decide the discussion');
  const tapTo = async (text, col) => {
    await page.locator('[data-cs-card]').filter({ hasText: text }).first().click();
    await page.click(`[data-cs-col="${col}"] [data-cs-drop]`);
    await sleep(200);
  };
  await tapTo('Medication readiness', 'top');
  await tapTo('Food and water', 'time');
  // Hold the wrong card's return to the list until it has been captured.
  await page.evaluate(() => {
    const real = window.setTimeout;
    window.__held = [];
    window.setTimeout = (fn, ms, ...a) => (ms === 1000 ? (window.__held.push(fn), 0) : real(fn, ms, ...a));
    window.__restore = () => { window.setTimeout = real; window.__held.forEach((f) => f()); };
  });
  await tapTo('Skin check', 'top');
  check('wrong card marked', await page.locator('[data-cs-card].is-wrong').count() === 1);
  await section('Right cards ticked, a wrong card marked before it returns', sec('discuss'), 'Decide the discussion');
  await page.evaluate(() => window.__restore()); await sleep(300);
  for (const [t, c] of [['Hydration', 'top'], ['Diabetes sick day', 'top'], ['Travel insurance', 'time'], ['Mosquito', 'time'], ['Skin check', 'defer'], ['Mammo', 'defer']]) await tapTo(t, c);
  await sleep(500);
  check('no pop-up until Continue', !(await modalOpen()));
  await section('All cards placed', sec('discuss'), 'Decide the discussion');
  await page.click(`${sec('discuss')} [data-cs-continue]`);
  check('Nicely done on Continue', (await modalText()).startsWith('Nicely done'));
  await popup('Pop-up: Nicely done!', sec('discuss'), 'Decide the discussion');
  await modalContinue();

  /* Prepare the plan */
  const rows = `${sec('prepare')} [data-cs-row]`;
  await section('Sick day plan questions', sec('prepare'), 'Prepare the plan');
  for (let j = 0; j < 5; j++) {
    const pick = j === 0 || j === 2 ? '.cs-opt[data-correct]' : '.cs-opt:not([data-correct])';
    await page.locator(rows).nth(j).locator(pick).click();
  }
  await page.click(`${sec('prepare')} [data-cs-check]`);
  check('plan wrong pop-up', (await modalText()).startsWith('Some answers'));
  await popup('Pop-up: incorrect', sec('prepare'), 'Prepare the plan');
  await modalContinue();
  await section('Questions to revisit outlined', sec('prepare'), 'Prepare the plan');
  for (let j = 0; j < 5; j++) await page.locator(rows).nth(j).locator('.cs-opt[data-correct]').click();
  await page.click(`${sec('prepare')} [data-cs-check]`);
  check('plan right pop-up', (await modalText()).startsWith('Perfect'));
  await popup('Pop-up: Perfect!', sec('prepare'), 'Prepare the plan');
  await modalContinue();
  await sleep(800);

  /* the finished page, top to bottom (last: the recorder unsticks fixed elements) */
  check('closing shown', await page.locator('[data-cs-closing]').isVisible());
  await rec.shot(page, 'Completed page, top to bottom');

  rec.write();
  const failed = writeChecks(OUT);
  await browser.close();
  if (failed) process.exit(1);
}

main().catch((e) => { console.error(e); process.exit(1); });

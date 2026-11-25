// Plays the travel & diabetes case study as a test user and records the client review copy in the
// same shape as v1: the completed page top to bottom, then the page behind each pop-up (activity 1
// right, sorting right, plan wrong, plan right), every page full length and vector.
//
//   node walk.mjs [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123] [--out out] [--headed]
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { parseArgs, createChecks, launch, login, goto, sleep } from '../lib/session.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const args = parseArgs();
const OUT = path.resolve(here, args.out || 'out');
const WIDTH = 1280;
const { check, write: writeChecks } = createChecks();

/**
 * Prints the whole page as one vector PDF page. The window is first made as tall as the page: the
 * theme sizes some blocks to the window height, and a print taller than the window would lay them
 * out differently. An open pop-up is taken out of fixed positioning and drawn over the section
 * behind it, with its backdrop over the whole page, as the reader sees it on screen.
 */
async function capture(page, file, popupOver = null) {
  await page.evaluate(() => document.fonts?.ready);
  // Lazy images below the fold would otherwise print as empty boxes.
  await page.evaluate(() => Promise.all([...document.images].map((img) => {
    img.loading = 'eager';
    return img.decode().catch(() => {});
  })));
  await sleep(600);
  for (let i = 0; i < 4; i++) {
    const h = await page.evaluate(() => document.documentElement.scrollHeight);
    if (page.viewportSize().height === h) break;
    await page.setViewportSize({ width: WIDTH, height: h });
    await sleep(400);
  }
  const height = await page.evaluate((popupOver) => {
    window.scrollTo(0, 0);
    const doc = document.documentElement.scrollHeight;
    if (popupOver) {
      const r = document.querySelector(popupOver).getBoundingClientRect();
      const modal = document.querySelector('.cs-modal');
      const box = modal.querySelector('.cs-modal__box');
      modal.style.cssText = `position:absolute;top:0;left:0;right:0;bottom:auto;height:${doc}px;display:block;padding:0;`;
      const top = Math.max(24, r.top + r.height / 2 - box.offsetHeight / 2);
      box.style.cssText = `position:absolute;top:${top}px;bottom:auto;left:50%;right:auto;width:100%;max-width:640px;transform:translateX(-50%);animation:none;`;
    }
    return doc;
  }, popupOver);
  check(`${file}: every image loaded`, await page.evaluate(() => [...document.images].filter((i) => i.offsetParent && i.getAttribute('src')).every((i) => i.complete && i.naturalWidth > 0)));
  await page.pdf({ path: path.join(OUT, file), width: `${WIDTH}px`, height: `${height + 4}px`, printBackground: true, pageRanges: '1' });
  await page.evaluate(() => {
    const modal = document.querySelector('.cs-modal');
    modal.style.cssText = '';
    modal.querySelector('.cs-modal__box').style.cssText = '';
  });
  await page.setViewportSize({ width: WIDTH, height: 900 });
  console.log(`  ${file}  (h=${height})`);
}

async function main() {
  fs.rmSync(OUT, { recursive: true, force: true });
  fs.mkdirSync(OUT, { recursive: true });
  const { browser, page } = await launch({ headed: !!args.headed, width: WIDTH, height: 900 });
  await login(page, args);
  await goto(page, args.site + '/blog/travelling-with-diabetes/');
  await page.waitForSelector('[data-cs]');
  // Print ignores backface-visibility, so the flip cards would show both faces at once.
  await page.addStyleTag({ content: '.cs-flip:not(.is-flipped) .cs-flip__back, .cs-flip.is-flipped .cs-flip__front { visibility: hidden; }' });

  const sec = (name) => `[data-cs-sec][data-name="${name}"]`;
  const modalText = () => page.locator('.cs-modal__body').textContent().then((t) => t.replace(/ /g, ' ').trim());
  const modalContinue = async () => { await page.click('.cs-modal .cs-btn'); await sleep(900); };
  const tapTo = async (text, col) => {
    await page.locator('[data-cs-card]').filter({ hasText: text }).first().click();
    await page.click(`[data-cs-col="${col}"] [data-cs-drop]`);
    await sleep(200);
  };

  await page.click(`${sec('intro')} [data-cs-continue]`); await sleep(1000);
  await page.click(`${sec('meet_jess')} [data-cs-continue]`); await sleep(1000);
  for (const c of await page.$$('.cs-flip')) { await c.click(); await sleep(150); }
  await page.click(`${sec('investigate')} [data-cs-continue]`); await sleep(1000);

  // Activity 1 pop up
  await page.click('.cs-opt:has-text("All the above")');
  await page.click(`${sec('factors_question')} [data-cs-check]`);
  check('Great choice pop-up', (await modalText()).startsWith('Great choice'));
  await capture(page, '2_activity_1_pop_up.pdf', sec('factors_question'));
  await modalContinue();

  // Pop up 2
  for (const [t, c] of [['Medication readiness', 'top'], ['Hydration', 'top'], ['Diabetes sick day', 'top'], ['Food and water', 'time'], ['Travel insurance', 'time'], ['Mosquito', 'time'], ['Skin check', 'defer'], ['Mammo', 'defer']]) await tapTo(t, c);
  await sleep(500);
  await page.click(`${sec('discuss')} [data-cs-continue]`);
  check('Nicely done pop-up', (await modalText()).startsWith('Nicely done'));
  await capture(page, '3_pop_up_2.pdf', sec('discuss'));
  await modalContinue();

  // Pop up 3 error
  const rows = `${sec('prepare')} [data-cs-row]`;
  for (let j = 0; j < 5; j++) {
    const pick = j === 0 || j === 2 ? '.cs-opt[data-correct]' : '.cs-opt:not([data-correct])';
    await page.locator(rows).nth(j).locator(pick).click();
  }
  await page.click(`${sec('prepare')} [data-cs-check]`);
  check('plan error pop-up', (await modalText()).startsWith('Some answers'));
  await capture(page, '4_pop_up_3_error.pdf', sec('prepare'));
  await modalContinue();

  // Pop up 3 correct
  for (let j = 0; j < 5; j++) await page.locator(rows).nth(j).locator('.cs-opt[data-correct]').click();
  await page.click(`${sec('prepare')} [data-cs-check]`);
  check('Perfect pop-up', (await modalText()).startsWith('Perfect'));
  await capture(page, '5_pop_up_3_correct.pdf', sec('prepare'));
  await modalContinue();

  // The completed page, first in the PDF
  await sleep(800);
  check('closing shown', await page.locator('[data-cs-closing]').isVisible());
  await capture(page, '1_case_study.pdf');

  const failed = writeChecks(OUT);
  await browser.close();
  if (failed) process.exit(1);
}

main().catch((e) => { console.error(e); process.exit(1); });

// Plays the travel & diabetes case study end to end at four screen sizes (desktop, laptop, two
// phones), including wrong answers, auto-marked sorting and a finger-style drag, and prints PASS/FAIL per size.
// Screenshots land in caph0148/out/.   node caph0148/checks.mjs [--only=iphone]
import fs from 'fs';
import { launch, login, goto, sleep, parseArgs } from '../lib/session.mjs';

const args = parseArgs();
const OUT = new URL('./out/', import.meta.url).pathname;
fs.mkdirSync(OUT, { recursive: true });
const sizes = [['desk', 1440, 900], ['laptop', 1366, 768], ['iphone', 390, 664], ['android', 360, 640]];
const only = process.argv.find(a => a.startsWith('--only='))?.slice(7);
for (const [n, w, h] of sizes.filter(s => !only || s[0] === only)) {
  const { browser, page } = await launch({ width: w, height: h });
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const gaHits = []; page.on('request', r => { if (/google-analytics\.com\/g\/collect/.test(r.url()) && /case_study/.test(r.url() + (r.postData() || ''))) gaHits.push(r.url()); });
  const log = [];
  const ok = (name, cond, detail = '') => log.push(`${cond ? 'PASS' : 'FAIL'} ${name}${detail ? ' (' + detail + ')' : ''}`);
  await login(page, args);
  await goto(page, args.site + '/blog/travelling-with-diabetes/');
  const js = (fn, a) => page.evaluate(fn, a);
  const visibleSecs = () => js(() => [...document.querySelectorAll('[data-cs-sec]')].filter(s => !s.hidden).length);
  // where the latest section landed: its top relative to the bottom of the sticky bars
  // where the latest section landed: centred in the visible area when it fits, else 16px under the bars
  const landing = () => js(() => {
    const secs = [...document.querySelectorAll('[data-cs-sec], [data-cs-closing]')].filter(s => !s.hidden);
    const last = secs[secs.length - 1];
    const hdr = document.getElementById('header'); const hp = getComputedStyle(hdr).position;
    const p = document.querySelector('[data-cs-progress]');
    const inset = (hp === 'sticky' || hp === 'fixed' ? hdr.offsetHeight : 0) + (p.hidden ? 0 : p.getBoundingClientRect().height);
    const r = last.getBoundingClientRect();
    const spare = innerHeight - inset - r.height;
    const want = spare > 32 ? spare / 2 : 16;
    return Math.round(r.top - inset - want);
  });
  const progressTop = () => js(() => { const p = document.querySelector('[data-cs-progress]'); return p.hidden ? null : Math.round(p.getBoundingClientRect().top); });
  const modalText = async () => (await page.locator('.cs-modal__body').textContent()).replace(/\u00a0/g, ' ').trim().slice(0, 40);
  const modalBtn = async () => { await page.click('.cs-modal .cs-btn'); await sleep(900); };
  const shot = (label) => page.screenshot({ path: OUT + n + '-s-' + label + '.png' });

  ok('only intro visible at start', await visibleSecs() === 1);
  ok('progress hidden at start', await progressTop() === null);
  await shot('0-intro');
  await page.click('[data-cs-sec][data-step="0"] [data-cs-continue]'); await sleep(1000);
  ok('Start reveals Meet Jess', await visibleSecs() === 2);
  ok('Meet Jess lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  await shot('1-jess');
  await page.click('[data-cs-sec][data-step="1"] [data-cs-continue]'); await sleep(1000);
  ok('Learn more reveals Investigate', await visibleSecs() === 3);
  const flipsCont = page.locator('[data-cs-sec][data-step="2"] >> nth=0 >> [data-cs-continue]');
  ok('Investigate Continue disabled before flips', await flipsCont.isDisabled());
  for (const c of await page.$$('.cs-flip')) await c.click();
  ok('Investigate Continue enabled after 4 flips', await flipsCont.isEnabled());
  await shot('2-flips');
  await flipsCont.click(); await sleep(1000);
  ok('question revealed', await visibleSecs() === 4);
  ok('question lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  const pt = await progressTop();
  ok('progress bar stuck at top', pt !== null && pt >= 0 && pt <= 100, 'top ' + pt);
  await page.click('.cs-opt:has-text("Medications")'); await page.click('[data-cs-quiz][data-right="factor-right"] [data-cs-check]'); await sleep(300);
  ok('wrong factor -> try again', (await modalText()).startsWith('Perhaps'));
  await modalBtn();
  await page.click('.cs-opt:has-text("All the above")'); await page.click('[data-cs-quiz][data-right="factor-right"] [data-cs-check]'); await sleep(300);
  ok('right factor -> explanation', (await modalText()).startsWith('Great choice'));
  await shot('3-right-modal');
  ok('pop-ups have no close cross', await page.locator('.cs-modal__x').count() === 0);
  ok('right answer headings are blue', await js(() => getComputedStyle(document.querySelector('.cs-modal .cs-msg__title')).color) === 'rgb(0, 179, 214)');
  await page.mouse.click(5, 5); await sleep(300);
  ok('backdrop click does not close', await page.locator('.cs-modal:not([hidden])').count() === 1);
  await modalBtn();
  ok('Discuss revealed', await visibleSecs() === 5);
  ok('Discuss lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  ok('no Check button in Discuss', await page.locator('[data-cs-sort] [data-cs-check]').count() === 0);
  await shot('4-discuss');
  const answers = await page.$$eval('[data-cs-card]', els => els.map(e => [e.textContent.replace(/\u00ad/g, ''), e.dataset.answer]));
  const cardEl = (t) => page.locator('[data-cs-card]').filter({ hasText: t.slice(0, 5) }).first();
  const tapTo = async (t, col) => { await cardEl(t).click(); await page.click('[data-cs-col="' + col + '"] [data-cs-drop]'); await sleep(150); };
  const isCorrect = (t) => cardEl(t).evaluate(e => e.classList.contains('is-correct'));
  // finger-style drag: hold near the bottom edge until the column scrolls into view, then drop on it
  const card0 = page.locator('[data-cs-card]').first();
  await card0.scrollIntoViewIfNeeded();
  const fb = await card0.boundingBox();
  const target = page.locator('[data-cs-col="' + answers[0][1] + '"] [data-cs-list]');
  await page.mouse.move(fb.x + 10, fb.y + 10); await page.mouse.down(); await page.mouse.move(fb.x + 30, fb.y + 30, { steps: 4 });
  for (let i = 0; i < 40; i++) {
    const tb = await target.boundingBox();
    if (tb.y + 30 < h - 80) { await page.mouse.move(tb.x + 15, tb.y + 25, { steps: 6 }); break; }
    await page.mouse.move(fb.x + 30, h - 20, { steps: 2 }); await sleep(100);
  }
  await sleep(150); await page.mouse.up(); await sleep(300);
  const dragged = await page.$eval('[data-cs-col="' + answers[0][1] + '"]', (c, t) => c.textContent.replace(/\u00ad/g, '').includes(t), answers[0][0]);
  ok('drag places a card', dragged);
  if (!dragged) await tapTo(answers[0][0], answers[0][1]);
  ok('right column -> card ticked', await isCorrect(answers[0][0]));
  ok('ticked card is blue', await cardEl(answers[0][0]).evaluate(e => getComputedStyle(e).borderColor) === 'rgb(0, 179, 214)');
  ok('ticked card is fixed in place', await cardEl(answers[0][0]).isDisabled());
  const inPool = (t) => page.$eval('[data-cs-pool]', (p, t) => p.textContent.replace(/\u00ad/g, '').includes(t), t);
  await cardEl('Skin check').click(); await page.click('[data-cs-col="time"] [data-cs-drop]'); await sleep(150);
  ok('wrong column -> card marked wrong', await cardEl('Skin check').evaluate(e => e.classList.contains('is-wrong') && getComputedStyle(e).borderColor === 'rgb(214, 69, 69)'));
  await sleep(1200);
  ok('wrong card returns to the list', await inPool('Skin check') && !(await isCorrect('Skin check')));
  for (const [t, a] of answers.slice(1)) if (t !== 'Skin check') await tapTo(t, a);
  await cardEl('Skin check').click(); await page.click('[data-cs-col="top"] [data-cs-drop]'); await sleep(1300);
  ok('full column: no pop-up, card returns', (await page.locator('.cs-modal:not([hidden])').count()) === 0 && await inPool('Skin check'));
  await shot('5-sort-ticks');
  await tapTo('Skin check', 'defer'); await sleep(700);
  ok('all right -> no pop-up until Continue', (await page.locator('.cs-modal:not([hidden])').count()) === 0);
  const sortCont = page.locator('[data-cs-sec][data-step="3"] [data-cs-continue]');
  ok('Continue shown when all right', await sortCont.isVisible());
  await sortCont.click(); await sleep(300);
  ok('empty pool reads "All cards placed."', await js(() => { const l = document.querySelector('[data-cs-pool].is-empty [data-cs-list]'); return !!l && getComputedStyle(l, '::before').content === '"All cards placed."'; }));
  ok('last right card -> nicely done', (await modalText()).startsWith('Nicely done')); await modalBtn();
  ok('Prepare revealed', await visibleSecs() === 6);
  ok('Prepare lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  const planCheck = page.locator('[data-cs-quiz][data-right="plan-right"] [data-cs-check]');
  const rowSel = '[data-cs-quiz][data-right="plan-right"] [data-cs-row]';
  const visibleRows = () => page.$$eval(rowSel, els => els.filter(e => !e.hidden).length);
  ok('Prepare shows every question at once', await visibleRows() === 5);
  ok('Check disabled until all answered', await planCheck.isDisabled());
  // questions 1 and 3 right, the other three wrong
  for (let j = 0; j < 5; j++) {
    const pick = j === 0 || j === 2 ? '.cs-opt[data-correct]' : '.cs-opt:not([data-correct])';
    await page.locator(rowSel).nth(j).locator(pick).click(); await sleep(100);
  }
  ok('Check enabled after the fifth answer', await planCheck.isEnabled());
  await planCheck.click(); await sleep(300);
  ok('wrong plan -> some answers', (await modalText()).startsWith('Some answers aren’t quite')); await modalBtn();
  const wrongRows = () => page.$$eval(rowSel, els => els.map((e, i) => e.classList.contains('is-wrong') ? i + 1 : 0).filter(Boolean).join(','));
  ok('only the wrong questions are outlined', await wrongRows() === '2,4,5', 'outlined ' + await wrongRows());
  await page.locator(rowSel).nth(1).locator('.cs-opt[data-correct]').click(); await sleep(200);
  ok('changing an answer clears its outline', await wrongRows() === '4,5', 'outlined ' + await wrongRows());
  for (let j = 0; j < 5; j++) await page.locator(rowSel).nth(j).locator('.cs-opt[data-correct]').click();
  await sleep(300);
  await shot('6-plan');
  await planCheck.click(); await sleep(300);
  ok('right plan -> perfect', (await modalText()).startsWith('Perfect')); await modalBtn(); await sleep(300);
  ok('closing revealed', await page.locator('[data-cs-closing]').isVisible());
  ok('closing lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  await shot('7-closing');
  ok('earlier sections still on the page', await visibleSecs() === 6);
  // analytics: events are logged locally (not sent) in the order a reader triggers them
  await js(() => { const a = document.querySelector('a.cs-res'); a.addEventListener('click', e => e.preventDefault(), { once: true }); a.click(); });
  const events = await js(() => (window.hcpCaseStudyEvents || []).map(([n, p]) => [n, p.cs_step || p.cs_result || p.cs_resource || '', p.cs_result || '', p.case_study]));
  const names = events.map(e => e[0]);
  ok('GA: first event is case_study_start', names[0] === 'case_study_start', names.slice(0, 3).join(','));
  const steps = events.filter(e => e[0] === 'case_study_step').map(e => e[1]).join(',');
  ok('GA: a step event for every section', steps === 'meet_jess,investigate,factors_question,discuss,prepare', steps);
  const answerEvents = events.filter(e => e[0] === 'case_study_answer');
  ok('GA: answers logged with right/wrong', answerEvents.some(e => e[2] === 'incorrect') && answerEvents.some(e => e[2] === 'correct'), answerEvents.length + ' answers');
  const sortAns = answerEvents.filter(e => e[1] === 'discuss');
  ok('GA: sort logged once, as correct', sortAns.length === 1 && sortAns[0][2] === 'correct', sortAns.length + ' sort answers');
  ok('GA: completion logged', names.includes('case_study_complete'));
  ok('GA: resource click logged', events.some(e => e[0] === 'case_study_resource' && e[1] === 'Diabetes Sick Day Care Plan'));
  ok('GA: every event tagged with the case study', events.every(e => e[3] === 'travelling-with-diabetes'));
  ok('GA: nothing sent to Google from local', gaHits.length === 0, gaHits.length + ' hits');
  ok('no horizontal overflow', !(await js(() => document.documentElement.scrollWidth > innerWidth)));
  ok('no script errors', errs.length === 0, errs.join(' | '));
  const fails = log.filter(l => l.startsWith('FAIL'));
  console.log(`== ${n} ${w}x${h}: ${log.length - fails.length}/${log.length} passed` + (fails.length ? '\n  ' + fails.join('\n  ') : ''));
  await browser.close();
}

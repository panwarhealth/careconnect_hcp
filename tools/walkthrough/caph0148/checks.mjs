// Plays the travel & diabetes case study end to end at four screen sizes (desktop, laptop, two
// phones), including wrong answers, the hint and a finger-style drag, and prints PASS/FAIL per size.
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
  // close with X instead of Continue: in-section Continue must appear and work
  await page.click('.cs-modal__x'); await sleep(400);
  ok('closing with X stays put', await visibleSecs() === 4);
  const qCont = page.locator('[data-cs-sec][data-step="2"] >> nth=1 >> [data-cs-continue]');
  ok('in-section Continue shown after X', await qCont.isVisible());
  await qCont.click(); await sleep(1000);
  ok('Discuss revealed', await visibleSecs() === 5);
  ok('Discuss lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  await shot('4-discuss');
  const sortCheck = () => page.click('[data-cs-sort] [data-cs-check]');
  await sortCheck(); await sleep(300);
  ok('check with cards unplaced -> place all', (await modalText()).startsWith('Place all cards')); await modalBtn();
  const answers = await page.$$eval('[data-cs-card]', els => els.map(e => [e.textContent.replace(/­/g, ''), e.dataset.answer]));
  const tapTo = async (t, col) => { await page.locator('[data-cs-card]').filter({ hasText: t.slice(0, 5) }).first().click(); await page.click('[data-cs-col="' + col + '"] [data-cs-drop]'); };
  // real drag of the first card into its column (touch-sized pointer path), relying on on-screen targets
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
  const dragged = await page.$eval('[data-cs-col="' + answers[0][1] + '"]', (c, t) => c.textContent.replace(/­/g, '').includes(t), answers[0][0]);
  ok('drag places a card', dragged);
  if (!dragged) await tapTo(answers[0][0], answers[0][1]);
  // wrong sort twice -> hint
  const wrongCol = { top: 'defer', time: 'top', defer: 'time' };
  for (const [t, a] of answers.slice(1)) await tapTo(t, a);
  // make it wrong: swap one top card and one defer card
  await tapTo('Skin check', 'top'); await sleep(200);
  ok('4th card into a full column -> column full', (await page.locator('.cs-modal:not([hidden])').count()) === 1 && (await modalText()).startsWith('This column is full')); await modalBtn();
  await tapTo('Medication readiness', 'defer'); await tapTo('Skin check', 'top');
  await sortCheck(); await sleep(300);
  ok('first wrong sort -> try again', (await modalText()).startsWith('Perhaps you could')); await modalBtn();
  await sortCheck(); await sleep(300);
  ok('second wrong sort -> hint offer', (await modalText()).startsWith('Not quite')); await modalBtn();
  const left = await page.$$eval('[data-cs-pool] [data-cs-card]', els => els.map(e => e.textContent.replace(/­/g, '')));
  ok('hint leaves two cards', left.length === 2, left.join(', '));
  await shot('5-hint');
  for (const t of left) { const a = answers.find(x => x[0] === t)[1]; await tapTo(t, a); }
  await sortCheck(); await sleep(300);
  ok('correct sort -> nicely done', (await modalText()).startsWith('Nicely done')); await modalBtn();
  ok('Prepare revealed', await visibleSecs() === 6);
  ok('Prepare lands centred or top-aligned', Math.abs(await landing()) <= 4, 'offset ' + await landing());
  const planCheck = page.locator('[data-cs-quiz][data-right="plan-right"] [data-cs-check]');
  const rowSel = '[data-cs-quiz][data-right="plan-right"] [data-cs-row]';
  const visibleRows = () => page.$$eval(rowSel, els => els.filter(e => !e.hidden).length);
  ok('Prepare shows one question at first', await visibleRows() === 1);
  ok('Prepare check hidden until last answered', !(await planCheck.isVisible()));
  // answer every question wrong, one at a time, checking each next one appears and is centred/top-aligned
  let landedOk = true;
  // questions 1 and 3 right, the other three wrong
  for (let j = 0; j < 5; j++) {
    const pick = j === 0 || j === 2 ? '.cs-opt[data-correct]' : '.cs-opt:not([data-correct])';
    await page.locator(rowSel).nth(j).locator(pick).click(); await sleep(1100);
    if (j < 4 && await visibleRows() !== j + 2) landedOk = false;
  }
  ok('each answer reveals the next question', landedOk && await visibleRows() === 5);
  ok('Check appears after the fifth answer', await planCheck.isVisible() && await planCheck.isEnabled());
  await planCheck.click(); await sleep(300);
  ok('wrong plan -> not quite right', (await modalText()).startsWith('That’s not quite')); await modalBtn();
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
  await js(() => { const a = document.querySelector('.cs-res a.cs-btn'); a.addEventListener('click', e => e.preventDefault(), { once: true }); a.click(); });
  const events = await js(() => (window.hcpCaseStudyEvents || []).map(([n, p]) => [n, p.cs_step || p.cs_result || p.cs_resource || '', p.cs_result || '', p.case_study]));
  const names = events.map(e => e[0]);
  ok('GA: first event is case_study_start', names[0] === 'case_study_start', names.slice(0, 3).join(','));
  const steps = events.filter(e => e[0] === 'case_study_step').map(e => e[1]).join(',');
  ok('GA: a step event for every section', steps === 'meet_jess,investigate,factors_question,discuss,prepare', steps);
  const answerEvents = events.filter(e => e[0] === 'case_study_answer');
  ok('GA: answers logged with right/wrong', answerEvents.some(e => e[2] === 'incorrect') && answerEvents.some(e => e[2] === 'correct'), answerEvents.length + ' answers');
  ok('GA: hint logged', names.includes('case_study_hint'));
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

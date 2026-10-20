// Walks the CAPH0085 consent changes and records every screen as a vector PDF:
//   Terms of Use, Privacy Policy, registration tick box (Register page, MCA landing page),
//   consent pop-up (desktop + mobile), /consent-preferences/ in its three states,
//   and the preference on Edit Your Profile.
//
//   node walk.mjs [--site http://localhost:8080] [--user Rob-Panwar] [--pass staging123] [--out out] [--headed]
//
// Local only: the user's consent answers are cleared before and after, via PHP in the container.
import path from 'path';
import { fileURLToPath } from 'url';
import { createRecorder } from '../lib/vec.mjs';
import { parseArgs, isLocal, php, createChecks, launch, prepareContext, login, goto as open, sleep } from '../lib/session.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const args = parseArgs();
const SITE = args.site;
const OUT = path.resolve(here, args.out || 'out');
const MCA_LANDING = '/anal-fissures-breaking-the-cycle-and-the-stigma-landing/';
// The local copy of the MCA landing page has a broken inline script from other branch work, so
// its Register now pop-up is captured from staging, which runs the same code.
const MCA_SITE = (args['mca-site'] || 'https://hcp-staging-app.azurewebsites.net').replace(/\/$/, '');
const STATEMENT = 'Yes, I consent to receiving electronic communications from Care Pharmaceuticals';
// PDF order. Capture order differs (logged-out screens first), so the manifest is sorted by this.
const GROUPS = ['Terms of Use and Privacy Policy', 'Registration', 'Consent pop-up', 'Edit Your Profile', 'Consent update email: /consent-preferences/', 'Admin: Users > Marketing consent'];
// Shown on every page of the section, so a reviewer landing on any one of them knows why it is plain.
const EDM = 'The landing pages for the Consent update email. These pages have no logos or marketing content, so the email can legally be sent to all registered users. ';

if (!isLocal(SITE)) throw new Error('Local only: the walk clears consent answers via the local container.');

const { check, write: writeChecks } = createChecks();
const goto = (page, route) => open(page, SITE + route);
const reset = () => php(path.join(here, 'reset.php'), args.user);

async function popupOpen(page) {
  await page.waitForSelector('.hcp-popup.is-open', { timeout: 8000 }).catch(() => {});
  await sleep(700); // entrance transition
  return page.locator('.hcp-popup.is-open').count();
}

async function main() {
  reset();
  const rec = createRecorder(OUT);
  const { browser, context, page } = await launch({ headed: args.headed });

  // Logged out
  await goto(page, '/terms-of-use/');
  check('Terms: clause 8.2 numbered', (await page.locator('.hcp-legal__num', { hasText: /^8\.2$/ }).count()) === 1);
  check('Terms: no review notes left', !/Jodi/.test(await page.locator('.hcp-legal').innerText()));
  await rec.shot(page, 'Terms of Use', { group: 'Terms of Use and Privacy Policy' });

  await goto(page, '/privacy-policy/');
  check('Privacy: Last Updated set', /Last Updated: \d+\/\d+\/\d+/.test(await page.locator('.hcp-legal__updated').innerText()));
  check('Privacy: no review notes left', !/Jodi/.test(await page.locator('.hcp-legal').innerText()));
  await rec.shot(page, 'Privacy Policy', { group: 'Terms of Use and Privacy Policy' });

  await goto(page, '/register/');
  const box = page.locator('form:has(input[name="form_id"][value="2"]) input[type="checkbox"]').first();
  const submit = page.locator('form:has(input[name="form_id"][value="2"]) button[type="submit"], form:has(input[name="form_id"][value="2"]) input[type="submit"]').first();
  check('Register: Terms tick box present', (await box.count()) > 0);
  check('Register: button disabled until ticked', await submit.isDisabled());
  await box.scrollIntoViewIfNeeded();
  await rec.shot(page, 'Register page: Register disabled until the box is ticked', { group: 'Registration' });
  await box.check();
  check('Register: button enabled once ticked', await submit.isEnabled());
  await rec.shot(page, 'Register page: box ticked', { group: 'Registration' });

  const mca = await context.newPage();
  await mca.goto(MCA_SITE + MCA_LANDING, { waitUntil: 'load', timeout: 180000 });
  const mcaForm = await mca.locator('input[name="item_meta[2321]"][value*="completion-activity-homepage"]').count();
  check('MCA landing: uses Form 2, returns to the activity homepage', mcaForm > 0 && (await mca.locator('input[name="form_id"][value="129"]').count()) === 0);
  // The page's registration form opens in its own pop-up from "Register now".
  await mca.locator('.regBtn:visible').first().click();
  await mca.waitForSelector('#audit_popup_modal:visible', { timeout: 15000 }).catch(() => {});
  await sleep(800);
  check('MCA landing: Register now opens the form with the tick box', await mca.locator('#audit_popup_modal input[type="checkbox"]:visible').count() > 0);
  await rec.shot(mca, 'MCA landing page: Register now opens the same registration form', { group: 'Registration', viewport: true });
  await mca.close();

  await goto(page, '/consent-preferences/');
  check('Consent page (logged out): login form, no site header', (await page.locator('#rcp_user_login').count()) === 1 && (await page.locator('header').count()) === 0);
  await rec.shot(page, 'Logged out', { group: 'Consent update email: /consent-preferences/', caption: EDM + 'Shows when: not logged in. They log in and come back to this page.' });

  // Logged in, no answer on file
  await login(page, args);
  await goto(page, '/');
  check('Pop-up: consent pop-up shows', (await popupOpen(page)) === 1 && (await page.locator('#hcp-popup-caph0085-marketing-consent').count()) === 1);
  check('Pop-up: no close button', (await page.locator('.hcp-popup.is-open .hcp-popup__close').count()) === 0);
  check('Pop-up: lawyers\' statement', (await page.locator('.hcp-popup .hcp-consent-choice__statement').innerText()).startsWith(STATEMENT));
  check('Pop-up: MCA pop-up held back', (await page.locator('#hcp-popup-caph0150-mca-activity').count()) === 0);
  await page.keyboard.press('Escape');
  await page.mouse.click(20, 450);
  await sleep(500);
  check('Pop-up: Esc and outside click do not close it', (await page.locator('.hcp-popup.is-open').count()) === 1);
  await rec.shot(page, 'Consent pop-up: desktop', { group: 'Consent pop-up', viewport: true });

  const mobileContext = await prepareContext(await browser.newContext({ viewport: { width: 390, height: 844 }, storageState: await context.storageState() }));
  const mobile = await mobileContext.newPage();
  await mobile.goto(SITE + '/', { waitUntil: 'load' });
  await popupOpen(mobile);
  await rec.shot(mobile, 'Consent pop-up: mobile', { group: 'Consent pop-up', viewport: true });
  await mobileContext.close();

  await page.reload({ waitUntil: 'load' });
  check('Pop-up: still there after a reload', (await popupOpen(page)) === 1);

  await goto(page, '/consent-preferences/');
  check('Consent page: no pop-up on it', (await page.locator('.hcp-popup').count()) === 0);
  check('Consent page: asks the question', (await page.locator('[data-hcp-consent-choice]').count()) === 1);
  check('Consent page: no logo or benefits', (await page.locator('.hcp-consent-intro, .hcp-consent-landing img').count()) === 0);
  await rec.shot(page, 'Logged in, not answered yet', { group: 'Consent update email: /consent-preferences/', caption: EDM + 'Shows when: logged in, not answered yet.' });

  await page.click('[data-hcp-consent-choice] [data-answer="yes"]');
  await page.waitForSelector('[data-hcp-consent-saved]:not([hidden])', { timeout: 8000 }).catch(() => {});
  check('Consent page: saved confirmation', await page.locator('[data-hcp-consent-saved]').isVisible());
  await rec.shot(page, 'Straight after answering', { group: 'Consent update email: /consent-preferences/', caption: EDM + 'Shows when: straight after they click Yes or No.' });

  await goto(page, '/consent-preferences/');
  check('Consent page: answered state on return', (await page.locator('.hcp-consent-landing__done').count()) === 1);
  await rec.shot(page, 'Already answered', { group: 'Consent update email: /consent-preferences/', caption: EDM + 'Shows when: they open the link again after answering.' });

  await page.goto(SITE + '/wp-admin/users.php?page=hcp-consent', { waitUntil: 'load' });
  // Plugin nags and update notices aren't part of this page.
  await page.addStyleTag({ content: '#wpbody-content .notice, #wpbody-content .update-nag, #wpbody-content .updated, #wpbody-content .error, #wpbody-content [class*="rank-math"], #wpbody-content > div:not(.wrap):not(.clear) { display: none !important; }' });
  check('Admin: export page counts the Yes', /Yes \(on the send list\)\s*1\b/.test(await page.locator('.wrap table').innerText()));
  check('Admin: both download buttons', (await page.locator('.wrap a.button[href*="hcp_consent_export"]').count()) === 2);
  await rec.shot(page, 'Counts and CSV downloads', { group: 'Admin: Users > Marketing consent', caption: 'wp-admin > Users > Marketing consent (admins only). Send list = current Yes answers, for the eDM mailer.' });

  await goto(page, '/');
  await sleep(4000); // longer than the MCA pop-up's dwell
  check('Cool-down: no pop-up after answering', (await page.locator('.hcp-popup.is-open').count()) === 0);

  await goto(page, '/edit-your-profile/');
  const status = await page.locator('[data-hcp-consent-status]').innerText();
  check('Profile: shows the answer on file', /You answered Yes on/.test(status), status);
  await page.locator('.hcp-consent-pref').scrollIntoViewIfNeeded();
  await rec.shot(page, 'Edit Your Profile: communication preferences', { group: 'Edit Your Profile' });

  await page.click('.hcp-consent-pref [data-answer="no"]');
  await page.waitForFunction(() => /answered No/.test(document.querySelector('[data-hcp-consent-status]').textContent), null, { timeout: 8000 }).catch(() => {});
  check('Profile: change to No updates the status', /You answered No on/.test(await page.locator('[data-hcp-consent-status]').innerText()));
  await rec.shot(page, 'Edit Your Profile: changed to No', { group: 'Edit Your Profile' });

  rec.manifest.sort((a, b) => GROUPS.indexOf(a.group) - GROUPS.indexOf(b.group));
  rec.write();
  const failed = writeChecks(OUT);
  await browser.close();
  reset();
  process.exitCode = failed ? 1 : 0;
}

main().catch((e) => {
  console.error(e);
  reset();
  process.exit(1);
});

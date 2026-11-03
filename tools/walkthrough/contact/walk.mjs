// Records the Contact page with Care Pharma's new wording and the Customer Care and Order Samples links.
//   node walk.mjs [--site http://localhost:8080] [--out out] [--headed]
import path from 'path';
import { fileURLToPath } from 'url';
import { createRecorder } from '../lib/vec.mjs';
import { parseArgs, createChecks, launch, goto as open } from '../lib/session.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const args = parseArgs();
const SITE = args.site;
const OUT = path.resolve(here, args.out || 'out');
// The form's invisible reCAPTCHA shows a domain error box on localhost; on the live site it is only the corner badge.
// The theme's empty image lightbox prints a stray close button under the footer; not part of this change.
const HIDE_CAPTCHA = '.frm-g-recaptcha, .grecaptcha-badge, #lightboxModal { display: none !important; }';

async function main() {
  const { check, write: writeChecks } = createChecks();
  const rec = createRecorder(OUT);
  const { browser, page } = await launch({ headed: args.headed, height: 1000 });

  await open(page, SITE + '/contact/');
  const text = await page.locator('main, body').first().innerText();
  check('Banner: new question line', text.includes('Have a question about our healthcare professional resources, educational materials, marketing programs or other HCP initiatives?'));
  check('Banner: HCP Marketing team line', text.includes('Complete the form below and our HCP Marketing team will respond to your inquiry.'));
  check('Customer Care link', (await page.locator('a[href="https://carepharmaceuticals.com.au/contact-us/"]', { hasText: 'Customer Care team' }).count()) === 1);
  check('Order samples link', (await page.locator('a[href="/order-samples/"]', { hasText: 'Order samples online' }).count()) === 1);
  check('Adverse event box kept', text.includes('Reporting an adverse event?'));
  await page.addStyleTag({ content: HIDE_CAPTCHA });
  await rec.shot(page, 'Contact page', { group: 'Contact page' });

  rec.write();
  const failed = writeChecks(OUT);
  await browser.close();
  process.exitCode = failed ? 1 : 0;
}

main().catch((e) => { console.error(e); process.exit(1); });

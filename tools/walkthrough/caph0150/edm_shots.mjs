// Screenshots the two audit eDMs with their review header block, for edm_pdf.py.
import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const root = path.resolve('../../../site/EDM');
const out = path.resolve('out');
const date = process.argv[2];
const emails = [
  ['Audit_update_started_email', 'Updates to the Anal Fissure Management Mini Clinical Audit', 'Switch to a NEW streamlined version, or complete your existing activity by 31 December'],
  ['Audit_update_not_started_email', 'Continue your Anal Fissure Management CPD activity—NEW streamlined audit available now', 'Earn 6 hours of CPD by completing the online module and clinical audit'],
];
const row = (k, v) => `<div><b style="display:inline-block;width:72px">${k}:</b>${v}</div>`;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 680, height: 900 }, deviceScaleFactor: 2 });
for (const [file, subject, pre] of emails) {
  const head = '<div style="background:#fff;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;color:#000;padding:24px 0 28px;"><div style="max-width:600px;margin:0 auto;">'
    + row('Subject', subject) + row('Preheader', pre) + row('Date', `Preview, ${date} (not sent)`) + row('From', 'Anal Fissure CPD Activity') + row('To', 'Participant') + '</div></div>';
  const html = fs.readFileSync(path.join(root, file + '.html'), 'utf8').replace(/(<body[^>]*>)/, (m) => m + head);
  await page.setContent(html, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: path.join(out, `edm-${file}.png`), fullPage: true });
}
await browser.close();

// Shared plumbing for a walk script: arguments, browser, login, navigation, PASS/FAIL checks,
// and running PHP helpers inside the local wordpress container.
import { chromium } from 'playwright';
import { execFileSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const lib = path.dirname(fileURLToPath(import.meta.url));

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** --key value and bare --flag pairs, plus the defaults every walk shares. */
export function parseArgs(argv = process.argv.slice(2)) {
  const args = {};
  for (let i = 0; i < argv.length; i++) {
    const t = argv[i];
    if (!t.startsWith('--')) continue;
    if (argv[i + 1] && !argv[i + 1].startsWith('--')) args[t.slice(2)] = argv[++i];
    else args[t.slice(2)] = true;
  }
  args.site = (args.site || 'http://localhost:8080').replace(/\/$/, '');
  args.user = args.user || 'Rob-Panwar';
  args.pass = args.pass || 'staging123';
  return args;
}

export function isLocal(site) {
  return /localhost|127\.0\.0\.1/.test(site);
}

/** Runs a PHP script inside the local wordpress container and returns its stdout. */
export function php(script, ...a) {
  const out = execFileSync('bash', [path.join(lib, 'php.sh'), path.resolve(script), ...a], { encoding: 'utf8' });
  console.log('  php:', out.trim());
  return out;
}

export function createChecks() {
  const checks = [];
  return {
    checks,
    check(name, ok, detail = '') {
      checks.push({ name, ok: !!ok, detail });
      console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  (' + detail + ')' : ''}`);
    },
    /** Writes checks.json and prints the tally. Returns the number of failures. */
    write(outDir) {
      fs.writeFileSync(path.join(outDir, 'checks.json'), JSON.stringify(checks, null, 2));
      const failed = checks.filter((c) => !c.ok);
      console.log(`\n${checks.length - failed.length}/${checks.length} checks passed`);
      if (failed.length) console.log('FAILED: ' + failed.map((c) => c.name).join('; '));
      return failed.length;
    }
  };
}

/** Desktop browser with the admin bar and off-canvas mobile menu kept out of the captures. */
export async function launch({ headed = false, width = 1280, height = 900 } = {}) {
  const browser = await chromium.launch({ headless: !headed });
  const context = await browser.newContext({ viewport: { width, height } });
  // The recorder turns fixed elements static, so these would otherwise print inline.
  await context.addInitScript(() => {
    document.addEventListener('DOMContentLoaded', () => {
      const style = document.createElement('style');
      style.textContent = '#wpadminbar, .fixed.-top-full, .nojq { display: none !important; } html { margin-top: 0 !important; }';
      document.head.appendChild(style);
    });
  });
  const page = await context.newPage();
  page.on('pageerror', (e) => console.log('  ! page error:', e.message));
  return { browser, context, page };
}

export async function login(page, { site, user, pass }) {
  await page.goto(site + '/log-in/', { waitUntil: 'load' });
  await page.fill('#rcp_user_login', user);
  await page.fill('#rcp_user_pass', pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}), page.click('#rcp_login_submit')]);
  const loggedIn = await page.evaluate(() => document.body.classList.contains('logged-in'));
  if (!loggedIn) throw new Error('login failed for ' + user);
}

export async function dismissPopups(page) {
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('.modal.show .close, [class*="popup"] [aria-label="Close"], .hcp-popup__close')) el.click();
  }).catch(() => {});
}

export async function goto(page, url) {
  await page.goto(url, { waitUntil: 'load' });
  await sleep(600);
  await dismissPopups(page);
}

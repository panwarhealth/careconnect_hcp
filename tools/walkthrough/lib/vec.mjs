// Renders the current page as a single-page, full-height VECTOR PDF: real text at any zoom,
// which is what a client marking up a review copy needs. Screenshots would be flat images.
import fs from 'fs';
import path from 'path';

function slug(label) {
  return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 60);
}

/** Waits for the things that make a page render short or blank if you print too early. */
async function settlePage(page, settle) {
  await page.emulateMedia({ media: 'screen' });
  await page.waitForLoadState('networkidle').catch(() => {});
  await page
    .evaluate(async () => {
      await document.fonts?.ready;
      const images = [...document.images].filter((img) => !img.complete);
      await Promise.all(
        images.map(
          (img) =>
            new Promise((resolve) => {
              img.addEventListener('load', resolve, { once: true });
              img.addEventListener('error', resolve, { once: true });
              setTimeout(resolve, 4000);
            })
        )
      );
    })
    .catch(() => {});

  // A sticky or fixed element prints where the viewport happens to be, not where it belongs, so
  // on a full-height capture the site header landed in the middle of any page that had been
  // scrolled (opening a record does that) and covered the question underneath it. Scrolling back
  // is the direct fix; unsticking is the guard, and it costs no layout because a sticky element
  // already occupies its place in the flow.
  await page
    .evaluate(() => {
      window.scrollTo(0, 0);
      for (const el of document.querySelectorAll('*')) {
        const position = getComputedStyle(el).position;
        if (position === 'sticky' || position === 'fixed') {
          el.style.setProperty('position', 'static', 'important');
        }
      }
    })
    .catch(() => {});

  await page.waitForTimeout(settle);
}

export function createRecorder(outDir, { width = 1280, settle = 1400 } = {}) {
  fs.rmSync(outDir, { recursive: true, force: true });
  fs.mkdirSync(outDir, { recursive: true });
  const manifest = [];

  return {
    manifest,

    async shot(page, label, { section = null, group = null } = {}) {
      const n = manifest.length + 1;
      await settlePage(page, settle);
      const height = await page.evaluate(() => document.documentElement.scrollHeight);
      const file = `${String(n).padStart(3, '0')}_${slug(label)}.pdf`;
      await page.pdf({
        path: path.join(outDir, file),
        width: `${width}px`,
        height: `${height + 4}px`,
        printBackground: true,
        pageRanges: '1'
      });
      let route = page.url();
      try {
        route = new URL(page.url()).pathname;
      } catch {}
      manifest.push({ file, label, group, section, url: page.url() });
      console.log(`  [${String(n).padStart(3, '0')}] ${label}  <- ${route}  (h=${height})`);
      return file;
    },

    write() {
      fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
      console.log(`\n${manifest.length} screens -> ${outDir}/`);
    }
  };
}

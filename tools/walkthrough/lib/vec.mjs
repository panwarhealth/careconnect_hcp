// Renders the current page as a single-page, full-height VECTOR PDF: real text at any zoom,
// which is what a client marking up a review copy needs. Screenshots would be flat images.
import fs from 'fs';
import path from 'path';

function slug(label) {
  return label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 60);
}

/** Waits for the things that make a page render short or blank if you print too early. */
async function settlePage(page, settle, { keepFixed = false } = {}) {
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
  await flattenGradients(page).catch((e) => console.log('  ! gradient flatten:', e.message));

  if (keepFixed) {
    await page.waitForTimeout(settle);
    return;
  }

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

/**
 * CSS gradients print as PDF shading patterns, which Acrobat drops once the capture is scaled
 * onto A4 (the blue Register panel came out white). Swap each gradient for a screenshot of
 * itself, taken with its contents hidden, so it prints as an ordinary image in every viewer.
 * The element's text and children stay vector.
 */
async function flattenGradients(page) {
  const count = await page.evaluate(() => {
    let n = 0;
    for (const el of document.querySelectorAll('body *')) {
      if (el.closest('.hcp-popup')) continue;
      const bg = getComputedStyle(el).backgroundImage;
      if (bg && bg.includes('gradient(') && el.getClientRects().length) el.setAttribute('data-vec-gradient', String(n++));
    }
    return n;
  });
  for (let i = 0; i < count; i++) {
    const handle = await page.$(`[data-vec-gradient="${i}"]`);
    if (!handle) continue;
    const box = await handle.boundingBox();
    if (!box || box.width < 2 || box.height < 2) continue;
    await handle.evaluate((el) => {
      el.dataset.vecColor = el.style.color;
      el.style.color = 'transparent';
      for (const child of el.children) child.style.setProperty('visibility', 'hidden', 'important');
    });
    const png = await handle.screenshot({ type: 'png' }).catch(() => null);
    await handle.evaluate((el, data) => {
      el.style.color = el.dataset.vecColor;
      for (const child of el.children) child.style.removeProperty('visibility');
      if (data) {
        el.style.setProperty('background-image', `url(data:image/png;base64,${data})`, 'important');
        el.style.setProperty('background-size', '100% 100%', 'important');
        el.style.setProperty('background-repeat', 'no-repeat', 'important');
      }
    }, png ? png.toString('base64') : null);
  }
}

export function createRecorder(outDir, { width = 1280, settle = 1400 } = {}) {
  fs.rmSync(outDir, { recursive: true, force: true });
  fs.mkdirSync(outDir, { recursive: true });
  const manifest = [];

  return {
    manifest,

    /**
     * caption: a line printed above the screen in the PDF, e.g. when the screen shows.
     * viewport: capture only what is on screen, with fixed elements left in place. For modals
     * and overlays, which the full-height capture would flatten into the page flow.
     */
    async shot(page, label, { section = null, group = null, viewport = false, caption = null } = {}) {
      const n = manifest.length + 1;
      await settlePage(page, settle, { keepFixed: viewport });
      const height = viewport
        ? page.viewportSize().height
        : await page.evaluate(() => document.documentElement.scrollHeight);
      const pageWidth = viewport ? page.viewportSize().width : width;
      const file = `${String(n).padStart(3, '0')}_${slug(label)}.pdf`;
      await page.pdf({
        path: path.join(outDir, file),
        width: `${pageWidth}px`,
        height: `${height + (viewport ? 0 : 4)}px`,
        printBackground: true,
        pageRanges: '1'
      });
      let route = page.url();
      try {
        route = new URL(page.url()).pathname;
      } catch {}
      manifest.push({ file, label, group, section, caption, url: page.url() });
      console.log(`  [${String(n).padStart(3, '0')}] ${label}  <- ${route}  (h=${height})`);
      return file;
    },

    write() {
      fs.writeFileSync(path.join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
      console.log(`\n${manifest.length} screens -> ${outDir}/`);
    }
  };
}

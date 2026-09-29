// Opens dist/index.html straight from disk (file://), like a double-click, and reports what renders.
import { chromium } from 'playwright';
const b = await chromium.launch();
const p = await b.newPage({ viewport: { width: 1280, height: 800 } });
const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => m.type() === 'error' && errs.push(m.text()));
await p.goto(process.argv[2]);
for (const h of ['', 'work', 'work-palau', 'photography']) {
  await p.evaluate(h => { location.hash = h }, h); await p.waitForTimeout(1600);
  await p.evaluate(() => scrollTo(0, document.body.scrollHeight / 3)); await p.waitForTimeout(1200);
  const r = await p.evaluate(() => { const im = [...document.querySelectorAll('#app img')];
    return { imgs: im.length, loaded: im.filter(i => i.complete && i.naturalWidth > 0).length, logos: [...document.querySelectorAll('.logo img')].filter(i => i.naturalWidth > 0).length, gl: document.querySelectorAll('canvas.glc').length } });
  console.log((h || 'home').padEnd(12), JSON.stringify(r));
  await p.screenshot({ path: `/tmp/fc-${h || 'home'}.png` });
}
console.log('errors:', errs.slice(0, 5));
await b.close();

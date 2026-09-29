import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
const errs = []; p.on('pageerror', e => errs.push(e.message));
await p.goto(process.argv[2]); await p.waitForTimeout(1500);
for (const h of ['', 'work', 'photography', 'film', 'services', 'work-palau', 'work-hoiana', 'about']) {
  await p.evaluate(h => { location.hash = h }, h); await p.waitForTimeout(1200);
  const r = await p.evaluate(() => ({ frames: document.querySelectorAll('#app .frame').length, pal: document.querySelectorAll('#app .frame[data-pal]').length,
    filmLinks: document.querySelectorAll('#app a.film').length, filmPlay: document.querySelectorAll('#app button.film').length,
    photoLinks: document.querySelectorAll('#app a.plink').length, lightbox: document.querySelectorAll('#app [data-lb]').length }));
  console.log((h || 'home').padEnd(12), JSON.stringify(r));
}
console.log('errors', errs); await b.close();

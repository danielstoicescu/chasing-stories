// Renders pages, scrolls every frame into view so its palette squares get placed, then saves element shots.
import { chromium } from 'playwright';
const [url, out] = [process.argv[2], process.argv[3]];
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
const errs = []; p.on('pageerror', e => errs.push(e.message));
await p.goto(url); await p.waitForTimeout(1500);
await p.evaluate(() => { try { localStorage.setItem('cs-consent', JSON.stringify({ a: false, t: Date.now() })) } catch (e) { } document.getElementById('cookie').hidden = true });
let n = 0;
for (const h of ['', 'photography', 'work-sixsenses', 'work-bangkok', 'work-heritance']) {
  await p.evaluate(h => { location.hash = h }, h); await p.waitForTimeout(1300);
  const H = await p.evaluate(() => document.body.scrollHeight);
  for (let y = 0; y < H; y += 500) { await p.evaluate(y => window.scrollTo(0, y), y); await p.waitForTimeout(160) }
  await p.waitForTimeout(1500);
  const frames = await p.$$('.frame.sw-on');
  for (const f of frames.slice(0, 14)) { await f.scrollIntoViewIfNeeded(); await f.screenshot({ path: `${out}/f${String(n++).padStart(3, '0')}.png` }) }
}
// hover one palette chip
await p.evaluate(() => { location.hash = '' }); await p.waitForTimeout(1500);
const card = await p.$('.c1 .frame'); await card.scrollIntoViewIfNeeded(); await p.waitForTimeout(2500);
const pal = await p.$('.c1 .pal'); if (pal) { await pal.hover(); await p.waitForTimeout(900); await card.screenshot({ path: `${out}/hover.png` }) }
const stats = await p.evaluate(() => [...document.querySelectorAll('.frame.sw-on')].map(f => f.querySelectorAll('i.sw').length));
console.log('frames', n, 'squares per frame', JSON.stringify(stats.slice(0, 30)), 'errors', errs);
await b.close();

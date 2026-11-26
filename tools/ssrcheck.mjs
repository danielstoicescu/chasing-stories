import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } }); const errs = []; p.on('pageerror', e => errs.push(e.message));
for (const u of ['/', '/work', '/work/palau', '/contact']) { await p.goto('http://localhost:4210' + u); await p.waitForTimeout(1500);
  console.log(u, await p.evaluate(() => ({ ssrLeft: document.querySelectorAll('.ssr').length, page: !!document.querySelector('#app .page'), h1: (document.querySelector('#app h1') || {}).textContent }))) }
await p.goto('http://localhost:4210/work'); await p.waitForTimeout(4000);
console.log('work card videos:', await p.$$eval('.wgrid video.amb', v => v.map(x => x.src.split('/').pop())));
console.log(errs.length ? 'ERR ' + errs.join(' | ') : 'no js errors'); await b.close();

import { webkit, devices } from 'playwright';
const b = await webkit.launch(); const ctx = await b.newContext(devices['iPhone 13']); const p = await ctx.newPage();
await p.goto('https://www.chasingstories.org/'); await p.evaluate(() => { const c = document.getElementById('cookie'); if (c) c.hidden = true });
await p.waitForTimeout(4000); await p.screenshot({ path: '/tmp/m1.png' }); await p.waitForTimeout(3000); await p.screenshot({ path: '/tmp/m2.png' });
console.log(await p.evaluate(() => { const v = document.querySelector('.hero-vid'); return v ? v.currentTime.toFixed(1) + ' cls:' + v.className : 'none' }));
await b.close();

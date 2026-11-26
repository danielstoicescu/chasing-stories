import { chromium } from 'playwright';
const b = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] }); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
await p.goto('https://www.chasingstories.org/work'); await p.waitForTimeout(1500);
const card = p.locator('.wgrid .card', { hasText: 'St. Regis' }); await card.scrollIntoViewIfNeeded(); await p.waitForTimeout(4000);
console.log(await card.evaluate(c => { const v = c.querySelector('video.amb'); return v ? v.src.split('/').pop() + ' rs' + v.readyState + ' on:' + v.classList.contains('on') : 'no video' }));
await b.close();

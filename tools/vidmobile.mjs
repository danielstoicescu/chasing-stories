import { chromium, webkit, devices } from 'playwright';
const BASE = process.argv[2] || 'https://www.chasingstories.org';
const b = process.argv[3] === 'webkit' ? await webkit.launch() : await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] });
for (const [label, opts] of [['desktop', { viewport: { width: 1440, height: 900 } }], ['iphone', devices['iPhone 13']]]) {
  const ctx = await b.newContext(opts); const p = await ctx.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()) });
  for (const path of ['/', '/work/palau']) {
    await p.goto(BASE + path); await p.waitForTimeout(6000);
    const st = await p.evaluate(() => ({ vids: [...document.querySelectorAll('video')].filter(v => v.src).map(v => `${v.className}:${v.src.split('/').pop()} ${v.videoWidth}x${v.videoHeight} rs${v.readyState} conn:${v.isConnected}`),
      dv: (document.querySelector('.phero') || {}).dataset ? JSON.stringify({ ...document.querySelector('.phero').dataset }) : '', w: innerWidth, h: innerHeight }));
    console.log(label, path, JSON.stringify(st));
  }
  console.log(label, errs.length ? 'ERR ' + errs.join(' | ') : 'no errors'); await ctx.close();
}
await b.close();

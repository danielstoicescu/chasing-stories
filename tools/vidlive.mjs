// Live check: hero / project-hero films render through WebGL and thumbnails play.
import { chromium } from 'playwright';
const BASE = process.argv[2] || 'https://www.chasingstories.org', OUT = process.argv[3] || '/tmp';
const b = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required', '--use-gl=angle'] });
const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => m.type() === 'error' && errs.push(m.text()));
for (const path of ['/', '/work/palau', '/work/sixsenses', '/film']) {
  await p.goto(BASE + path); await p.evaluate(() => { const c = document.getElementById('cookie'); if (c) c.hidden = true });
  if (path === '/film') await p.evaluate(() => scrollTo(0, 700));
  await p.waitForTimeout(7000);
  const st = await p.evaluate(() => [...document.querySelectorAll('video')].filter(v => v.src).map(v => `${v.className}:${v.src.split('/').pop()} rs${v.readyState} t${v.currentTime.toFixed(1)}`));
  const shot = OUT + '/live' + path.replace(/\W+/g, '_') + '.png'; await p.screenshot({ path: shot });
  console.log(path, JSON.stringify(st), shot);
}
console.log(errs.length ? 'ERRORS ' + errs.join(' | ') : 'no errors'); await b.close();

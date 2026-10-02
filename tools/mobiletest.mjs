// iPhone (WebKit): home hero film and a panel-chosen landscape project film both play as real <video> over the poster, copy stays on top.
import { chromium, webkit, devices } from 'playwright'; import fs from 'fs';
const BASE = process.argv[2] || 'http://localhost:4210', SET = process.argv[3] !== 'noset';
if (SET) { const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
  const b0 = await chromium.launch(); const a = await b0.newPage();
  await a.goto(BASE + '/admin/login.php'); await a.fill('input[name=u]', cred.user); await a.fill('input[name=p]', cred.pass); await a.click('button[type=submit]'); await a.waitForURL(/admin\/(index\.php)?$/);
  await a.goto(BASE + '/admin/work.php'); const href = await a.$eval('a[href*="project.php?id="]', x => x.getAttribute('href'));
  await a.goto(BASE + '/admin/' + href); await a.fill('input[name=hero_video]', '/assets/video/film-palau-3.mp4'); await a.click('.savebar button[type=submit]'); await a.waitForLoadState(); await b0.close(); }
const b = await webkit.launch(); const ctx = await b.newContext(devices['iPhone 13']); const p = await ctx.newPage();
for (const path of ['/', '/work/palau']) {
  await p.goto(BASE + path); await p.evaluate(() => { const c = document.getElementById('cookie'); if (c) c.hidden = true }); await p.waitForTimeout(6000);
  const st = await p.evaluate(() => { const v = document.querySelector('.hero-vid'), h = document.querySelector('.hero,.phero'), r = v && v.getBoundingClientRect();
    const top = document.elementFromPoint(innerWidth / 2, innerHeight * .45); return v ? { src: v.src.split('/').pop(), t: +v.currentTime.toFixed(1), on: v.classList.contains('on'), direct: h.classList.contains('vid-direct'), op: getComputedStyle(v).opacity, h: Math.round(r.height), topEl: top && top.tagName + '.' + top.className } : 'no video' });
  const shot = '/tmp/mob' + path.replace(/\W+/g, '_') + '.png'; await p.screenshot({ path: shot }); console.log(path, JSON.stringify(st), shot);
}
await b.close();

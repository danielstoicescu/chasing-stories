import { chromium } from 'playwright'; import fs from 'fs';
const BASE = process.argv[2] || 'http://localhost:4210';
const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1400, height: 900 } }); const errs = [];
p.on('pageerror', e => errs.push(e.message)); p.on('console', m => m.type() === 'error' && errs.push(m.text()));
await p.goto(BASE + '/admin/login.php'); await p.fill('input[name=u]', cred.user); await p.fill('input[name=p]', cred.pass); await p.click('button[type=submit]'); await p.waitForURL(/admin\/(index\.php)?$/);
await p.goto(BASE + '/admin/project.php?id=1');
console.log(await p.evaluate(() => [...document.querySelectorAll('.rep-list > .rep-row')].slice(0, 4).map(r => {
  const t = r.querySelector('[data-f="t"]'); return { t: t && t.value, fields: [...r.querySelectorAll('[data-show]')].map(f => (f.querySelector('.lb') || {}).textContent + ':' + getComputedStyle(f).display + ' show=' + f.dataset.show) } })));
await p.locator('.rep-list > .rep-row').nth(1).scrollIntoViewIfNeeded(); await p.waitForTimeout(500); await p.screenshot({ path: '/tmp/blk.png' });
console.log(errs.length ? 'ERR ' + errs.join(' | ') : 'no js errors'); await b.close();

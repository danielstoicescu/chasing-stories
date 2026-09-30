// End-to-end smoke test of the CMS against the local PHP server. Reads the dev login from cms/cache/dev-admin.txt.
import { chromium } from 'playwright'; import fs from 'fs'; import path from 'path';
const BASE = process.argv[2] || 'http://localhost:4210', OUT = process.argv[3] || '/tmp';
const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1400, height: 900 } });
const problems = []; p.on('pageerror', e => problems.push('JS ' + e.message));
p.on('dialog', d => d.accept());
const check = async label => { const html = await p.content(); for (const m of ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught']) if (html.includes(m)) problems.push(label + ': ' + m + ' ' + html.slice(html.indexOf(m), html.indexOf(m) + 180).replace(/<[^>]+>/g, '')); };
await p.goto(BASE + '/admin/login.php');
await p.fill('input[name=u]', cred.user); await p.fill('input[name=p]', cred.pass); await p.click('button[type=submit]');
await p.waitForURL(/admin\/(index\.php)?$/); console.log('login ok');
for (const pg of ['index', 'home', 'work', 'project?id=1', 'photos', 'photos?id=1', 'films', 'films?id=1', 'clients', 'clients?id=1', 'services', 'services?id=1', 'about', 'contact', 'pages', 'media', 'users', 'account']) {
  await p.goto(`${BASE}/admin/${pg.includes('?') ? pg.replace('?', '.php?') : pg + '.php'}`); await check(pg);
  await p.screenshot({ path: path.join(OUT, `adm-${pg.replace(/[?=]/g, '-')}.png`), fullPage: false });
}
// edit a homepage text and see it on the site
await p.goto(BASE + '/admin/home.php');
const h1 = p.locator('input[name="c_home__h1"]'); const old = await h1.inputValue();
await h1.fill('CMS test headline'); await p.click('.savebar button[type=submit]'); await p.waitForLoadState();
const site = await (await fetch(BASE + '/')).text(); console.log('home edit visible on site:', site.includes('CMS test headline'));
await p.goto(BASE + '/admin/home.php'); await p.locator('input[name="c_home__h1"]').fill(old); await p.click('.savebar button[type=submit]'); await p.waitForLoadState();
// upload through the picker endpoint
const img = fs.readFileSync(new URL('../assets/palau-1.webp', import.meta.url));
const csrf = await p.getAttribute('body', 'data-csrf');
const up = await p.evaluate(async ({ b64, csrf }) => { const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0)); const fd = new FormData();
  fd.append('file', new File([bin], 'test upload.webp', { type: 'image/webp' })); fd.append('kind', 'image'); fd.append('csrf', csrf);
  return (await fetch('media_api.php', { method: 'POST', body: fd })).json() }, { b64: img.toString('base64'), csrf });
console.log('upload:', up.item ? up.item.ref + ' ' + up.item.kind : up.error);
// blocks editor round-trip: save project 1 unchanged and make sure its blocks survive
const before = (await (await fetch(BASE + '/work/palau')).text()).match(/"blocks":\[.*?\]\}/)?.[0]?.length;
await p.goto(BASE + '/admin/project.php?id=1'); await p.click('.savebar button[type=submit]'); await p.waitForLoadState(); await check('project save');
const after = (await (await fetch(BASE + '/work/palau')).text()).match(/"blocks":\[.*?\]\}/)?.[0]?.length;
console.log('project blocks survive a save:', before === after, before, after);
// public enquiry form
const q = await b.newPage(); q.on('pageerror', e => problems.push('site JS ' + e.message));
await q.goto(BASE + '/contact?project=palau'); await q.waitForTimeout(1500);
await q.evaluate(() => document.getElementById('cookie').hidden = true);
for (const [id, v] of [['f-name', 'Test Person'], ['f-company', 'Test Resort'], ['f-email', 'test@example.com'], ['f-loc', 'Bali, Indonesia'], ['f-details', 'Smoke test enquiry from the automated check.']]) await q.fill('#' + id, v);
await q.selectOption('#f-type', { index: 1 }); await q.check('#f-consent'); await q.click('#enq button[type=submit]');
await q.waitForSelector('.form-msg', { timeout: 10000 }); console.log('form result:', (await q.textContent('.form-msg')).trim().slice(0, 80));
await p.goto(BASE + '/admin/index.php'); const inbox = await p.content();
console.log('enquiry in inbox:', inbox.includes('Test Resort'));
await p.screenshot({ path: path.join(OUT, 'adm-inbox.png') });
console.log(problems.length ? 'PROBLEMS:\n' + problems.join('\n') : 'no PHP/JS errors');
await b.close();

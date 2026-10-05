// Homepage card with extra photos; a Brands project; logo linked to it; Work filters.
import { chromium } from 'playwright'; import fs from 'fs';
const BASE = process.argv[2] || 'http://localhost:4210';
const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1440, height: 900 } }); const errs = [];
p.on('pageerror', e => errs.push(e.message)); p.on('dialog', d => d.accept());
const php = async l => { const h = await p.content(); for (const m of ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:']) if (h.includes(m)) errs.push(l + ': ' + h.slice(h.indexOf(m), h.indexOf(m) + 200).replace(/<[^>]+>/g, '')) };
await p.goto(BASE + '/admin/login.php'); await p.fill('input[name=u]', cred.user); await p.fill('input[name=p]', cred.pass); await p.click('button[type=submit]'); await p.waitForURL(/admin\/(index\.php)?$/);
// 1. extra photos on the first homepage project
await p.goto(BASE + '/admin/home.php'); await php('home');
await p.evaluate(() => { const r = document.querySelector('.rep[data-into=home_work] .rep-row'); r.querySelector('[data-f=img2]').value = 'bangkok-4'; r.querySelector('[data-f=img3]').value = 'bangkok-5' });
await p.click('.savebar button[type=submit]'); await p.waitForLoadState(); await php('home saved');
// 2. a Brands project
await p.goto(BASE + '/admin/project.php?id=0'); await php('project new');
await p.fill('input[name=name]', 'Dior Test'); await p.fill('input[name=slug]', 'dior-test'); await p.fill('input[name=category]', 'Brands');
await p.evaluate(() => { for (const [n, v] of [['hero', 'f-02'], ['cover_v', 'f-02'], ['cover_l', 'f-02']]) document.querySelector(`input[name=${n}]`).value = v });
await p.click('.savebar button[type=submit]'); await p.waitForLoadState(); await php('project saved');
// 3. link the Dior logo
await p.goto(BASE + '/admin/clients.php'); const rows = await p.$$eval('table.list tr', t => t.map(r => [r.textContent.trim().slice(0, 40), (r.querySelector('a[href*="id="]') || {}).getAttribute?.('href')]));
const dior = rows.find(r => /dior/i.test(r[0])); console.log('dior row:', dior && dior[0]);
if (dior) { await p.goto(BASE + '/admin/' + dior[1]); await p.selectOption('select[name=project_slug]', 'dior-test'); await p.click('.savebar button[type=submit]'); await p.waitForLoadState(); await php('client saved') }
// site checks
await p.goto(BASE + '/'); await p.waitForTimeout(1500); await p.evaluate(() => { const c = document.getElementById('cookie'); if (c) c.hidden = true });
const card = p.locator('.grid.work .card').first(); await card.scrollIntoViewIfNeeded(); await card.hover(); await p.waitForTimeout(600);
console.log('home card:', await card.evaluate(c => ({ alts: c.querySelectorAll('img.alt').length, on: c.querySelectorAll('img.alt.on').length, dots: c.querySelectorAll('.steps i').length })));
await p.screenshot({ path: '/tmp/cat-home.png' });
console.log('dior logo link:', await p.$eval('.logos', l => [...l.querySelectorAll('a.logo')].map(a => a.getAttribute('href')).filter(h => /dior/.test(h))));
await p.goto(BASE + '/work'); await p.waitForTimeout(1200);
console.log('work filters:', await p.$$eval('.filters.wf .chip', c => c.map(x => x.textContent.trim())), 'cards', await p.$$eval('.wgrid .card', c => c.length));
await p.click('.filters.wf .chip >> text=Brands'); await p.waitForTimeout(1500);
console.log('brands:', p.url(), await p.$$eval('.wgrid .card h3', c => c.map(x => x.textContent)));
await p.screenshot({ path: '/tmp/cat-work.png' });
console.log(errs.length ? 'PROBLEMS ' + errs.join(' | ') : 'no errors'); await b.close();

// Home hero plays the horizontal film on desktop; a project's hero film can be swapped from the panel.
import { chromium } from 'playwright'; import fs from 'fs';
const BASE = process.argv[2] || 'http://localhost:4210';
const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
const b = await chromium.launch({ args: ['--autoplay-policy=no-user-gesture-required'] }); const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
const errs = []; p.on('pageerror', e => errs.push(e.message));
const vid = async path => { await p.goto(BASE + path); await p.waitForTimeout(5000); return p.evaluate(() => [...document.querySelectorAll('video.hero-vid')].map(v => v.src.split('/').pop() + ' ' + v.videoWidth + 'x' + v.videoHeight + ' rs' + v.readyState)) };
console.log('home desktop:', await vid('/'));
await p.goto(BASE + '/admin/login.php'); await p.fill('input[name=u]', cred.user); await p.fill('input[name=p]', cred.pass); await p.click('button[type=submit]'); await p.waitForURL(/admin\/(index\.php)?$/);
await p.goto(BASE + '/admin/work.php'); const href = await p.$eval('a[href*="project.php?id="]', a => a.getAttribute('href'));
// palau is the first project
await p.goto(BASE + '/admin/' + href); console.log('project fields:', await p.$$eval('.fld.vidf .lb', l => l.map(x => x.textContent)).then(x => x.slice(0, 2)));
await p.fill('input[name=hero_video]', '/assets/video/film-palau-2.mp4'); await p.click('.savebar button[type=submit]'); await p.waitForLoadState();
console.log('saved value:', await p.inputValue('input[name=hero_video]'));
const slug = await p.inputValue('input[name=slug]');
console.log('project page:', slug, await vid('/work/' + slug));
await p.goto(BASE + '/admin/' + href); await p.fill('input[name=hero_video]', ''); await p.click('.savebar button[type=submit]'); await p.waitForLoadState();
console.log('reverted:', await vid('/work/' + slug));
console.log(errs.length ? 'ERR ' + errs.join(' | ') : 'no js errors'); await b.close();

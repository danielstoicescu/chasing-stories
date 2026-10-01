// Picker upload: a 6000px camera-size JPEG must be scaled in the browser and land in the library.
import { chromium } from 'playwright'; import fs from 'fs';
const BASE = process.argv[2] || 'http://localhost:4210';
const cred = Object.fromEntries(fs.readFileSync(new URL('../cms/cache/dev-admin.txt', import.meta.url), 'utf8').split('\n').filter(l => l.includes(': ')).map(l => l.split(': ')));
const b = await chromium.launch(); const p = await b.newPage(); const errs = []; p.on('pageerror', e => errs.push(e.message));
await p.goto(BASE + '/admin/login.php'); await p.fill('input[name=u]', cred.user); await p.fill('input[name=p]', cred.pass); await p.click('button[type=submit]'); await p.waitForURL(/admin\/(index\.php)?$/);
await p.goto(BASE + '/admin/photos.php?id=0');
await p.click('[data-pick]'); await p.waitForSelector('.pk-item');
await p.setInputFiles('#pkUpload', '/tmp/big.jpg'); await p.waitForFunction(() => /Încărcat|bad/.test(document.getElementById('pkStatus').className + document.getElementById('pkStatus').textContent), null, { timeout: 30000 });
console.log('status:', (await p.textContent('#pkStatus')).trim(), '| picked:', await p.$eval('.fld.img [data-ref]', i => i.value));
await p.goto(BASE + '/admin/films.php?id=0'); console.log('film field:', await p.$('.fld.vidf') ? 'video picker present' : 'MISSING');
console.log(errs.length ? 'ERR ' + errs.join(' | ') : 'no js errors'); await b.close();

import { chromium } from 'playwright';
const b = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
await p.goto(process.argv[2]); await p.waitForTimeout(2500);
await p.evaluate(() => { document.getElementById('cookie').hidden = true });
for (let i = 0; i <= 24; i++) { await p.mouse.move(300 + i * 30, 300 + Math.sin(i / 3) * 80); await p.waitForTimeout(40) }
await p.waitForTimeout(150); await p.screenshot({ path: process.argv[3] + '/hero-hover.png' });
await p.waitForTimeout(1600); await p.screenshot({ path: process.argv[3] + '/hero-after.png' });
console.log('gl', await p.evaluate(() => !!document.querySelector('.hero canvas.glc')));
await b.close();

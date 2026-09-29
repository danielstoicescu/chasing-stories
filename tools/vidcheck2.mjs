import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage();
p.on('response', async r => { if (r.url().includes('drive')) { const h = r.headers(); console.log('RESP', r.status(), r.url().slice(0, 90), 'acao=' + h['access-control-allow-origin'], 'ct=' + h['content-type'], 'corp=' + h['cross-origin-resource-policy'], 'loc=' + (h['location'] || '').slice(0, 80)) } });
p.on('requestfailed', r => { if (r.url().includes('drive')) console.log('FAIL', r.failure().errorText) });
await p.goto(process.argv[2]);
const id = process.argv[3];
const r = await p.evaluate(async id => {
  const url = `https://drive.usercontent.google.com/download?id=${id}&export=download&confirm=t`;
  const one = co => new Promise(res => { const v = document.createElement('video'); v.muted = true; if (co) v.crossOrigin = 'anonymous'; v.preload = 'auto';
    v.onloadeddata = () => res(['ok', v.videoWidth, v.videoHeight]); v.onerror = () => res(['error', v.error && v.error.code]); v.src = url; setTimeout(() => res(['timeout', v.readyState]), 15000) });
  return { nocors: await one(false), cors: await one(true) } }, id);
console.log(JSON.stringify(r)); await b.close();

import { chromium } from 'playwright';
const b = await chromium.launch(); const p = await b.newPage();
const logs=[]; p.on('console', m => logs.push(m.text())); p.on('pageerror', e => logs.push('ERR '+e.message));
await p.goto(process.argv[2]);
const id = process.argv[3];
const r = await p.evaluate(async id => {
  const url = `https://drive.usercontent.google.com/download?id=${id}&export=download&confirm=t`;
  const out = {};
  try { const f = await fetch(url, { headers: { Range: 'bytes=0-99' } }); out.fetch = [f.status, f.headers.get('content-type')] } catch (e) { out.fetch = String(e) }
  out.video = await new Promise(res => { const v = document.createElement('video'); v.muted = true; v.crossOrigin = 'anonymous'; v.preload = 'auto';
    v.onloadeddata = () => res(['loadeddata', v.videoWidth, v.videoHeight, v.duration]); v.onerror = () => res(['error', v.error && v.error.code]); v.src = url; setTimeout(() => res(['timeout', v.readyState]), 20000) });
  return out }, id);
console.log(JSON.stringify(r), logs.slice(0,3)); await b.close();

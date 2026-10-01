/* Chasing Stories admin: media picker, repeaters, small conveniences. No libraries. */
(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const CSRF = document.body.dataset.csrf;

  /* ---------------- media picker ---------------- */
  const pk = $('#picker'), grid = $('#pkGrid'), search = $('#pkSearch');
  let items = null, target = null;
  const load = async () => { if (items) return items; const r = await fetch('media_api.php?list=1', { credentials: 'same-origin' }); items = (await r.json()).items || []; return items };
  const kindOf = el => el.dataset.kind || 'image';
  function render() {
    const want = target ? kindOf(target) : 'image', q = (search.value || '').toLowerCase();
    const list = items.filter(it => (want === 'video' ? it.kind === 'video' : want === 'logo' ? it.kind !== 'video' : it.kind === 'image'))
      .filter(it => !q || (it.name + ' ' + it.ref).toLowerCase().includes(q));
    grid.innerHTML = list.map(it => `<button type="button" class="pk-item ${it.kind === 'logo' ? 'logo' : ''} ${it.kind === 'video' ? 'vid' : ''}" data-ref="${it.ref}" data-url="${it.url}" title="${it.name}">
      ${it.kind === 'video' ? '' : `<img src="${it.thumb || it.url}" alt="" loading="lazy" decoding="async">`}<span>${it.name}</span></button>`).join('') || '<p class="muted">Nimic aici încă. Încarcă un fișier.</p>';
  }
  async function open(field) { target = field; pk.hidden = false; search.value = ''; grid.innerHTML = '<p class="muted">Se încarcă…</p>'; await load(); render(); search.focus() }
  function choose(ref, url) {
    if (!target) return; const inp = $('[data-ref]', target), img = $('.imgbox img', target), none = $('.none', target);
    inp.value = ref; if (img) { img.src = url; img.hidden = !url; } if (none) none.hidden = !!url;
    inp.dispatchEvent(new Event('change', { bubbles: true })); close();
  }
  function close() { pk.hidden = true; target = null }
  document.addEventListener('click', e => {
    const p = e.target.closest('[data-pick]'); if (p) { open(p.closest('.fld.img')); return }
    const c = e.target.closest('[data-clear]'); if (c) { const f = c.closest('.fld.img'); $('[data-ref]', f).value = ''; const i = $('.imgbox img', f); if (i) { i.hidden = true; i.removeAttribute('src') } const n = $('.none', f); if (n) n.hidden = false; return }
    const it = e.target.closest('.pk-item'); if (it) { choose(it.dataset.ref, it.dataset.url); return }
  });
  if (pk) {
    $('#pkClose').onclick = close;
    pk.addEventListener('click', e => { if (e.target === pk) close() });
    addEventListener('keydown', e => { if (e.key === 'Escape' && !pk.hidden) close() });
    search.addEventListener('input', render);
    const upload = async files => {
      const kind = target ? kindOf(target) : 'image'; let last = null;
      for (const f of files) {
        const fd = new FormData(); fd.append('file', f); fd.append('kind', kind); fd.append('csrf', CSRF);
        grid.insertAdjacentHTML('afterbegin', `<p class="muted up">Se încarcă ${f.name}…</p>`);
        const r = await fetch('media_api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({ error: 'Răspuns invalid de la server.' }));
        $$('.up', grid).forEach(x => x.remove());
        if (j.error) { alertBox(j.error); continue }
        items.unshift(j.item); last = j.item;
      }
      render(); if (last && files.length === 1) choose(last.ref, last.url);
    };
    $('#pkUpload').addEventListener('change', e => { upload([...e.target.files]); e.target.value = '' });
    grid.addEventListener('dragover', e => { e.preventDefault(); grid.classList.add('drag') });
    grid.addEventListener('dragleave', () => grid.classList.remove('drag'));
    grid.addEventListener('drop', e => { e.preventDefault(); grid.classList.remove('drag'); upload([...e.dataTransfer.files]) });
  }
  function alertBox(msg) { const d = document.createElement('div'); d.className = 'flash bad'; d.textContent = msg; ($('.main') || document.body).prepend(d); setTimeout(() => d.remove(), 7000) }

  /* ---------------- repeaters ----------------
     <div class="rep" data-into="field_name"><div class="rep-list">rows…</div><template>row</template><button data-add>…</button></div>
     Every input inside a row with data-f="key" becomes a property of that row's object. */
  function syncShow(row) {
    const t = $('[data-f="t"]', row); if (!t) return;
    $$('[data-show]', row).forEach(el => { el.style.display = el.dataset.show.split(' ').includes(t.value) ? '' : 'none' });
  }
  $$('.rep').forEach(rep => {
    const list = $('.rep-list', rep), tpl = $('template', rep);
    $$('.rep-row', list).forEach(syncShow);
    rep.addEventListener('click', e => {
      const add = e.target.closest('[data-add]');
      if (add && add.closest('.rep') === rep) {
        const node = tpl.content.firstElementChild.cloneNode(true);
        if (add.dataset.add) { const t = $('[data-f="t"]', node); if (t) t.value = add.dataset.add }
        list.appendChild(node); syncShow(node); node.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); return;
      }
      const row = e.target.closest('.rep-row'); if (!row || row.closest('.rep') !== rep) return;
      if (e.target.closest('[data-up]') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (e.target.closest('[data-down]') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
      if (e.target.closest('[data-del]') && confirm('Ștergi acest rând?')) row.remove();
    });
    rep.addEventListener('change', e => { const row = e.target.closest('.rep-row'); if (row) syncShow(row) });
  });
  document.addEventListener('submit', e => {
    $$('.rep', e.target).forEach(rep => {
      const out = $$('.rep-list > .rep-row', rep).map(row => {
        const o = {}; $$('[data-f]', row).forEach(el => { if (el.closest('.rep-row') !== row) return; o[el.dataset.f] = el.type === 'checkbox' ? el.checked : el.value.trim() }); return o;
      });
      const into = e.target.querySelector(`input[name="${rep.dataset.into}"]`); if (into) into.value = JSON.stringify(out);
    });
  }, true);

  /* ---------------- conveniences ---------------- */
  document.addEventListener('click', e => { const c = e.target.closest('[data-confirm]'); if (c && !confirm(c.dataset.confirm)) e.preventDefault() });
  const nameI = $('input[name="name"][data-slug-source]'), slugI = $('input[name="slug"]');
  if (nameI && slugI) {
    let touched = slugI.value !== '';
    slugI.addEventListener('input', () => touched = true);
    nameI.addEventListener('input', () => { if (!touched) slugI.value = nameI.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') });
  }
  // warn before leaving with unsaved edits
  let dirty = false; $$('form.edit').forEach(f => { f.addEventListener('input', () => dirty = true); f.addEventListener('change', () => dirty = true); f.addEventListener('submit', () => dirty = false) });
  addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = '' } });
})();

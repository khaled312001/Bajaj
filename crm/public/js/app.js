/* Bajaj CRM — UI helpers (no build step) */
(function () {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));
  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /* sidebar */
  const sidebar = $('#sidebar'), overlay = $('#overlay');
  const toggle = (open) => { if (!sidebar) return; sidebar.classList.toggle('open', open); overlay && overlay.classList.toggle('open', open); };
  $$('[data-menu]').forEach(b => b.addEventListener('click', () => toggle(!sidebar.classList.contains('open'))));
  overlay && overlay.addEventListener('click', () => toggle(false));

  /* modals */
  window.openModal = (id) => { const m = document.getElementById(id); if (m) { m.classList.add('open'); const f = m.querySelector('input:not([type=hidden]),textarea,select'); f && setTimeout(() => f.focus(), 60); } };
  window.closeModal = (id) => { const m = document.getElementById(id); m && m.classList.remove('open'); };
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-open]'); if (o) { e.preventDefault(); fillModal(o); openModal(o.dataset.open); return; }
    const c = e.target.closest('[data-close]'); if (c) { closeModal(c.dataset.close || c.closest('.modal').id); return; }
    if (e.target.classList && e.target.classList.contains('modal')) e.target.classList.remove('open');
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') $$('.modal.open').forEach(m => m.classList.remove('open')); });

  /* friendlier date / time controls inside modals: quick chips + simple hour / minute / ص-م selects */
  (() => {
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    $$('.modal input[type=date]').forEach(inp => {
      const box = document.createElement('div'); box.className = 'chips';
      [['اليوم', 0], ['غداً', 1], ['بعد 3 أيام', 3], ['أسبوع', 7], ['شهر', 30]].forEach(([t, n]) => {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'chip'; b.textContent = t;
        b.addEventListener('click', () => { const d = new Date(); d.setDate(d.getDate() + n); inp.value = fmt(d); inp.dispatchEvent(new Event('change')); });
        box.appendChild(b);
      });
      inp.parentNode.appendChild(box);
    });
    /* analog clock time picker */
    const NS = 'http://www.w3.org/2000/svg';
    const panel = document.createElement('div'); panel.className = 'clock-pop'; document.body.appendChild(panel);
    let cur = null;
    const closePanel = () => { panel.style.display = 'none'; cur = null; };
    document.addEventListener('mousedown', (e) => { if (cur && !panel.contains(e.target) && !cur.btn.contains(e.target)) closePanel(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closePanel(); });
    const el = (n, a, par) => { const x = document.createElementNS(NS, n); Object.keys(a || {}).forEach(k => x.setAttribute(k, a[k])); par && par.appendChild(x); return x; };
    function openClock(st) {
      cur = st; let mode = 'h';
      const draw = () => {
        const dispH = st.h === null ? '--' : pad(st.h), dispM = st.m === null ? '--' : pad(st.m);
        panel.innerHTML = '<div class="clock-top"><button type="button" data-m="h" class="' + (mode === 'h' ? 'on' : '') + '">' + dispH + '</button><span>:</span><button type="button" data-m="m" class="' + (mode === 'm' ? 'on' : '') + '">' + dispM + '</button>' +
          '<div class="ampm"><button type="button" data-ap="AM" class="' + (st.ap === 'AM' ? 'on' : '') + '">ص</button><button type="button" data-ap="PM" class="' + (st.ap === 'PM' ? 'on' : '') + '">م</button></div></div><div class="clock-face"></div>' +
          '<div class="clock-foot"><button type="button" class="chip" data-now>الآن</button><button type="button" class="chip" data-clear>مسح</button><button type="button" class="btn btn-primary btn-xs" data-ok>تم</button></div>';
        const svg = el('svg', { viewBox: '-110 -110 220 220', width: 220, height: 220 }, panel.querySelector('.clock-face'));
        el('circle', { r: 104, class: 'cf-bg' }, svg);
        const val = mode === 'h' ? st.h : st.m;
        const ang = (v) => (mode === 'h' ? (v % 12) * 30 : v * 6) * Math.PI / 180;
        if (val !== null) {
          const a = ang(val); const hv = mode === 'h' ? (val % 12 || 12) : val;
          const a2 = mode === 'h' ? (hv % 12) * 30 * Math.PI / 180 : a;
          el('line', { x1: 0, y1: 0, x2: 78 * Math.sin(a2), y2: -78 * Math.cos(a2), class: 'cf-hand' }, svg);
          el('circle', { cx: 78 * Math.sin(a2), cy: -78 * Math.cos(a2), r: 17, class: 'cf-knob' }, svg);
        }
        el('circle', { r: 4, class: 'cf-hub' }, svg);
        const marks = mode === 'h' ? Array.from({ length: 12 }, (_, k) => k + 1) : Array.from({ length: 12 }, (_, k) => k * 5);
        marks.forEach(n => {
          const a = (mode === 'h' ? n * 30 : n * 6) * Math.PI / 180;
          const sel = mode === 'h' ? (st.h !== null && (st.h % 12 || 12) === n) : st.m === n;
          const t = el('text', { x: 78 * Math.sin(a), y: -78 * Math.cos(a) + 5, class: 'cf-n' + (sel ? ' sel' : '') }, svg); t.textContent = mode === 'h' ? n : pad(n);
        });
        const pick = (e) => {
          const r = svg.getBoundingClientRect(); const x = e.clientX - r.left - r.width / 2, y = e.clientY - r.top - r.height / 2;
          let deg = (Math.atan2(x, -y) * 180 / Math.PI + 360) % 360;
          if (mode === 'h') { const h12 = Math.round(deg / 30) % 12 || 12; st.h = h12; }
          else st.m = Math.round(deg / 6) % 60;
          if (st.m === null && mode === 'h') st.m = 0;
          commit();
        };
      };
      let down = false;
      const pickAt = (e) => {
        const svg = panel.querySelector('svg'); if (!svg) return;
        const r = svg.getBoundingClientRect(); const x = e.clientX - r.left - r.width / 2, y = e.clientY - r.top - r.height / 2;
        const deg = (Math.atan2(x, -y) * 180 / Math.PI + 360) % 360;
        if (mode === 'h') { st.h = Math.round(deg / 30) % 12 || 12; if (st.m === null) st.m = 0; } else st.m = Math.round(deg / 6) % 60;
        commit();
      };
      panel.onpointerdown = (e) => { if (e.target.closest('svg')) { down = true; pickAt(e); } };
      panel.onpointermove = (e) => { if (down) pickAt(e); };
      panel.onpointerup = () => { if (down) { down = false; if (mode === 'h') { mode = 'm'; draw(); } } };
      const commit = () => {
        const h24 = st.h === null ? null : (st.h % 12) + (st.ap === 'PM' ? 12 : 0);
        st.inp.value = h24 === null ? '' : pad(h24) + ':' + pad(st.m ?? 0);
        st.btn.querySelector('span').textContent = h24 === null ? 'اختر الوقت' : pad(st.h) + ':' + pad(st.m ?? 0) + ' ' + (st.ap === 'PM' ? 'م' : 'ص');
        st.btn.classList.toggle('has', h24 !== null);
        const keep = mode; draw0(keep);
      };
      const draw0 = (m) => { mode = m; draw(); };
      panel.onclick = (e) => {
        const b = e.target.closest('button'); if (!b) return;
        if (b.dataset.m) { mode = b.dataset.m; draw(); }
        else if (b.dataset.ap) { st.ap = b.dataset.ap; if (st.h === null) st.h = 12; commit(); }
        else if ('now' in b.dataset) { const d = new Date(); st.h = d.getHours() % 12 || 12; st.m = d.getMinutes(); st.ap = d.getHours() >= 12 ? 'PM' : 'AM'; commit(); }
        else if ('clear' in b.dataset) { st.h = null; st.m = null; commit(); }
        else if ('ok' in b.dataset) closePanel();
      };
      draw();
      panel.style.display = 'block';
      const r = st.btn.getBoundingClientRect(); const ph = panel.offsetHeight, pw = panel.offsetWidth;
      let top = r.bottom + 6; if (top + ph > innerHeight - 8) top = Math.max(8, r.top - ph - 6);
      let left = Math.min(Math.max(8, r.left), innerWidth - pw - 8);
      panel.style.top = top + 'px'; panel.style.left = left + 'px';
    }
    $$('.modal input[type=time]').forEach(inp => {
      inp.style.display = 'none';
      const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'time-btn';
      btn.innerHTML = '<i class="fa-regular fa-clock"></i><span>اختر الوقت</span>';
      const st = { inp, btn, h: null, m: null, ap: 'AM' };
      btn.addEventListener('click', () => { cur && cur.btn === btn ? closePanel() : openClock(st); });
      inp.parentNode.appendChild(btn);
    });
  })();

  /* data-fill-*: copy attributes of the trigger into the modal form (action url, hidden inputs, labels) */
  function fillModal(trigger) {
    const modal = document.getElementById(trigger.dataset.open); if (!modal) return;
    const form = modal.querySelector('form');
    if (trigger.dataset.action && form) form.action = trigger.dataset.action;
    Object.keys(trigger.dataset).forEach(k => {
      if (!k.startsWith('fill')) return;
      const name = k.slice(4).replace(/^./, c => c.toLowerCase()).replace(/[A-Z]/g, c => '_' + c.toLowerCase());
      const field = modal.querySelector('[name="' + name + '"]');
      if (field) field.value = trigger.dataset[k];
      const label = modal.querySelector('[data-label="' + name + '"]'); if (label) label.textContent = trigger.dataset[k];
    });
  }

  /* confirm + double submit guard */
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) { e.preventDefault(); return; }
    const btn = f.querySelector('button[type=submit]:not([data-nolock])');
    if (btn && !f.dataset.noLock) setTimeout(() => { btn.disabled = true; btn.dataset.t = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جارٍ الحفظ...'; setTimeout(() => { btn.disabled = false; btn.innerHTML = btn.dataset.t; }, 8000); }, 0);
  });

  /* toasts */
  window.toast = (msg, type = 'success') => {
    let box = $('#toasts'); if (!box) { box = document.createElement('div'); box.id = 'toasts'; box.className = 'toasts'; document.body.appendChild(box); }
    const t = document.createElement('div'); t.className = 'toast ' + type;
    t.innerHTML = '<i class="fa-solid ' + (type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check') + '"></i><span></span>'; t.querySelector('span').textContent = msg;
    box.appendChild(t); setTimeout(() => { t.style.transition = 'all .3s'; t.style.opacity = 0; setTimeout(() => t.remove(), 300); }, 4200);
  };
  $$('[data-toast]').forEach(el => window.toast(el.dataset.toast, el.dataset.type || 'success'));

  /* password reveal */
  $$('[data-pw]').forEach(b => b.addEventListener('click', () => { const i = document.getElementById(b.dataset.pw); i.type = i.type === 'password' ? 'text' : 'password'; b.firstElementChild.className = i.type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash'; }));

  /* customer picker (autocomplete) */
  $$('[data-picker]').forEach(wrap => {
    const input = $('input[type=text]', wrap), hidden = $('input[type=hidden]', wrap), list = $('.picker-list', wrap), url = wrap.dataset.picker;
    let timer;
    input.addEventListener('input', () => {
      hidden.value = ''; clearTimeout(timer);
      const q = input.value.trim(); if (q.length < 2) { list.classList.remove('open'); return; }
      timer = setTimeout(async () => {
        try {
          const r = await fetch(url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
          if (!r.ok) throw 0; const rows = await r.json();
          list.innerHTML = rows.length ? '' : '<div class="picker-item muted">لا توجد نتائج</div>';
          rows.forEach(c => { const d = document.createElement('div'); d.className = 'picker-item'; d.innerHTML = '<b></b><small></small>'; d.firstChild.textContent = c.name; d.lastChild.textContent = c.phone + ' · ' + c.code; d.addEventListener('click', () => { input.value = c.name; hidden.value = c.id; list.classList.remove('open'); }); list.appendChild(d); });
          list.classList.add('open');
        } catch (_) { list.classList.remove('open'); }
      }, 250);
    });
    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) list.classList.remove('open'); });
  });

  /* duplicate phone/alt/whatsapp check on customer form — any of the 3 slots is checked
     against the other customers' 3 columns; the main phone field keeps the original inline box */
  const phone = $('#phone-input'), dupBox = $('#dup-box');
  if (phone && dupBox) {
    let t; phone.addEventListener('input', () => { clearTimeout(t); t = setTimeout(async () => {
      const v = phone.value.replace(/\D/g, ''); if (v.length < 8) { dupBox.style.display = 'none'; return; }
      try { const r = await fetch(dupBox.dataset.url + '?phone=' + v, { headers: { Accept: 'application/json' } }); const d = await r.json();
        if (d.exists && d.code !== dupBox.dataset.self) { dupBox.style.display = 'flex'; dupBox.querySelector('span').textContent = 'هذا الرقم مسجل بالفعل (كود ' + d.code + ') — أدخله ' + (d.by || 'النظام') + ' بتاريخ ' + d.since; const a = dupBox.querySelector('a'); a.style.display = d.can_open ? '' : 'none'; a.href = d.url; } else dupBox.style.display = 'none';
      } catch (_) {} }, 400); });
  }

  /* alternative number + whatsapp: warn right away if either is already registered to another customer */
  const otherSlots = ['input[name=alt_phone]', 'input[name=whatsapp]'].map((sel) => document.querySelector(sel)).filter(Boolean);
  otherSlots.forEach((slot) => {
    if (!dupBox) return;
    const warn = document.createElement('div'); warn.className = 'pill-alert'; warn.style.cssText = 'display:none;margin-top:8px'; slot.parentNode.appendChild(warn);
    let t2; slot.addEventListener('input', () => { clearTimeout(t2); t2 = setTimeout(async () => {
      const v = slot.value.replace(/\D/g, '');
      const others = otherSlots.concat(phone ? [phone] : []).filter((s) => s !== slot).map((s) => s.value.replace(/\D/g, ''));
      if (v.length < 8 || others.some((o) => o && o.slice(-10) === v.slice(-10))) { warn.style.display = 'none'; return; }
      try { const r = await fetch(dupBox.dataset.url + '?phone=' + v, { headers: { Accept: 'application/json' } }); const d = await r.json();
        if (d.exists && d.code !== dupBox.dataset.self) { warn.style.display = 'flex'; warn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i><span>تنبيه: هذا الرقم مسجل بالفعل لعميل آخر (كود ' + d.code + ') — أدخله ' + (d.by || 'النظام') + ' بتاريخ ' + d.since + '</span>' + (d.can_open ? '<a class="btn btn-xs btn-danger" style="margin-right:auto" target="_blank" rel="noopener" href="' + d.url + '">فتح الملف</a>' : ''); }
        else warn.style.display = 'none'; } catch (_) {} }, 400); });
  });

  /* cascading Egypt governorate -> مركز selects (resources/views/partials/geo_fields.blade.php) */
  $$('select.geo-gov').forEach((gov) => {
    const field = gov.closest('.field');
    const center = field && field.nextElementSibling ? field.nextElementSibling.querySelector('select.geo-center') : null;
    if (!center || !window.EGYPT_GEO) return;
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fill = (keepCurrent) => {
      const list = window.EGYPT_GEO[gov.value] || [];
      const cur = keepCurrent ? (center.dataset.current || '') : '';
      center.innerHTML = '<option value="">— اختر —</option>' + list.map((c) => '<option' + (c === cur ? ' selected' : '') + '>' + esc(c) + '</option>').join('');
      if (cur && !list.includes(cur)) center.insertAdjacentHTML('afterbegin', '<option selected>' + esc(cur) + '</option>');
    };
    gov.addEventListener('change', () => { center.dataset.current = ''; fill(false); });
    if (gov.value) fill(true);
  });

  /* charts */
  window.makeChart = (id, cfg) => {
    const el = document.getElementById(id); if (!el || !window.Chart) return;
    Chart.defaults.font.family = 'Cairo, sans-serif'; Chart.defaults.font.weight = '700'; Chart.defaults.color = '#64748b';
    return new Chart(el, cfg);
  };
  window.PALETTE = ['#2563eb', '#0ea5e9', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0d9488', '#db2777', '#64748b', '#ca8a04'];

  window.fmt = (n, d = 2) => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });

  /* top search: typing a phone number shows the customer instantly, or offers to add a new one */
  (() => {
    const form = document.querySelector('.top-search'); if (!form) return;
    const input = form.querySelector('input'); const csrfUrl = document.body.dataset.checkPhone; if (!input || !csrfUrl) return;
    const box = document.createElement('div'); box.className = 'ts-result'; form.appendChild(box);
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let t, seq = 0;
    const hide = () => { box.style.display = 'none'; box.innerHTML = ''; };
    input.addEventListener('input', () => {
      clearTimeout(t); const v = input.value.trim();
      if (!/^[\d\s+()\-٠-٩]{8,20}$/.test(v)) { hide(); return; }
      t = setTimeout(async () => {
        const my = ++seq;
        try {
          const r = await fetch(csrfUrl + '?phone=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } }); if (!r.ok || my !== seq) return;
          const d = await r.json(); if (my !== seq) return;
          if (d.exists && d.can_open) box.innerHTML = '<a class="ts-hit" href="' + esc(d.url) + '"><b>' + esc(d.name) + '</b><span>' + esc(d.phone) + ' · ' + esc(d.status) + (d.interest ? ' · ' + esc(d.interest) : '') + '</span><small>فتح ملف العميل ←</small></a>';
          else if (d.exists) box.innerHTML = '<div class="ts-hit"><b>الرقم مسجل بالفعل</b><span>كود ' + esc(d.code) + ' — أدخله ' + esc(d.by || 'النظام') + ' بتاريخ ' + esc(d.since) + '</span></div>';
          else if (d.new_url) box.innerHTML = '<div class="ts-hit"><b>الرقم غير مسجل</b></div><a class="btn btn-primary btn-sm" style="margin:8px" href="' + esc(d.new_url) + '"><i class="fa-solid fa-user-plus"></i> إضافة عميل جديد</a>';
          else { hide(); return; }
          box.style.display = 'block';
        } catch (e) { hide(); }
      }, 250);
    });
    document.addEventListener('click', (e) => { if (!form.contains(e.target)) hide(); });
  })();

  /* generic filter toolbar for small tables: <table data-filterable> ; <th data-filter> adds a dropdown for that column */
  document.querySelectorAll('table[data-filterable]').forEach((tbl) => {
    const wrap = tbl.closest('.table-wrap') || tbl;
    const bar = document.createElement('div');
    bar.className = 'filter-bar table-filter'; bar.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--line-2)';
    const search = document.createElement('div'); search.className = 'input-icon';
    search.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i><input class="input" type="search" placeholder="بحث في الجدول...">';
    bar.appendChild(search);
    const heads = Array.from(tbl.tHead ? tbl.tHead.rows[0].cells : []);
    const selects = [];
    heads.forEach((th, i) => {
      if (!th.hasAttribute('data-filter')) return;
      const vals = new Set(); Array.from(tbl.tBodies[0].rows).forEach(r => { const t = (r.cells[i]?.innerText || '').trim(); if (t && t !== '—') vals.add(t); });
      if (vals.size < 2 || vals.size > 40) return;
      const sel = document.createElement('select'); sel.className = 'input';
      sel.innerHTML = '<option value="">' + (th.getAttribute('data-filter') || th.innerText.trim()) + ': الكل</option>' + Array.from(vals).sort().map(v => '<option>' + v.replace(/</g, '&lt;') + '</option>').join('');
      sel.dataset.col = i; bar.appendChild(sel); selects.push(sel);
    });
    const count = document.createElement('div'); count.className = 'muted small'; bar.appendChild(count);
    wrap.parentNode.insertBefore(bar, wrap);
    const rows = Array.from(tbl.tBodies[0].rows);
    const run = () => {
      const q = search.querySelector('input').value.trim().toLowerCase(); let n = 0;
      rows.forEach((r) => {
        const txt = (r.innerText + ' ' + Array.from(r.querySelectorAll('input,select,textarea')).map(i => i.value).join(' ')).toLowerCase();
        let ok = !q || txt.includes(q);
        selects.forEach((s) => { if (ok && s.value && (r.cells[s.dataset.col]?.innerText || '').trim() !== s.value) ok = false; });
        r.style.display = ok ? '' : 'none'; if (ok) n++;
      });
      count.textContent = n + ' / ' + rows.length;
    };
    bar.addEventListener('input', run); bar.addEventListener('change', run); run();
  });

  /* data-leak deterrents for customer-service accounts */
  if (document.body.classList.contains('agent-view')) {
    const block = (e) => { if (!e.target.closest('input,textarea,select,[contenteditable]')) e.preventDefault(); };
    ['contextmenu', 'copy', 'cut', 'dragstart'].forEach(ev => document.addEventListener(ev, block));
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && ['p', 's', 'u', 'a'].includes(e.key.toLowerCase()) && !e.target.closest('input,textarea')) {
        if (e.key.toLowerCase() === 'p' && document.body.dataset.allowPrint) return; e.preventDefault(); }
      if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['i', 'j', 'c'].includes(e.key.toLowerCase()))) e.preventDefault();
    });
  }
})();

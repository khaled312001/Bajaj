/* Bajaj CRM — chat page (polling, composer, emoji, voice notes) */
(function () {
  'use strict';
  const thread = document.getElementById('chat-thread');
  if (!thread) return;

  const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const messagesUrl = thread.dataset.messagesUrl;
  const sendUrl = thread.dataset.sendUrl;
  const box = document.getElementById('chat-messages');
  const composer = document.getElementById('chat-composer');
  const input = document.getElementById('chat-input');

  const scrollDown = () => { if (box) box.scrollTop = box.scrollHeight; };
  scrollDown();

  /* back button on mobile */
  const backBtn = document.getElementById('chat-back');
  backBtn && backBtn.addEventListener('click', (e) => {
    if (window.innerWidth > 760) return;
    e.preventDefault();
    document.querySelector('.chat-list').classList.remove('has-active');
    thread.classList.remove('active');
  });

  if (!messagesUrl || !sendUrl) return;

  /* polling */
  let polling = false;
  async function poll() {
    if (polling) return;
    polling = true;
    try {
      const after = thread.dataset.lastId || 0;
      const r = await fetch(messagesUrl + '?after=' + after, { headers: { Accept: 'application/json' } });
      if (r.ok) {
        const data = await r.json();
        if (data.messages && data.messages.length) {
          const wasBottom = box && (box.scrollHeight - box.scrollTop - box.clientHeight < 60);
          data.messages.forEach((m) => {
            box.insertAdjacentHTML('beforeend', m.html);
            thread.dataset.lastId = m.id;
          });
          if (wasBottom) scrollDown();
        }
      }
    } catch (e) { /* network hiccup — next tick retries */ }
    polling = false;
  }
  poll();
  setInterval(poll, 4000);

  /* composer: text */
  composer && composer.addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = (input.value || '').trim();
    if (!body) return;
    input.value = '';
    try {
      const r = await fetch(sendUrl, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'body=' + encodeURIComponent(body),
      });
      if (r.ok) {
        const data = await r.json();
        box.insertAdjacentHTML('beforeend', data.message.html);
        thread.dataset.lastId = data.message.id;
        scrollDown();
      } else {
        window.toast && window.toast('تعذر إرسال الرسالة', 'error');
      }
    } catch (e) {
      window.toast && window.toast('تعذر إرسال الرسالة', 'error');
    }
  });

  /* emoji picker */
  const emojiBtn = document.getElementById('emoji-btn');
  const emojiPop = document.getElementById('emoji-pop');
  if (emojiBtn && emojiPop) {
    const EMOJI = ['😀', '😂', '🙂', '😉', '😍', '👍', '🙏', '👏', '🔥', '✅', '❌', '⚠️', '📌', '📞', '🚗', '🏍️', '💰', '🕒', '📍', '🎉', '😢', '😡', '🤔', '💪'];
    emojiPop.innerHTML = EMOJI.map((e) => '<button type="button" data-e="' + e + '">' + e + '</button>').join('');
    emojiBtn.addEventListener('click', (e) => { e.stopPropagation(); emojiPop.classList.toggle('open'); });
    emojiPop.addEventListener('click', (e) => {
      const s = e.target.closest('[data-e]'); if (!s) return;
      input.value += s.dataset.e; input.focus();
    });
    document.addEventListener('click', (e) => { if (!emojiPop.contains(e.target) && e.target !== emojiBtn) emojiPop.classList.remove('open'); });
  }

  /* voice notes */
  const micBtn = document.getElementById('mic-btn');
  if (micBtn && navigator.mediaDevices && window.MediaRecorder) {
    let recorder = null, chunks = [], stream = null;

    const stopStream = () => { if (stream) { stream.getTracks().forEach((t) => t.stop()); stream = null; } };

    micBtn.addEventListener('click', async () => {
      if (recorder && recorder.state === 'recording') { recorder.stop(); return; }
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch (e) {
        window.toast && window.toast('تعذر الوصول للميكروفون', 'error');
        return;
      }
      chunks = [];
      recorder = new MediaRecorder(stream);
      recorder.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
      recorder.onstop = async () => {
        micBtn.classList.remove('rec');
        stopStream();
        if (!chunks.length) return;
        const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
        const fd = new FormData();
        const ext = (blob.type.split('/')[1] || 'webm').split(';')[0];
        fd.append('audio', blob, 'voice.' + ext);
        try {
          const r = await fetch(sendUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: fd });
          if (r.ok) {
            const data = await r.json();
            box.insertAdjacentHTML('beforeend', data.message.html);
            thread.dataset.lastId = data.message.id;
            scrollDown();
          } else {
            window.toast && window.toast('تعذر إرسال الرسالة الصوتية', 'error');
          }
        } catch (e) {
          window.toast && window.toast('تعذر إرسال الرسالة الصوتية', 'error');
        }
      };
      recorder.start();
      micBtn.classList.add('rec');
    });
  } else if (micBtn) {
    micBtn.disabled = true;
    micBtn.title = 'التسجيل الصوتي غير متاح على هذا المتصفح';
  }

  /* share a customer file: search + pick from a popover, sends a customer_link message */
  const shareBtn = document.getElementById('share-customer-btn');
  const customerPop = document.getElementById('customer-pop');
  const customerSearch = document.getElementById('customer-pop-search');
  const customerResults = document.getElementById('customer-pop-results');
  const searchUrl = composer && composer.dataset.searchUrl;

  async function sendCustomerLink(id) {
    const fd = new FormData();
    fd.append('customer_id', id);
    try {
      const r = await fetch(sendUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: fd });
      if (r.ok) {
        const data = await r.json();
        box.insertAdjacentHTML('beforeend', data.message.html);
        thread.dataset.lastId = data.message.id;
        scrollDown();
      } else {
        window.toast && window.toast('تعذر مشاركة ملف العميل', 'error');
      }
    } catch (e) {
      window.toast && window.toast('تعذر مشاركة ملف العميل', 'error');
    }
  }

  if (shareBtn && customerPop && customerSearch && customerResults && searchUrl) {
    shareBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      customerPop.classList.toggle('open');
      if (customerPop.classList.contains('open')) setTimeout(() => customerSearch.focus(), 50);
    });

    let searchSeq = 0;
    let debounce = null;
    customerSearch.addEventListener('input', () => {
      clearTimeout(debounce);
      const term = customerSearch.value.trim();
      if (term.length < 2) { customerResults.innerHTML = ''; return; }
      debounce = setTimeout(async () => {
        const my = ++searchSeq;
        try {
          const r = await fetch(searchUrl + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } });
          if (!r.ok || my !== searchSeq) return;
          const data = await r.json();
          if (!data.customers || !data.customers.length) {
            customerResults.innerHTML = '<div class="customer-pop-empty">لا توجد نتائج</div>';
            return;
          }
          customerResults.innerHTML = data.customers.map((c) => (
            '<div class="customer-pop-row" data-id="' + c.id + '"><b>' + esc(c.name) + '</b><span>' + esc(c.code) + ' — ' + esc(c.status) + '</span></div>'
          )).join('');
        } catch (e) { /* ignore */ }
      }, 300);
    });

    customerResults.addEventListener('click', (e) => {
      const row = e.target.closest('.customer-pop-row');
      if (!row) return;
      sendCustomerLink(row.dataset.id);
      customerPop.classList.remove('open');
      customerSearch.value = '';
      customerResults.innerHTML = '';
    });

    document.addEventListener('click', (e) => {
      if (!customerPop.contains(e.target) && e.target !== shareBtn) customerPop.classList.remove('open');
    });
  }
})();

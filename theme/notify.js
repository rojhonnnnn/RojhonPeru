/* =========================================================================
   ROJHON SOLUTIONS - app-wide chat notifications
   Runs on every page. Keeps you "Active now", marks incoming messages as
   Delivered, updates the unread badges (navbar + sidebar + tab title),
   fills the navbar Chats dropdown and pops a toast with a sound when a new
   message arrives. On the Messages page itself theme/chat.js does the
   polling and only borrows the helpers below.
   ========================================================================= */
(function () {
  'use strict';
  if (!window.RS) { return; }
  var ROOT = window.RS.root, API = ROOT + 'module/chat/api.php';
  var baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  var inboxSig = '', timer = null, fails = 0;
  var LAST_KEY = 'rsLastIn_' + window.RS.uid;

  function esc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  var PALETTE = ['#1c2f9e', '#0eb17a', '#0aa5c7', '#c31f5e', '#6b4fbb', '#d9730d', '#2b3648', '#0a7cff'];
  function colorFor(name) { var h = 0; name = String(name || '?'); for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) >>> 0; } return PALETTE[h % PALETTE.length]; }
  function initials(name) { var p = String(name || '?').trim().split(/\s+/); return ((p[0] || '?').charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase(); }
  function avatar(name, photo, online) {
    return '<span class="rsn-av">' + (photo ? '<img src="' + esc(photo) + '" alt="">' : '<span style="background:' + colorFor(name) + '">' + esc(initials(name)) + '</span>') +
      (online ? '<i class="rsn-dot"></i>' : '') + '</span>';
  }
  function ago(ms) {
    var s = Math.max(0, Math.round((Date.now() - ms) / 1000));
    if (s < 60) { return 'now'; } var m = Math.floor(s / 60); if (m < 60) { return m + 'm'; }
    var h = Math.floor(m / 60); if (h < 24) { return h + 'h'; } var d = Math.floor(h / 24); if (d < 7) { return d + 'd'; }
    return Math.floor(d / 7) + 'w';
  }
  function preview(it) {
    var l = it.last, mine = l.from === window.RS.uid, who = String(it.partner.name).split(/\s+/)[0];
    if (l.unsent) { return mine ? 'You unsent a message' : who + ' unsent a message'; }
    if (l.type === 'image') { return mine ? 'You sent a photo' : who + ' sent a photo'; }
    if (l.type === 'file') { return mine ? 'You sent an attachment' : who + ' sent an attachment'; }
    return (mine ? 'You: ' : '') + String(l.body || '').replace(/\s+/g, ' ');
  }

  /* --- navbar avatar initials (accounts with no photo) --- */
  document.querySelectorAll('.rs-initials[data-name]').forEach(function (n) {
    var name = n.getAttribute('data-name');
    n.textContent = initials(name); n.style.background = colorFor(name);
  });

  function setUnread(n) {
    n = parseInt(n, 10) || 0;
    ['rsNavUnread', 'rsSideUnread'].forEach(function (id) {
      var b = document.getElementById(id); if (!b) { return; }
      b.textContent = n > 9 ? '9+' : String(n);
      b.style.display = n > 0 ? '' : 'none';
    });
    var home = document.getElementById('rsHomeUnread');
    if (home) { home.textContent = String(n); }
    document.title = (n > 0 ? '(' + n + ') ' : '') + baseTitle;
  }

  function renderInbox(list) {
    var box = document.getElementById('rsNavList'); if (!box) { return; }
    if (!list || !list.length) { box.innerHTML = '<div class="rs-nav-dd-empty">No chats yet. Open Messages to start one.</div>'; return; }
    box.innerHTML = list.slice(0, 8).map(function (it) {
      var unread = it.unread > 0;
      return '<a class="rs-nav-dd-item' + (unread ? ' unread' : '') + '" href="' + ROOT + 'module/chat/?u=' + it.partner.uid + '">' +
        avatar(it.partner.name, it.partner.photo, it.partner.online) +
        '<span class="rsn-body"><span class="rsn-name">' + esc(it.partner.name) + '</span>' +
        '<span class="rsn-sub"><span class="rsn-prev">' + esc(preview(it)) + '</span><span>&middot; ' + ago(it.last.sent) + '</span></span></span>' +
        (unread ? '<span class="rsn-count">' + (it.unread > 9 ? '9+' : it.unread) + '</span>' : '') + '</a>';
    }).join('');
  }

  /* --- sound: short two-note chime made with WebAudio (no mp3 needed) --- */
  var ctx = null;
  function unlock() {
    if (ctx) { return; }
    try { ctx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { ctx = null; }
  }
  document.addEventListener('pointerdown', unlock, { once: true });
  document.addEventListener('keydown', unlock, { once: true });
  var lastSound = 0;
  function sound() {
    if (!ctx || Date.now() - lastSound < 1500) { return; }
    lastSound = Date.now();
    try {
      if (ctx.state === 'suspended') { ctx.resume(); }
      [[880, 0], [1320, 0.09]].forEach(function (n) {
        var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + n[1];
        o.type = 'sine'; o.frequency.value = n[0];
        g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.18, t + 0.015); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
        o.connect(g); g.connect(ctx.destination); o.start(t); o.stop(t + 0.25);
      });
    } catch (e) {}
  }

  /* --- toasts --- */
  function toast(m) {
    var wrap = document.getElementById('rsnToasts');
    if (!wrap) { wrap = document.createElement('div'); wrap.id = 'rsnToasts'; wrap.className = 'rsn-toasts'; wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
    while (wrap.children.length >= 3) { wrap.removeChild(wrap.firstChild); }
    var text = m.type === 'image' ? 'Sent you a photo' : (m.type === 'file' ? 'Sent you a file: ' + (m.file_name || '') : m.body);
    var a = document.createElement('a');
    a.className = 'rsn-toast'; a.href = ROOT + 'module/chat/?u=' + m.from;
    a.innerHTML = avatar(m.name, m.photo, true) + '<span class="rsn-body"><span class="rsn-name">' + esc(m.name) + '</span><span class="rsn-text">' + esc(text) + '</span></span>' +
      '<button type="button" class="rsn-close" aria-label="Dismiss">&times;</button>';
    a.querySelector('.rsn-close').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); a.remove(); });
    wrap.appendChild(a);
    setTimeout(function () { a.classList.add('out'); setTimeout(function () { a.remove(); }, 400); }, 7000);
  }

  window.RSNotify = { setUnread: setUnread, renderInbox: renderInbox, sound: sound, toast: toast };

  if (window.RS.chatPage) { return; } /* theme/chat.js takes over there */

  function ping() {
    var last = null;
    try { last = sessionStorage.getItem(LAST_KEY); } catch (e) {}
    var fd = new FormData();
    fd.append('act', 'ping');
    fd.append('last_in', last === null ? '-1' : last);
    fd.append('inbox_sig', inboxSig);
    fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store',
      headers: { 'X-CSRF-Token': window.RS.csrf, 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (r.status === 401) { window.location = ROOT + 'login.php?expired=1'; throw new Error('expired'); }
        return r.json();
      })
      .then(function (d) {
        if (!d || d.error) { throw new Error(d && d.message); }
        fails = 0;
        setUnread(d.unread);
        if (d.inbox) { inboxSig = d.inbox_sig; renderInbox(d.inbox); }
        if (d.incoming && d.incoming.length) { d.incoming.forEach(toast); sound(); }
        try { sessionStorage.setItem(LAST_KEY, String(d.last_in)); } catch (e) {}
      })
      .catch(function () { fails++; })
      .then(function () {
        clearTimeout(timer);
        timer = setTimeout(ping, fails ? Math.min(30000, 4000 * fails) : (document.hidden ? 10000 : 4000));
      });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { clearTimeout(timer); timer = setTimeout(ping, 200); } });
  ping();
})();

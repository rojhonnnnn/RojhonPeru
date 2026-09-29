/* =========================================================================
   ROJHON SOLUTIONS - Messages (Messenger-style chat)

   Live updates use short polling (module/chat/api.php?act=sync), which
   works on plain XAMPP with nothing extra installed. One sync every 1.5s
   while a conversation is open does all of this at once:
     - heartbeat (keeps you "Active now")
     - marks messages to you as Delivered, and as Seen when the chat is
       open, visible and scrolled to the bottom
     - brings new messages, status changes, reactions and unsends
     - partner typing indicator, presence dots, inbox, unread badge
   ========================================================================= */
(function ($) {
  'use strict';

  var ROOT = window.RS.root;
  var API = ROOT + 'module/chat/api.php';
  var $chat = document.getElementById('rsChat');
  if (!$chat) { return; }

  /* ------------------------------------------------------------ state */
  var S = {
    me: { uid: window.RS.uid, name: window.RS.name, photo: window.RS.photo },
    people: {},
    inbox: [], inboxSig: '',
    with: 0, conv: 0,
    msgs: [], byId: {}, byCid: {},
    lastId: 0, cursor: '', hasMore: false, loadingOlder: false,
    partnerTyping: false,
    myTypingAt: 0, typingSent: 0,
    tray: [],
    timer: null, syncing: false, fails: 0,
    skew: 0,
    unread: 0,
    search: '', peopleMode: false,
    reactions: ['👍', '❤️', '😆', '😮', '😢', '😡'],
    maxUpload: parseInt($chat.getAttribute('data-max-upload'), 10) || 10485760,
    sendChain: Promise.resolve(),
    listSig: '', headSig: '',
    rowCache: {},
    infoOpen: false,
    baseTitle: document.title
  };

  var el = function (id) { return document.getElementById(id); };
  var $list = el('rsList'), $strip = el('rsStrip'), $msgs = el('rsMsgs'), $scroll = el('rsScroll'),
      $input = el('rsInput'), $send = el('rsSend'), $tray = el('rsTray'), $thread = el('rsThread'),
      $empty = el('rsEmpty'), $emoji = el('rsEmoji'), $info = el('rsInfo');

  /* ------------------------------------------------------------ utils */
  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function nowS() { return Date.now() + S.skew; }
  function newCid() {
    var a = new Uint8Array(12);
    (window.crypto || window.msCrypto).getRandomValues(a);
    return 'c' + Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
  }
  function humanSize(b) {
    if (b >= 1048576) { return (b / 1048576).toFixed(1) + ' MB'; }
    if (b >= 1024) { return Math.round(b / 1024) + ' KB'; }
    return b + ' B';
  }
  function firstName(n) { return String(n || '').split(/\s+/)[0]; }

  var DAY = 86400000;
  function startOfDay(ms) { var d = new Date(ms); d.setHours(0, 0, 0, 0); return d.getTime(); }
  function fmtTime(ms) { return new Date(ms).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); }
  function fmtDate(ms, withYear) {
    var o = { month: 'short', day: 'numeric' };
    if (withYear) { o.year = 'numeric'; }
    return new Date(ms).toLocaleDateString([], o);
  }
  /* "3:45 PM", "yesterday 3:45 PM", "Sep 3, 3:45 PM", "Sep 3, 2025, 3:45 PM" */
  function fmtStamp(ms) {
    var today = startOfDay(nowS());
    if (ms >= today) { return fmtTime(ms); }
    if (ms >= today - DAY) { return 'yesterday ' + fmtTime(ms); }
    var sameYear = new Date(ms).getFullYear() === new Date(nowS()).getFullYear();
    return fmtDate(ms, !sameYear) + ', ' + fmtTime(ms);
  }
  /* Centered divider between groups of messages */
  function fmtDivider(ms) {
    var today = startOfDay(nowS());
    if (ms >= today) { return 'Today ' + fmtTime(ms); }
    if (ms >= today - DAY) { return 'Yesterday ' + fmtTime(ms); }
    if (ms >= today - 6 * DAY) { return new Date(ms).toLocaleDateString([], { weekday: 'long' }) + ' ' + fmtTime(ms); }
    var sameYear = new Date(ms).getFullYear() === new Date(nowS()).getFullYear();
    return fmtDate(ms, !sameYear) + ', ' + fmtTime(ms);
  }
  function fmtFull(ms) {
    return new Date(ms).toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
  }
  /* compact "5m", "3h", "2d", "4w", "1y" like the Messenger list */
  function fmtAgo(ms) {
    var s = Math.max(0, Math.round((nowS() - ms) / 1000));
    if (s < 60) { return 'now'; }
    var m = Math.floor(s / 60); if (m < 60) { return m + 'm'; }
    var h = Math.floor(m / 60); if (h < 24) { return h + 'h'; }
    var d = Math.floor(h / 24); if (d < 7) { return d + 'd'; }
    var w = Math.floor(d / 7); if (d < 365) { return w + 'w'; }
    return Math.floor(d / 365) + 'y';
  }
  function fmtAgoLong(ms) {
    var s = Math.max(0, Math.round((nowS() - ms) / 1000));
    if (s < 60) { return 'just now'; }
    var m = Math.floor(s / 60); if (m < 60) { return m + (m === 1 ? ' minute' : ' minutes') + ' ago'; }
    var h = Math.floor(m / 60); if (h < 24) { return h + (h === 1 ? ' hour' : ' hours') + ' ago'; }
    var d = Math.floor(h / 24); return d + (d === 1 ? ' day' : ' days') + ' ago';
  }

  /* Big emoji: 1 to 3 emojis and nothing else, like Messenger */
  var JUMBO_RE = null;
  try { JUMBO_RE = new RegExp('^(?:\\p{Extended_Pictographic}|\\p{Emoji_Modifier}|\\p{Regional_Indicator}|\\u200d|\\ufe0f|\\u20e3|[#*0-9]|\\s)+$', 'u'); } catch (e) { JUMBO_RE = null; }
  function isJumbo(t) {
    if (!t || !JUMBO_RE) { return false; }
    t = t.trim();
    if (!t || !JUMBO_RE.test(t) || /^[\s#*0-9]+$/.test(t)) { return false; }
    var n;
    if (window.Intl && Intl.Segmenter) {
      n = 0; var it = new Intl.Segmenter().segment(t.replace(/\s+/g, ''));
      for (var x of it) { n++; } // eslint-disable-line no-unused-vars
    } else {
      n = Array.from(t.replace(/[\s\u200d\ufe0f\u20e3]|\ud83c[\udffb-\udfff]/g, '')).length;
    }
    return n > 0 && n <= 3;
  }

  function linkify(text) {
    var safe = esc(text);
    return safe.replace(/(\bhttps?:\/\/[^\s<]+[^\s<.,;:!?'")\]]|\bwww\.[^\s<]+[^\s<.,;:!?'")\]])/gi, function (u) {
      var href = /^www\./i.test(u) ? 'http://' + u : u;
      return '<a href="' + href + '" target="_blank" rel="noopener noreferrer">' + u + '</a>';
    });
  }

  var PALETTE = ['#1c2f9e', '#0eb17a', '#0aa5c7', '#c31f5e', '#6b4fbb', '#d9730d', '#2b3648', '#0a7cff'];
  function colorFor(name) {
    var h = 0; name = String(name || '?');
    for (var i = 0; i < name.length; i++) { h = (h * 31 + name.charCodeAt(i)) >>> 0; }
    return PALETTE[h % PALETTE.length];
  }
  function initials(name) {
    var parts = String(name || '?').trim().split(/\s+/);
    var s = (parts[0] || '?').charAt(0) + (parts.length > 1 ? parts[parts.length - 1].charAt(0) : '');
    return s.toUpperCase();
  }
  /* size: '', 'sm', 'xs', 'lg', 'xl' ; badge: true adds the green dot / "12m" pill */
  function avatar(card, size, badge) {
    card = card || { name: '?' };
    var inner = card.photo
      ? '<img src="' + esc(card.photo) + '" alt="" loading="lazy">'
      : '<span class="rs-av-in" style="background:' + colorFor(card.name) + '">' + esc(initials(card.name)) + '</span>';
    var extra = '';
    if (badge) {
      if (card.online) { extra = '<span class="rs-dot" title="Active now"></span>'; }
      else if (card.last_seen && nowS() - card.last_seen < 3600000) {
        extra = '<span class="rs-ago" title="Active ' + esc(fmtAgoLong(card.last_seen)) + '">' + esc(fmtAgo(card.last_seen)) + '</span>';
      }
    }
    return '<span class="rs-av ' + (size || '') + '">' + inner + extra + '</span>';
  }

  var ICON = {
    sending: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.3"/></svg>',
    sent: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.3"/><path d="M5 8.2l2 2 4-4.2" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    delivered: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="7.2" fill="currentColor"/><path d="M5 8.2l2 2 4-4.2" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    failed: '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="7.2" fill="#c31f5e"/><path d="M8 4.2v4.6M8 11.2v.4" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>'
  };
  function receiptState(m) {
    if (m.failed) { return 'failed'; }
    if (m.pending) { return 'sending'; }
    if (m.seen) { return 'seen'; }
    if (m.delivered) { return 'delivered'; }
    return 'sent';
  }
  function receiptIcon(state, partner) {
    if (state === 'seen') { return '<span title="Seen">' + avatar(partner, 'xs') + '</span>'; }
    return '<span class="rs-rc' + (state === 'sending' ? ' muted' : '') + '" title="' + state.charAt(0).toUpperCase() + state.slice(1) + '">' + ICON[state] + '</span>';
  }

  function fileIcon(name, mime) {
    var ext = String(name || '').split('.').pop().toLowerCase();
    if (/pdf/.test(ext)) { return 'fa-file-pdf'; }
    if (/docx?|odt|rtf/.test(ext)) { return 'fa-file-word'; }
    if (/xlsx?|ods|csv/.test(ext)) { return 'fa-file-excel'; }
    if (/pptx?|odp/.test(ext)) { return 'fa-file-powerpoint'; }
    if (/zip|rar|7z/.test(ext)) { return 'fa-file-archive'; }
    if (/mp3|wav|m4a|ogg/.test(ext) || /^audio/.test(mime || '')) { return 'fa-file-audio'; }
    if (/mp4|webm|mov/.test(ext) || /^video/.test(mime || '')) { return 'fa-file-video'; }
    if (/txt/.test(ext)) { return 'fa-file-alt'; }
    return 'fa-file';
  }

  /* ------------------------------------------------------------ network */
  function handleHttpError(status, data) {
    if (status === 401) { window.location = ROOT + 'login.php?expired=1'; }
    if (status === 419) { banner('Your session was refreshed. Reloading...'); setTimeout(function () { window.location.reload(); }, 1200); }
    var err = new Error((data && data.message) || 'Something went wrong. Please try again.');
    err.status = status;
    return err;
  }
  function post(fields) {
    var fd = new FormData();
    Object.keys(fields).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null) { fd.append(k, fields[k]); } });
    return fetch(API, {
      method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store',
      headers: { 'X-CSRF-Token': window.RS.csrf, 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) {
      return r.json().catch(function () { return null; }).then(function (data) {
        if (!r.ok || !data || data.error) { throw handleHttpError(r.status, data); }
        return data;
      });
    });
  }
  /* XHR version for uploads, because fetch has no upload progress. */
  function postWithProgress(fields, onProgress) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      Object.keys(fields).forEach(function (k) { if (fields[k] !== undefined && fields[k] !== null) { fd.append(k, fields[k]); } });
      var x = new XMLHttpRequest();
      x.open('POST', API + '?act=' + encodeURIComponent(fields.act));
      x.setRequestHeader('X-CSRF-Token', window.RS.csrf);
      x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      x.responseType = 'json';
      if (x.upload && onProgress) { x.upload.onprogress = function (e) { if (e.lengthComputable) { onProgress(e.loaded / e.total); } }; }
      x.onload = function () {
        var data = x.response;
        if (x.status >= 200 && x.status < 300 && data && !data.error) { resolve(data); }
        else { reject(handleHttpError(x.status, data)); }
      };
      x.onerror = function () { reject(new Error('No connection. Tap to retry.')); };
      x.send(fd);
    });
  }

  function banner(text) {
    var b = document.createElement('div');
    b.className = 'rs-banner'; b.textContent = text;
    $chat.appendChild(b);
    setTimeout(function () { b.remove(); }, 3800);
  }

  /* ------------------------------------------------------------ people + inbox */
  function mergePeople(list) {
    (list || []).forEach(function (p) { S.people[p.uid] = $.extend(S.people[p.uid] || {}, p); });
  }
  function applyPresence(map) {
    if (!map) { return; }
    Object.keys(map).forEach(function (uid) {
      if (S.people[uid]) { S.people[uid].online = map[uid].online; S.people[uid].last_seen = map[uid].last_seen; }
    });
  }
  function partner() { return S.people[S.with] || { uid: S.with, name: '...' }; }

  function previewText(it) {
    var l = it.last, mine = l.from === S.me.uid, who = firstName(it.partner.name);
    if (l.unsent) { return mine ? 'You unsent a message' : who + ' unsent a message'; }
    if (l.type === 'image') { return mine ? 'You sent a photo' : who + ' sent a photo'; }
    if (l.type === 'file') { return mine ? 'You sent an attachment' : who + ' sent an attachment'; }
    var body = String(l.body || '').replace(/\s+/g, ' ');
    return mine ? 'You: ' + body : body;
  }

  function renderList() {
    var q = S.search.trim().toLowerCase();
    var html = '';
    var inConv = {};
    var chats = S.inbox.filter(function (it) {
      inConv[it.partner.uid] = true;
      var p = S.people[it.partner.uid] || it.partner;
      return !q || String(p.name).toLowerCase().indexOf(q) !== -1 || String(p.username || '').toLowerCase().indexOf(q) !== -1;
    });

    if (!S.peopleMode || q) {
      if (q && chats.length) { html += '<div class="rs-list-label">Chats</div>'; }
      chats.forEach(function (it) {
        var p = S.people[it.partner.uid] || it.partner;
        var unread = it.unread > 0 && it.partner.uid !== S.with;
        var tail = '';
        if (unread) { tail = '<span class="rs-unread-dot">' + (it.unread > 9 ? '9+' : it.unread) + '</span>'; }
        else if (it.last.from === S.me.uid && !it.last.unsent) {
          tail = receiptIcon(it.last.seen ? 'seen' : (it.last.delivered ? 'delivered' : 'sent'), p);
        }
        html += '<button type="button" role="listitem" class="rs-item' + (it.partner.uid === S.with ? ' active' : '') + (unread ? ' unread' : '') + '" data-uid="' + it.partner.uid + '">' +
          avatar(p, '', true) +
          '<span class="rs-item-body"><span class="rs-item-name">' + esc(p.name) + '</span>' +
          '<span class="rs-item-sub"><span class="rs-prev">' + esc(previewText(it)) + '</span><span class="rs-when">&middot; ' + esc(fmtAgo(it.last.sent)) + '</span></span></span>' +
          '<span class="rs-item-tail">' + tail + '</span></button>';
      });
    }

    if (q || S.peopleMode) {
      var ppl = Object.keys(S.people).map(function (k) { return S.people[k]; }).filter(function (p) {
        if (!p.active) { return false; }
        if (!S.peopleMode && inConv[p.uid]) { return false; }
        return !q || String(p.name).toLowerCase().indexOf(q) !== -1 || String(p.username || '').toLowerCase().indexOf(q) !== -1;
      }).sort(function (a, b) { return (b.online - a.online) || String(a.name).localeCompare(String(b.name)); });
      if (ppl.length) { html += '<div class="rs-list-label">' + (S.peopleMode && !q ? 'Start a chat with' : 'People') + '</div>'; }
      ppl.forEach(function (p) {
        html += '<button type="button" role="listitem" class="rs-item" data-uid="' + p.uid + '">' + avatar(p, '', true) +
          '<span class="rs-item-body"><span class="rs-item-name">' + esc(p.name) + '</span>' +
          '<span class="rs-item-sub"><span class="rs-prev">' + esc(p.online ? 'Active now' : (p.type || '')) + '</span></span></span></button>';
      });
    }

    if (!html) {
      html = q
        ? '<div class="rs-list-empty">No one matches "' + esc(S.search) + '".</div>'
        : '<div class="rs-list-empty">No chats yet.<br><button type="button" class="btn btn-primary btn-sm" data-act="people">Start a new chat</button></div>';
    }
    if (html !== S.listSig) { $list.innerHTML = html; S.listSig = html; }

    /* Active now strip */
    var online = Object.keys(S.people).map(function (k) { return S.people[k]; })
      .filter(function (p) { return p.online && p.active; })
      .sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); });
    var strip = online.map(function (p) {
      return '<button type="button" class="rs-strip-item" data-uid="' + p.uid + '" title="' + esc(p.name) + ' is active now">' + avatar(p, '', true) + '<span>' + esc(firstName(p.name)) + '</span></button>';
    }).join('');
    if ($strip.innerHTML !== strip) { $strip.innerHTML = strip; }
  }

  function setUnread(n) {
    S.unread = n;
    document.title = (n > 0 ? '(' + n + ') ' : '') + S.baseTitle;
    if (window.RSNotify) { window.RSNotify.setUnread(n); }
  }

  /* ------------------------------------------------------------ header + status */
  function statusText(p) {
    if (S.partnerTyping) { return { text: 'typing...', cls: 'typing' }; }
    if (p.active === false) { return { text: 'Account deactivated', cls: '' }; }
    if (p.online) { return { text: 'Active now', cls: 'online' }; }
    if (p.last_seen) { return { text: 'Offline since ' + fmtStamp(p.last_seen) + ' (' + fmtAgoLong(p.last_seen) + ')', cls: '' }; }
    return { text: 'Offline', cls: '' };
  }
  function renderHead() {
    if (!S.with) { return; }
    var p = partner(), st = statusText(p);
    var sig = [p.name, p.photo, p.online, p.last_seen, st.text].join('|');
    if (sig === S.headSig) { return; }
    S.headSig = sig;
    el('rsHeadAv').innerHTML = avatar(p, '', true);
    el('rsHeadName').textContent = p.name;
    var hs = el('rsHeadStatus'); hs.textContent = st.text; hs.className = 'rs-head-status ' + st.cls;
    el('rsIntro').innerHTML = avatar(p, 'xl') + '<h4>' + esc(p.name) + '</h4><p>' + esc(p.type || '') + (p.username ? ' &middot; @' + esc(p.username) : '') + '</p>';
    el('rsTypingAv').outerHTML = '<span class="rs-av sm" id="rsTypingAv">' + $(avatar(p, 'sm')).html() + '</span>';
    if (S.infoOpen) {
      el('rsInfoAv').innerHTML = avatar(p, 'xl', true);
      el('rsInfoName').textContent = p.name;
      el('rsInfoType').textContent = (p.type || '') + (p.username ? ' @' + p.username : '');
      el('rsInfoStatus').textContent = statusText(p).text;
    }
  }

  /* ------------------------------------------------------------ messages */
  function sortMsgs() {
    S.msgs.sort(function (a, b) {
      if (a.id && b.id) { return a.id - b.id; }
      if (a.id && !b.id) { return -1; }
      if (!a.id && b.id) { return 1; }
      return a._seq - b._seq;
    });
  }
  var seq = 0;
  function upsert(m) {
    if (m.cid && S.byCid[m.cid] && !S.byCid[m.cid].id) {
      /* our own optimistic message just got confirmed by the server */
      var tmp = S.byCid[m.cid];
      if (tmp._local) { m._local = tmp._local; }
      var idx = S.msgs.indexOf(tmp);
      if (idx !== -1) { S.msgs.splice(idx, 1); }
    }
    var cur = m.id ? S.byId[m.id] : null;
    if (cur) {
      if (cur._local && !m._local) { m._local = cur._local; }
      var i = S.msgs.indexOf(cur);
      S.msgs[i] = m;
    } else {
      m._seq = ++seq;
      S.msgs.push(m);
    }
    if (m.id) { S.byId[m.id] = m; if (m.id > S.lastId) { S.lastId = m.id; } }
    if (m.cid) { S.byCid[m.cid] = m; }
  }

  function nearBottom() { return $scroll.scrollHeight - $scroll.scrollTop - $scroll.clientHeight < 140; }
  function toBottom() { $scroll.scrollTop = $scroll.scrollHeight; el('rsJump').hidden = true; }

  function reactionSummary(m) {
    if (!m.reactions || !m.reactions.length) { return ''; }
    var uniq = [], names = [];
    m.reactions.forEach(function (r) {
      if (uniq.indexOf(r.emoji) === -1) { uniq.push(r.emoji); }
      names.push((r.uid === S.me.uid ? 'You' : firstName((S.people[r.uid] || {}).name || '')) + ' ' + r.emoji);
    });
    return '<span class="rs-reacts" data-act="react" title="' + esc(names.join('\n')) + '">' + uniq.slice(0, 3).join('') +
      (m.reactions.length > 1 ? '<b>' + m.reactions.length + '</b>' : '') + '</span>';
  }

  function contentHTML(m, mine) {
    var who = mine ? 'You' : firstName(partner().name);
    if (m.unsent) { return '<div class="rs-bubble unsent">' + esc(who) + ' unsent a message</div>'; }
    var out = '';
    var prog = m.pending && m._local ? '<span class="rs-progress"><i style="width:' + Math.round((m._progress || 0) * 100) + '%"></i></span>' : '';
    if (m.type === 'image') {
      var src = (m._local && m._local.url) || (m.file && m.file.url) || '';
      var w = (m.file && m.file.w) || (m._local && m._local.w) || 280, h = (m.file && m.file.h) || (m._local && m._local.h) || 200;
      var dispW = Math.min(280, w), dispH = Math.max(60, Math.min(360, Math.round(dispW * h / Math.max(1, w))));
      out += '<a class="rs-img" href="' + esc((m.file && m.file.url) || '#') + '" data-act="view" style="width:' + dispW + 'px;height:' + dispH + 'px">' +
        (src ? '<img src="' + esc(src) + '" alt="' + esc((m.file && m.file.name) || 'Photo') + '" loading="lazy">' : '') + prog + '</a>';
    } else if (m.type === 'file') {
      var f = m.file || (m._local ? { name: m._local.name, size: m._local.size } : { name: 'file', size: 0 });
      var href = m.file ? m.file.dl : '#';
      out += '<a class="rs-file" href="' + esc(href) + '"' + (m.file ? ' download' : '') + '>' +
        '<span class="rs-file-ic"><i class="fas ' + fileIcon(f.name, f.mime) + '"></i></span>' +
        '<span class="rs-file-meta"><span class="rs-file-name" title="' + esc(f.name) + '">' + esc(f.name) + '</span>' +
        '<span class="rs-file-size">' + esc(humanSize(f.size || 0)) + (m.file ? ' &middot; Download' : '') + '</span>' + prog + '</span></a>';
    }
    if (m.body) {
      var jumbo = !m.file && !m._local && isJumbo(m.body);
      out += '<div class="rs-bubble' + (jumbo ? ' jumbo' : '') + (m.type !== 'text' ? ' caption' : '') + (m.failed ? ' failed' : '') + '">' + linkify(m.body) + '</div>';
    }
    return out;
  }

  function tooltip(m, mine) {
    if (m.pending) { return 'Sending...'; }
    var t = 'Sent ' + fmtFull(m.sent);
    if (mine) {
      if (m.delivered) { t += '\nDelivered ' + fmtStamp(m.delivered); }
      if (m.seen) { t += '\nSeen ' + fmtStamp(m.seen); }
    }
    return t;
  }

  function renderThread(opts) {
    opts = opts || {};
    var stick = opts.forceBottom || nearBottom();
    var oldH = $scroll.scrollHeight, oldTop = $scroll.scrollTop;
    var p = partner();
    var blocks = [];

    /* index of my last message, and of my last message the partner has seen */
    var lastMine = -1, lastSeenMine = -1;
    S.msgs.forEach(function (m, i) {
      if (m.from === S.me.uid) { lastMine = i; if (m.seen && !m.unsent) { lastSeenMine = i; } }
    });

    S.msgs.forEach(function (m, i) {
      var prev = S.msgs[i - 1], next = S.msgs[i + 1];
      var mine = m.from === S.me.uid;
      var t = m.sent || nowS();
      var newBlock = !prev || (t - (prev.sent || nowS())) > 15 * 60000;
      if (newBlock) { blocks.push({ key: 'd' + (m.id || m.cid), html: '<div class="rs-divider">' + esc(fmtDivider(t)) + '</div>' }); }
      var joinPrev = prev && !newBlock && prev.from === m.from && (t - (prev.sent || nowS())) < 5 * 60000 && !prev.reactions.length;
      var nt = next ? (next.sent || nowS()) : 0;
      var joinNext = next && next.from === m.from && (nt - t) < 5 * 60000 && (nt - t) <= 15 * 60000 && !m.reactions.length;
      var pos = joinPrev ? (joinNext ? 'middle' : 'last') : (joinNext ? 'first' : 'single');

      var rc = '';
      if (mine) {
        if (i === lastSeenMine) { rc = receiptIcon('seen', p); }
        else if (i === lastMine || m.failed || (m.pending && i === lastMine)) { rc = receiptIcon(receiptState(m), p); }
      }
      var tools = '';
      if (!m.unsent && !m.pending && !m.failed) {
        tools = '<div class="rs-tools"><button type="button" class="rs-iconbtn" data-act="react" title="React" aria-label="React"><i class="far fa-smile"></i></button>' +
          '<button type="button" class="rs-iconbtn" data-act="more" title="More" aria-label="More"><i class="fas fa-ellipsis-v"></i></button></div>';
      }
      var key = m.id ? 'm' + m.id : 'c' + m.cid;
      var html =
        '<div class="rs-row ' + (mine ? 'me' : 'them') + ' ' + pos + (joinPrev ? '' : ' gap') + '" data-key="' + key + '">' +
          (mine ? '<div class="rs-rcol">' + rc + '</div>' : avatar(p, 'sm')) +
          '<div class="rs-stack" title="' + esc(tooltip(m, mine)) + '">' + contentHTML(m, mine) + reactionSummary(m) + '</div>' +
          tools +
        '</div>';
      blocks.push({ key: key, html: html });

      if (mine && i === lastMine && i === S.msgs.length - 1 && !m.unsent) {
        var st = receiptState(m), txt;
        if (st === 'failed') { txt = (m._error || 'Not sent') + '. Tap to retry.'; }
        else if (st === 'sending') { txt = m._local && m._progress < 1 ? 'Uploading ' + Math.round((m._progress || 0) * 100) + '%' : 'Sending...'; }
        else if (st === 'seen') { txt = 'Seen ' + fmtStamp(m.seen); }
        else if (st === 'delivered') { txt = 'Delivered ' + fmtStamp(m.delivered); }
        else { txt = 'Sent ' + fmtStamp(m.sent); }
        blocks.push({ key: 's' + key, html: '<div class="rs-status' + (st === 'failed' ? ' failed' : '') + '"' + (st === 'failed' ? ' data-act="retry" data-cid="' + esc(m.cid) + '"' : '') + '>' + esc(txt) + '</div>' });
      } else if (mine && m.failed) {
        blocks.push({ key: 's' + key, html: '<div class="rs-status failed" data-act="retry" data-cid="' + esc(m.cid) + '">' + esc((m._error || 'Not sent') + '. Tap to retry.') + '</div>' });
      }
    });

    /* Keyed patch: unchanged rows keep their DOM node, so images do not
       reload or flicker every time a status changes. */
    var fresh = {};
    var frag = document.createDocumentFragment();
    blocks.forEach(function (b) {
      var node = S.rowCache[b.key];
      if (!node || node._html !== b.html) {
        var wrap = document.createElement('div'); wrap.innerHTML = b.html;
        node = wrap.firstChild; node._html = b.html;
      }
      fresh[b.key] = node;
      frag.appendChild(node);
    });
    $msgs.innerHTML = '';
    $msgs.appendChild(frag);
    S.rowCache = fresh;

    el('rsIntro').hidden = S.hasMore;
    var older = el('rsOlder');
    older.hidden = !S.hasMore;
    if (S.hasMore) { older.innerHTML = S.loadingOlder ? '<span class="rs-spin"><i class="fas fa-circle-notch fa-spin"></i> Loading...</span>' : '<button type="button" data-act="older">Load earlier messages</button>'; }

    if (opts.keepTop) { $scroll.scrollTop = oldTop + ($scroll.scrollHeight - oldH); }
    else if (stick) { toBottom(); }
  }

  function renderTyping() {
    var t = el('rsTyping');
    var show = !!(S.with && S.partnerTyping);
    if (t.hidden === !show) { return; }
    var stick = nearBottom();
    t.hidden = !show;
    if (stick) { toBottom(); }
  }

  /* ------------------------------------------------------------ open / close */
  function openThread(uid, fromUser) {
    uid = parseInt(uid, 10);
    if (!uid || uid === S.me.uid) { return; }
    if (uid === S.with) { if (fromUser) { $input.focus(); } return; }
    S.with = uid; S.conv = 0; S.msgs = []; S.byId = {}; S.byCid = {}; S.rowCache = {};
    S.lastId = 0; S.cursor = ''; S.hasMore = false; S.partnerTyping = false; S.headSig = '';
    S.peopleMode = false; S.search = ''; el('rsSearch').value = '';
    clearTray(); closePops(); $emoji.hidden = true;
    $msgs.innerHTML = ''; el('rsTyping').hidden = true;
    $empty.hidden = true; $thread.hidden = false;
    $chat.classList.add('has-thread');
    renderHead(); renderList();
    try { history.replaceState(null, '', ROOT + 'module/chat/?u=' + uid); } catch (e) {}
    post({ act: 'open', with: uid, seen: document.hidden ? 0 : 1 }).then(function (d) {
      if (S.with !== uid) { return; }
      S.people[uid] = $.extend(S.people[uid] || {}, d.partner);
      S.conv = d.conv; S.cursor = d.cursor; S.hasMore = d.has_more; S.partnerTyping = d.typing;
      d.messages.forEach(upsert);
      renderHead(); renderThread({ forceBottom: true }); renderTyping();
      if (S.infoOpen) { loadInfo(); }
      scheduleSync(250);
    }).catch(function (e) { banner(e.message); });
    if (fromUser && window.matchMedia('(min-width: 768px)').matches) { $input.focus(); }
    updateSendBtn();
  }

  function closeThread() {
    S.with = 0; S.conv = 0; S.msgs = []; S.rowCache = {};
    $thread.hidden = true; $empty.hidden = false;
    $chat.classList.remove('has-thread');
    toggleInfo(false);
    try { history.replaceState(null, '', ROOT + 'module/chat/'); } catch (e) {}
    renderList();
  }

  function loadOlder() {
    if (S.loadingOlder || !S.hasMore || !S.msgs.length) { return; }
    var first = S.msgs.filter(function (m) { return m.id; })[0];
    if (!first) { return; }
    S.loadingOlder = true; renderThread({ keepTop: true });
    var uid = S.with;
    post({ act: 'history', with: uid, before: first.id }).then(function (d) {
      if (S.with !== uid) { return; }
      S.loadingOlder = false; S.hasMore = d.has_more;
      d.messages.forEach(function (m) { if (!S.byId[m.id]) { upsert(m); } });
      sortMsgs(); renderThread({ keepTop: true });
    }).catch(function (e) { S.loadingOlder = false; renderThread({ keepTop: true }); banner(e.message); });
  }

  /* ------------------------------------------------------------ sync loop */
  function wantTyping() {
    return (S.with && $input.value.trim() !== '' && Date.now() - S.myTypingAt < 4000) ? 1 : 0;
  }
  function canMarkSeen() { return !!(S.with && !document.hidden && nearBottom()); }
  function syncDelay() {
    if (S.fails) { return Math.min(10000, 1500 * Math.pow(2, S.fails - 1)); }
    if (document.hidden) { return 5000; }
    return S.with ? 1500 : 3000;
  }
  /* While a sync is in flight, a request for an earlier one is remembered
     and applied when it finishes, so a quick follow-up (typing, seen) is
     never pushed back by the regular interval. */
  function scheduleSync(ms) {
    if (ms === undefined) { ms = syncDelay(); }
    if (S.syncing) { S.pendingDelay = Math.min(S.pendingDelay === null || S.pendingDelay === undefined ? Infinity : S.pendingDelay, ms); return; }
    clearTimeout(S.timer);
    S.timer = setTimeout(sync, ms);
  }

  function sync() {
    if (S.syncing) { return; }
    clearTimeout(S.timer);
    S.syncing = true; S.pendingDelay = null;
    var uid = S.with;
    var typing = wantTyping();
    var req = { act: 'sync', with: uid, after: S.lastId, cursor: S.cursor, inbox_sig: S.inboxSig, seen: canMarkSeen() ? 1 : 0 };
    if (typing || S.typingSent) { req.typing = typing; }
    var prevUnread = S.unread;

    post(req).then(function (d) {
      S.fails = 0; el('rsOffline').hidden = true;
      S.skew = d.now - Date.now();
      S.typingSent = typing;
      if (d.presence) { applyPresence(d.presence); }
      if (d.inbox) {
        S.inbox = d.inbox; S.inboxSig = d.inbox_sig;
        d.inbox.forEach(function (it) { S.people[it.partner.uid] = $.extend(S.people[it.partner.uid] || {}, it.partner); });
        if (window.RSNotify) { window.RSNotify.renderInbox(d.inbox); }
      }
      if (typeof d.unread === 'number') {
        if (d.unread > prevUnread && (document.hidden || d.unread > 0)) { if (window.RSNotify) { window.RSNotify.sound(); } }
        setUnread(d.unread);
      }

      var th = d.thread;
      if (th && th.with === S.with && uid === S.with) {
        var changed = false, gotIncoming = false;
        if (th.conv) { S.conv = th.conv; }
        th.messages.forEach(function (m) { upsert(m); changed = true; if (m.from !== S.me.uid && !m.seen) { gotIncoming = true; } });
        th.updates.forEach(function (m) {
          var cur = S.byId[m.id];
          if (cur && JSON.stringify([cur.delivered, cur.seen, cur.unsent, cur.reactions, cur.body]) !== JSON.stringify([m.delivered, m.seen, m.unsent, m.reactions, m.body])) {
            upsert(m); changed = true;
          }
        });
        S.cursor = d.cursor;
        if (changed) {
          sortMsgs();
          var wasBottom = nearBottom();
          renderThread();
          if (gotIncoming && !wasBottom) { el('rsJump').hidden = false; }
          if (gotIncoming && document.hidden && window.RSNotify) { window.RSNotify.sound(); }
          if (gotIncoming && canMarkSeen()) { scheduleSync(350); }
        }
        if (S.partnerTyping !== th.typing) { S.partnerTyping = th.typing; S.headSig = ''; renderTyping(); }
      }
      renderHead(); renderList();
    }).catch(function (e) {
      S.fails++;
      if (S.fails >= 2 && S.with) { el('rsOffline').hidden = false; }
      if (window.console) { console.warn('[chat] sync failed:', e.message); }
    }).then(function () {
      S.syncing = false;
      var next = (S.pendingDelay === null || S.pendingDelay === undefined) ? syncDelay() : Math.min(S.pendingDelay, syncDelay());
      S.pendingDelay = null;
      scheduleSync(next);
    });
  }

  /* ------------------------------------------------------------ sending */
  function queueSend(job) {
    S.sendChain = S.sendChain.then(job, job);
    return S.sendChain;
  }

  function addPending(m) {
    m.pending = true; m.failed = false; m.reactions = []; m.sent = nowS();
    m.delivered = null; m.seen = null; m.unsent = false; m.id = 0;
    m.from = S.me.uid; m.to = S.with;
    upsert(m);
    renderThread({ forceBottom: true });
  }

  function doSend(m) {
    var fields = { act: 'send', with: m.to, body: m.body || '', cid: m.cid };
    if (m._local && m._local.file) { fields.file = m._local.file; }
    var onProg = m._local ? function (f) {
      m._progress = f;
      var node = $msgs.querySelector('[data-key="c' + m.cid + '"] .rs-progress i');
      if (node) { node.style.width = Math.round(f * 100) + '%'; }
      var st = $msgs.querySelector('.rs-status');
      if (st && f < 1) { st.textContent = 'Uploading ' + Math.round(f * 100) + '%'; }
    } : null;
    var p = m._local ? postWithProgress(fields, onProg) : post(fields);
    return p.then(function (d) {
      if (m.to !== S.with) { return; }
      var real = d.message;
      if (m._local && m._local.url && real.type === 'image' && real.file) {
        /* Keep showing the local preview until the server copy has loaded.
           The same _local object is shared by every copy of this message
           (the send reply and any sync update), so clearing its url here
           switches ALL of them to the server photo at once. The preview
           is only released after the page no longer uses it. */
        var loc = m._local, img = new Image();
        img.onload = function () {
          var blobUrl = loc.url;
          if (!blobUrl) { return; }
          loc.url = null;
          S.rowCache = {}; renderThread();
          setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 2000);
        };
        img.src = real.file.url;
      }
      upsert(real); sortMsgs(); renderThread();
      scheduleSync(400);
    }).catch(function (e) {
      m.failed = true; m.pending = false; m._error = e.message.replace(/\.? ?Tap to retry\.?$/, '');
      renderThread();
    });
  }

  function sendAll() {
    if (!S.with) { return; }
    var text = $input.value.replace(/\s+$/, '');
    var files = S.tray.slice();
    if (!text.trim() && !files.length) { text = '👍'; }
    clearTray(true);
    $emoji.hidden = true;
    $input.value = ''; autoGrow(); S.myTypingAt = 0; updateSendBtn();

    files.forEach(function (t) {
      var m = { cid: newCid(), type: t.isImage ? 'image' : 'file', body: '', file: null,
        _local: { file: t.file, url: t.url, name: t.file.name, size: t.file.size, w: t.w, h: t.h }, _progress: 0 };
      addPending(m);
      queueSend(function () { return doSend(m); });
    });
    if (text.trim()) {
      var m = { cid: newCid(), type: 'text', body: text, file: null };
      addPending(m);
      queueSend(function () { return doSend(m); });
    }
  }

  function retry(cid) {
    var m = S.byCid[cid];
    if (!m || !m.failed) { return; }
    m.failed = false; m.pending = true; m._progress = 0;
    renderThread();
    queueSend(function () { return doSend(m); });
  }

  /* ------------------------------------------------------------ composer */
  function autoGrow() {
    $input.style.height = 'auto';
    $input.style.height = Math.min(120, $input.scrollHeight) + 'px';
  }
  function updateSendBtn() {
    var hasContent = $input.value.trim() !== '' || S.tray.length > 0;
    $send.classList.toggle('like', !hasContent);
    $send.innerHTML = hasContent ? '<i class="fas fa-paper-plane"></i>' : '👍';
    $send.title = hasContent ? 'Press Enter to send' : 'Send a like';
  }

  function addFiles(list) {
    if (!S.with) { return; }
    Array.prototype.forEach.call(list || [], function (f) {
      if (S.tray.length >= 10) { banner('You can send up to 10 files at a time.'); return; }
      if (f.size > S.maxUpload) { banner('"' + f.name + '" is larger than ' + humanSize(S.maxUpload) + '.'); return; }
      if (f.size === 0) { banner('"' + f.name + '" is empty.'); return; }
      var isImage = /^image\/(jpeg|png|gif|webp|bmp)$/i.test(f.type);
      var t = { id: newCid(), file: f, isImage: isImage, url: isImage ? URL.createObjectURL(f) : null, w: 0, h: 0 };
      if (isImage) {
        var probe = new Image();
        probe.onload = function () { t.w = probe.naturalWidth; t.h = probe.naturalHeight; };
        probe.src = t.url;
      }
      S.tray.push(t);
    });
    renderTray(); updateSendBtn(); $input.focus();
  }
  function renderTray() {
    $tray.innerHTML = S.tray.map(function (t) {
      return t.isImage
        ? '<div class="rs-chip"><img src="' + esc(t.url) + '" alt=""><button type="button" class="rs-x" data-tray="' + t.id + '" aria-label="Remove">&times;</button></div>'
        : '<div class="rs-chip file"><i class="fas ' + fileIcon(t.file.name, t.file.type) + '"></i><span title="' + esc(t.file.name) + '">' + esc(t.file.name) + '</span><button type="button" class="rs-x" data-tray="' + t.id + '" aria-label="Remove">&times;</button></div>';
    }).join('');
  }
  function clearTray(keepUrls) {
    if (!keepUrls) { S.tray.forEach(function (t) { if (t.url) { URL.revokeObjectURL(t.url); } }); }
    S.tray = []; renderTray();
  }

  /* emoji picker
     One scrolling list with every category (Messenger style). The tabs
     only jump to a section; they are built once and never redrawn, so a
     click on them can never be mistaken for a click outside the picker. */
  var RECENT_KEY = 'rsEmojiRecent';
  var emojiBuilt = false, emojiSections = [];
  function recentEmojis() { try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; } }
  function pushRecent(e) {
    var r = recentEmojis().filter(function (x) { return x !== e; });
    r.unshift(e);
    try { localStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, 24))); } catch (err) {}
  }
  function emojiName(e) { return (window.RS_EMOJI_NAMES && window.RS_EMOJI_NAMES[e]) || ''; }
  function emojiCell(e) { return '<button type="button" data-emoji="' + esc(e) + '" title="' + esc(emojiName(e)) + '">' + e + '</button>'; }

  function buildEmoji() {
    if (emojiBuilt) { return; }
    emojiBuilt = true;
    var cats = window.RS_EMOJI || [];
    var oldTitle = el('rsEmojiTitle');           /* each section has its own heading now */
    if (oldTitle) { oldTitle.parentNode.removeChild(oldTitle); }
    var search = document.createElement('div');
    search.className = 'rs-emoji-search';
    search.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i><input type="search" id="rsEmojiSearch" placeholder="Search emoji (heart, laugh, flag...)" autocomplete="off" aria-label="Search emoji">';
    $emoji.insertBefore(search, $emoji.firstChild);

    el('rsEmojiTabs').innerHTML = '<button type="button" data-tab="recent" title="Recently used">🕘</button>' +
      cats.map(function (c) { return '<button type="button" data-tab="' + c.key + '" title="' + esc(c.title) + '">' + c.icon + '</button>'; }).join('');

    var html = '<section class="rs-emoji-sec" data-sec="recent"><div class="rs-emoji-title">Recently used</div><div class="rs-emoji-cells" id="rsEmojiRecent"></div></section>';
    cats.forEach(function (c) {
      html += '<section class="rs-emoji-sec" data-sec="' + c.key + '"><div class="rs-emoji-title">' + esc(c.title) + '</div><div class="rs-emoji-cells">' + c.list.map(emojiCell).join('') + '</div></section>';
    });
    html += '<section class="rs-emoji-sec" data-sec="search" hidden><div class="rs-emoji-title" id="rsEmojiResultsTitle">Search results</div><div class="rs-emoji-cells" id="rsEmojiResults"></div></section>';
    el('rsEmojiGrid').innerHTML = html;
    emojiSections = Array.prototype.slice.call(el('rsEmojiGrid').querySelectorAll('.rs-emoji-sec'));

    el('rsEmojiGrid').addEventListener('scroll', syncEmojiTab);
    el('rsEmojiSearch').addEventListener('input', function () { filterEmoji(this.value); });
    el('rsEmojiSearch').addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); $emoji.hidden = true; $input.focus(); }
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = el('rsEmojiResults').querySelector('[data-emoji]');
        if (first && this.value.trim()) { var em = first.getAttribute('data-emoji'); pushRecent(em); insertAtCaret(em); }
      }
    });
  }
  function setEmojiTab(key) {
    el('rsEmojiTabs').querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-tab') === key); });
  }
  /* highlight the tab of the section being scrolled through */
  function syncEmojiTab() {
    var grid = el('rsEmojiGrid'), top = grid.scrollTop + 8, cur = null;
    emojiSections.forEach(function (sec) { if (!sec.hidden && sec.getAttribute('data-sec') !== 'search' && sec.offsetTop <= top) { cur = sec; } });
    if (cur) { setEmojiTab(cur.getAttribute('data-sec')); }
  }
  function jumpEmoji(key) {
    var s = el('rsEmojiSearch');
    if (s && s.value) { s.value = ''; filterEmoji(''); }
    var sec = el('rsEmojiGrid').querySelector('[data-sec="' + key + '"]');
    if (!sec || sec.hidden) { sec = el('rsEmojiGrid').querySelector('[data-sec="smileys"]'); key = 'smileys'; }
    if (sec) { el('rsEmojiGrid').scrollTop = sec.offsetTop; }
    setEmojiTab(key);
  }
  function filterEmoji(q) {
    q = String(q || '').trim().toLowerCase();
    var results = el('rsEmojiGrid').querySelector('[data-sec="search"]');
    emojiSections.forEach(function (sec) { if (sec !== results) { sec.hidden = !!q || (sec.getAttribute('data-sec') === 'recent' && !recentEmojis().length); } });
    results.hidden = !q;
    if (!q) { return; }
    setEmojiTab(null);
    var words = q.split(/\s+/), seen = {}, found = [];
    (window.RS_EMOJI || []).forEach(function (c) {
      c.list.forEach(function (e) {
        if (seen[e]) { return; }
        var n = emojiName(e);
        if (words.every(function (w) { return n.indexOf(w) !== -1; })) { seen[e] = 1; found.push(e); }
      });
    });
    el('rsEmojiResultsTitle').textContent = found.length ? 'Search results' : 'No emoji found for "' + q + '"';
    el('rsEmojiResults').innerHTML = found.map(emojiCell).join('');
    el('rsEmojiGrid').scrollTop = 0;
  }
  function openEmoji() {
    buildEmoji();
    var recent = recentEmojis();
    el('rsEmojiRecent').innerHTML = recent.map(emojiCell).join('');
    el('rsEmojiSearch').value = '';
    filterEmoji('');
    $emoji.hidden = false;
    jumpEmoji(recent.length ? 'recent' : 'smileys');
  }

  function insertAtCaret(text) {
    var s = $input.selectionStart || $input.value.length, e = $input.selectionEnd || s;
    $input.value = $input.value.slice(0, s) + text + $input.value.slice(e);
    var pos = s + text.length;
    $input.focus();
    try { $input.setSelectionRange(pos, pos); } catch (err) {}
    autoGrow(); updateSendBtn();
  }

  /* ------------------------------------------------------------ popovers */
  function closePops() {
    $chat.querySelectorAll('.rs-pop').forEach(function (p) { p.remove(); });
    $chat.querySelectorAll('.rs-row.tools-open').forEach(function (r) { r.classList.remove('tools-open'); });
  }
  function placePop(pop, anchor, mine) {
    $chat.appendChild(pop);
    var cr = $chat.getBoundingClientRect(), ar = anchor.getBoundingClientRect(), pr = pop.getBoundingClientRect();
    var top = ar.top - cr.top - pr.height - 6;
    if (top < 8) { top = ar.bottom - cr.top + 6; }
    var left = mine ? (ar.right - cr.left - pr.width) : (ar.left - cr.left);
    left = Math.max(8, Math.min(left, cr.width - pr.width - 8));
    pop.style.top = top + 'px'; pop.style.left = left + 'px';
  }
  function msgFromRow(row) {
    var key = row && row.getAttribute('data-key');
    if (!key) { return null; }
    return key.charAt(0) === 'm' ? S.byId[parseInt(key.slice(1), 10)] : S.byCid[key.slice(1)];
  }
  function openReact(row, anchor) {
    closePops();
    var m = msgFromRow(row); if (!m || !m.id) { return; }
    var mineR = (m.reactions || []).filter(function (r) { return r.uid === S.me.uid; })[0];
    var pop = document.createElement('div');
    pop.className = 'rs-pop react'; pop.setAttribute('role', 'menu');
    pop.innerHTML = S.reactions.map(function (e) {
      return '<button type="button" data-react="' + esc(e) + '" data-id="' + m.id + '" class="' + (mineR && mineR.emoji === e ? 'mine' : '') + '" title="' + esc(e) + '">' + e + '</button>';
    }).join('');
    row.classList.add('tools-open');
    placePop(pop, anchor || row.querySelector('.rs-stack'), m.from === S.me.uid);
  }
  function openMenu(row, anchor) {
    closePops();
    var m = msgFromRow(row); if (!m || !m.id) { return; }
    var mine = m.from === S.me.uid;
    var items = '';
    if (m.body) { items += '<button type="button" data-menu="copy" data-id="' + m.id + '"><i class="far fa-copy fa-fw"></i> Copy text</button>'; }
    if (m.file) { items += '<button type="button" data-menu="download" data-id="' + m.id + '"><i class="fas fa-download fa-fw"></i> Download</button>'; }
    items += '<button type="button" data-menu="info" data-id="' + m.id + '"><i class="far fa-clock fa-fw"></i> Message details</button>';
    if (mine) { items += '<button type="button" class="danger" data-menu="unsend" data-id="' + m.id + '"><i class="fas fa-undo fa-fw"></i> Unsend for everyone</button>'; }
    var pop = document.createElement('div');
    pop.className = 'rs-pop menu'; pop.setAttribute('role', 'menu'); pop.innerHTML = items;
    row.classList.add('tools-open');
    placePop(pop, anchor, mine);
  }

  function react(id, emoji) {
    var m = S.byId[id]; if (!m) { return; }
    var mineR = (m.reactions || []).filter(function (r) { return r.uid === S.me.uid; })[0];
    var sendEmoji = (mineR && mineR.emoji === emoji) ? '' : emoji;
    /* optimistic */
    m.reactions = (m.reactions || []).filter(function (r) { return r.uid !== S.me.uid; });
    if (sendEmoji) { m.reactions.push({ uid: S.me.uid, emoji: sendEmoji }); }
    renderThread();
    post({ act: 'react', id: id, emoji: sendEmoji }).then(function (d) { upsert(d.message); renderThread(); })
      .catch(function (e) { banner(e.message); });
  }
  function unsend(id) {
    var go = function () {
      post({ act: 'unsend', id: id }).then(function (d) { upsert(d.message); renderThread(); if (S.infoOpen) { loadInfo(); } })
        .catch(function (e) { banner(e.message); });
    };
    if (window.Swal) {
      Swal.fire({ title: 'Unsend this message?', text: 'It will be removed for everyone in the chat. They may have already seen it.', icon: 'warning',
        showCancelButton: true, confirmButtonText: 'Unsend', cancelButtonText: 'Cancel' })
        .then(function (r) { if (r.value || r.isConfirmed) { go(); } });
    } else if (window.confirm('Unsend this message for everyone?')) { go(); }
  }
  function details(id) {
    var m = S.byId[id]; if (!m) { return; }
    var lines = ['Sent: ' + fmtFull(m.sent)];
    if (m.from === S.me.uid) {
      lines.push('Delivered: ' + (m.delivered ? fmtFull(m.delivered) : 'not yet'));
      lines.push('Seen: ' + (m.seen ? fmtFull(m.seen) : 'not yet'));
    }
    if (window.Swal) { Swal.fire({ title: 'Message details', html: lines.map(esc).join('<br>'), icon: 'info' }); }
    else { window.alert(lines.join('\n')); }
  }

  /* ------------------------------------------------------------ info panel + lightbox */
  function toggleInfo(open) {
    S.infoOpen = (open === undefined) ? !S.infoOpen : open;
    $info.hidden = !S.infoOpen;
    if (S.infoOpen) { S.headSig = ''; renderHead(); loadInfo(); }
  }
  function loadInfo() {
    if (!S.with) { return; }
    var uid = S.with;
    el('rsInfoMedia').innerHTML = '<div class="rs-info-none">Loading...</div>'; el('rsInfoFiles').innerHTML = '';
    post({ act: 'media', with: uid }).then(function (d) {
      if (S.with !== uid) { return; }
      var imgs = d.items.filter(function (m) { return m.type === 'image'; });
      var files = d.items.filter(function (m) { return m.type === 'file'; });
      el('rsInfoMedia').innerHTML = imgs.length ? imgs.map(function (m) {
        return '<button type="button" data-view-id="' + m.id + '" title="' + esc(fmtFull(m.sent)) + '"><img src="' + esc(m.file.url) + '" alt="" loading="lazy"></button>';
      }).join('') : '<div class="rs-info-none">No photos yet.</div>';
      el('rsInfoFiles').innerHTML = files.length ? files.map(function (m) {
        return '<a href="' + esc(m.file.dl) + '" download><i class="fas ' + fileIcon(m.file.name, m.file.mime) + '"></i><span title="' + esc(m.file.name) + '">' + esc(m.file.name) + ' (' + humanSize(m.file.size) + ')</span></a>';
      }).join('') : '<div class="rs-info-none">No files yet.</div>';
      d.items.forEach(function (m) { if (!S.byId[m.id]) { S._media = S._media || {}; S._media[m.id] = m; } });
    }).catch(function (e) { el('rsInfoMedia').innerHTML = '<div class="rs-info-none">' + esc(e.message) + '</div>'; });
  }
  function viewImage(m) {
    if (!m || !m.file) { return; }
    var who = m.from === S.me.uid ? 'You' : partner().name;
    el('rsLightboxImg').src = m.file.url;
    el('rsLightboxImg').alt = m.file.name || 'Photo';
    el('rsLightboxDl').href = m.file.dl;
    el('rsLightboxCap').textContent = who + ', ' + fmtFull(m.sent);
    el('rsLightbox').hidden = false;
    el('rsLightboxClose').focus();
  }
  function closeLightbox() { el('rsLightbox').hidden = true; el('rsLightboxImg').src = ''; }

  /* ------------------------------------------------------------ layout */
  function fit() {
    var top = $chat.getBoundingClientRect().top + window.pageYOffset;
    var footer = document.querySelector('.main-footer');
    var fh = footer ? footer.offsetHeight : 0;
    var h = Math.max(440, window.innerHeight - top - fh - 8);
    $chat.style.height = h + 'px';
  }

  /* ------------------------------------------------------------ events */
  function bind() {
    $list.addEventListener('click', function (e) {
      var b = e.target.closest('[data-uid]');
      if (b) { openThread(b.getAttribute('data-uid'), true); return; }
      if (e.target.closest('[data-act="people"]')) { S.peopleMode = true; renderList(); el('rsSearch').focus(); }
    });
    $strip.addEventListener('click', function (e) {
      var b = e.target.closest('[data-uid]'); if (b) { openThread(b.getAttribute('data-uid'), true); }
    });
    el('rsSearch').addEventListener('input', function () { S.search = this.value; renderList(); });
    el('rsSearch').addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { this.value = ''; S.search = ''; S.peopleMode = false; renderList(); }
      if (e.key === 'Enter') { var first = $list.querySelector('[data-uid]'); if (first) { openThread(first.getAttribute('data-uid'), true); } }
    });
    var startNew = function () { S.peopleMode = true; S.search = ''; el('rsSearch').value = ''; if (window.matchMedia('(max-width: 767.98px)').matches) { closeThread(); } renderList(); el('rsSearch').focus(); };
    el('rsNew').addEventListener('click', startNew);
    el('rsEmptyNew').addEventListener('click', startNew);
    el('rsBack').addEventListener('click', closeThread);
    el('rsInfoBtn').addEventListener('click', function () { toggleInfo(); });

    $scroll.addEventListener('scroll', function () {
      if ($scroll.scrollTop < 60) { loadOlder(); }
      if (nearBottom()) {
        if (!el('rsJump').hidden) { el('rsJump').hidden = true; }
        /* reached the bottom with unseen messages: tell the sender now */
        var unseen = S.msgs.some(function (m) { return m.from !== S.me.uid && !m.seen && m.id; });
        if (unseen) { scheduleSync(200); }
      }
    });
    el('rsJump').addEventListener('click', function () { toBottom(); scheduleSync(150); });

    $msgs.addEventListener('click', function (e) {
      var row = e.target.closest('.rs-row');
      var act = e.target.closest('[data-act]');
      if (act) {
        var a = act.getAttribute('data-act');
        if (a === 'react') { e.preventDefault(); openReact(row, act); return; }
        if (a === 'more') { e.preventDefault(); openMenu(row, act); return; }
        if (a === 'view') {
          e.preventDefault();
          var mv = msgFromRow(row);
          if (act.classList.contains('broken') && mv && mv.file) {
            var bi = act.querySelector('img');
            act.classList.remove('broken');
            if (bi) { bi.setAttribute('data-try', '0'); bi.src = mv.file.url + '&r=' + Date.now(); }
            return;
          }
          if (mv && mv.file) { viewImage(mv); }
          return;
        }
        if (a === 'retry') { retry(act.getAttribute('data-cid')); return; }
      }
      var fileLink = e.target.closest('.rs-file');
      if (fileLink && fileLink.getAttribute('href') === '#') { e.preventDefault(); }
    });
    /* A photo that fails to load is never left as a broken icon: a stale
       preview switches to the server copy, a failed download is retried
       twice, then a "Photo not loaded, click to retry" placeholder shows. */
    $msgs.addEventListener('error', function (e) {
      var t = e.target;
      if (!t || t.tagName !== 'IMG' || !t.closest('.rs-img')) { return; }
      var m = msgFromRow(t.closest('.rs-row'));
      if (!m || !m.file) { return; }
      var tries = parseInt(t.getAttribute('data-try') || '0', 10);
      if (tries < 2) {
        t.setAttribute('data-try', String(tries + 1));
        setTimeout(function () { t.src = m.file.url + '&r=' + Date.now(); }, tries ? 1500 : 0);
        return;
      }
      t.closest('.rs-img').classList.add('broken');
    }, true);

    $msgs.addEventListener('dblclick', function (e) {
      var row = e.target.closest('.rs-row');
      var m = row && msgFromRow(row);
      if (m && m.id && !m.unsent && !e.target.closest('a')) { react(m.id, '❤️'); }
    });
    el('rsOlder').addEventListener('click', function (e) { if (e.target.closest('[data-act="older"]')) { loadOlder(); } });

    $chat.addEventListener('click', function (e) {
      var r = e.target.closest('[data-react]');
      if (r) { react(parseInt(r.getAttribute('data-id'), 10), r.getAttribute('data-react')); closePops(); return; }
      var mi = e.target.closest('[data-menu]');
      if (mi) {
        var id = parseInt(mi.getAttribute('data-id'), 10), m = S.byId[id], what = mi.getAttribute('data-menu');
        closePops();
        if (what === 'copy' && m) {
          if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(m.body).then(function () { banner('Copied'); }); }
          else { var ta = document.createElement('textarea'); ta.value = m.body; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); banner('Copied'); } catch (err) {} ta.remove(); }
        }
        if (what === 'download' && m && m.file) { window.location = m.file.dl; }
        if (what === 'unsend') { unsend(id); }
        if (what === 'info') { details(id); }
        return;
      }
      var v = e.target.closest('[data-view-id]');
      if (v) { var vid = parseInt(v.getAttribute('data-view-id'), 10); viewImage(S.byId[vid] || (S._media || {})[vid]); return; }
      if (!e.target.closest('.rs-pop') && !e.target.closest('[data-act="react"]') && !e.target.closest('[data-act="more"]')) { closePops(); }
      /* close the picker only on a real click outside it (a node that was
         just removed from the page is not "outside") */
      if (e.target.isConnected && !$emoji.contains(e.target) && !e.target.closest('#rsEmojiBtn')) { $emoji.hidden = true; }
    });

    /* composer */
    $input.addEventListener('input', function () {
      autoGrow(); updateSendBtn();
      if ($input.value.trim()) {
        var was = wantTyping();
        S.myTypingAt = Date.now();
        if (!was && !S.typingSent) { scheduleSync(150); }
      }
    });
    $input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); sendAll(); }
      if (e.key === 'Escape') { $emoji.hidden = true; closePops(); }
    });
    $input.addEventListener('paste', function (e) {
      var files = e.clipboardData && e.clipboardData.files;
      if (files && files.length) { e.preventDefault(); addFiles(files); }
    });
    $send.addEventListener('click', sendAll);
    el('rsPickImage').addEventListener('click', function () { el('rsFileImage').click(); });
    el('rsPickFile').addEventListener('click', function () { el('rsFileAny').click(); });
    ['rsFileImage', 'rsFileAny'].forEach(function (id) {
      el(id).addEventListener('change', function () { addFiles(this.files); this.value = ''; });
    });
    $tray.addEventListener('click', function (e) {
      var x = e.target.closest('[data-tray]'); if (!x) { return; }
      var id = x.getAttribute('data-tray');
      S.tray = S.tray.filter(function (t) { if (t.id === id && t.url) { URL.revokeObjectURL(t.url); } return t.id !== id; });
      renderTray(); updateSendBtn();
    });

    el('rsEmojiBtn').addEventListener('click', function (e) {
      e.stopPropagation();
      if ($emoji.hidden) { openEmoji(); } else { $emoji.hidden = true; }
    });
    el('rsEmojiTabs').addEventListener('click', function (e) { var b = e.target.closest('[data-tab]'); if (b) { jumpEmoji(b.getAttribute('data-tab')); } });
    el('rsEmojiGrid').addEventListener('click', function (e) {
      var b = e.target.closest('[data-emoji]'); if (!b) { return; }
      var em = b.getAttribute('data-emoji'); pushRecent(em); insertAtCaret(em);
    });

    /* drag and drop */
    var depth = 0;
    var hasFiles = function (e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1; };
    $thread.addEventListener('dragenter', function (e) { if (!hasFiles(e)) { return; } e.preventDefault(); depth++; el('rsDrop').hidden = false; });
    $thread.addEventListener('dragover', function (e) { if (hasFiles(e)) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
    $thread.addEventListener('dragleave', function () { depth = Math.max(0, depth - 1); if (!depth) { el('rsDrop').hidden = true; } });
    $thread.addEventListener('drop', function (e) {
      if (!hasFiles(e)) { return; }
      e.preventDefault(); depth = 0; el('rsDrop').hidden = true; addFiles(e.dataTransfer.files);
    });

    /* lightbox */
    el('rsLightboxClose').addEventListener('click', closeLightbox);
    el('rsLightbox').addEventListener('click', function (e) { if (e.target === this) { closeLightbox(); } });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { if (!el('rsLightbox').hidden) { closeLightbox(); } else { closePops(); $emoji.hidden = true; } }
    });

    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) { scheduleSync(100); }
    });
    window.addEventListener('resize', fit);
    $(document).on('collapsed.lte.pushmenu shown.lte.pushmenu', fit);

    /* relative times ("5m", "Offline since ... (12 minutes ago)") refresh */
    setInterval(function () { S.listSig = ''; S.headSig = ''; renderList(); renderHead(); }, 30000);
  }

  /* ------------------------------------------------------------ boot */
  function boot() {
    fit(); bind(); updateSendBtn();
    post({ act: 'bootstrap' }).then(function (d) {
      if (d.me) { S.me = $.extend(S.me, d.me); }
      S.skew = d.now - Date.now();
      if (d.reactions) { S.reactions = d.reactions; }
      if (d.max_upload) { S.maxUpload = d.max_upload; }
      mergePeople(d.people);
      S.inbox = d.inbox;
      d.inbox.forEach(function (it) { S.people[it.partner.uid] = $.extend(S.people[it.partner.uid] || {}, it.partner); });
      setUnread(d.unread);
      if (window.RSNotify) { window.RSNotify.renderInbox(d.inbox); }
      renderList();
      var want = parseInt($chat.getAttribute('data-open'), 10);
      if (want && want !== S.me.uid) { openThread(want, true); }
      else if (d.inbox.length && window.matchMedia('(min-width: 768px)').matches) { openThread(d.inbox[0].partner.uid, false); }
      scheduleSync(1200);
    }).catch(function (e) {
      $list.innerHTML = '<div class="rs-list-empty">' + esc(e.message) + '</div>';
      scheduleSync(4000);
    });
  }

  boot();
})(jQuery);
/*
 * KELUARGA HEBAT — aplikasi utama (vanilla JS, tanpa framework).
 * Router berbasis hash: #/ (Catat Emosi), #/grafik, #/belajar, #/kartu/<id>, #/akun, ...
 */
(function (KH) {
  'use strict';

  var APP_VERSION = '0.1.0';
  var DEFAULT_CONFIG = {
    checkmark_question: 'Hari ini saya sempat istirahat sebentar',
    whatsapp_group_url: '',
    emergency_numbers: [],
    support_card: null,
    content_base: 'content/'
  };
  var WEATHER = [
    { id: 'cerah', label: 'Cerah' },
    { id: 'berawan', label: 'Berawan' },
    { id: 'gerimis', label: 'Gerimis' },
    { id: 'badai', label: 'Badai' }
  ];
  var WEATHER_LABEL = { cerah: 'Cerah', berawan: 'Berawan', gerimis: 'Gerimis', badai: 'Badai' };
  // Pesan hangat setelah mencatat. Bukan nasihat; hanya ucapan terima kasih.
  var THANKS = {
    cerah: 'Senang mendengarnya. Terima kasih sudah meluangkan waktu untuk dirimu.',
    berawan: 'Terima kasih sudah mencatat. Hari yang campur aduk juga bagian dari perjalanan.',
    gerimis: 'Terima kasih sudah jujur pada dirimu hari ini.',
    badai: 'Terima kasih sudah mencatat. Hari yang berat tetap berarti, dan kamu sudah meluangkan waktu untuk dirimu.'
  };
  var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  var LOCK_AFTER_MS = 5 * 60 * 1000;

  var state = {
    user: null,            // {handle, consent:{status,version,required}}
    config: DEFAULT_CONFIG,
    consentVersion: null,  // versi persetujuan yang berlaku di server
    checkins: new Map(),   // date → record
    hemat: false,
    pin: null,             // {salt, hash, iter}
    locked: false,
    pinForgot: false,
    authExpired: false,
    consentAccepted: null, // versi yang disetujui sebelum mendaftar
    resetToken: null,
    swReg: null,
    renderedDay: null
  };
  KH.state = state;

  var main, nav, akunBtn, netbar, toastEl;

  /* ================= Utilitas ================= */

  /** Buat elemen DOM. Teks selalu lewat textContent; 'svg' hanya untuk ikon statis milik sendiri. */
  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v == null || v === false) return;
        if (k === 'class') el.className = v;
        else if (k === 'svg') el.insertAdjacentHTML('afterbegin', v);
        else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
        else el.setAttribute(k, v === true ? '' : v);
      });
    }
    for (var i = 2; i < arguments.length; i++) append(el, arguments[i]);
    return el;
  }
  function append(el, c) {
    if (c == null || c === false) return;
    if (Array.isArray(c)) { c.forEach(function (x) { append(el, x); }); return; }
    el.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
  }
  /** Ganti isi elemen (aman untuk array & null, tidak seperti replaceChildren bawaan). */
  function fill(el) {
    el.replaceChildren();
    for (var i = 1; i < arguments.length; i++) append(el, arguments[i]);
    return el;
  }
  function add(el) {
    for (var i = 1; i < arguments.length; i++) append(el, arguments[i]);
    return el;
  }
  KH.h = h;
  KH.fill = fill;
  KH.add = add;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parseYmd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function todayStr() { return ymd(new Date()); }
  function addDays(s, n) { var d = parseYmd(s); d.setDate(d.getDate() + n); return ymd(d); }
  function dateLabel(s, withDay) {
    var d = parseYmd(s);
    return (withDay === false ? '' : HARI[d.getDay()] + ', ') + d.getDate() + ' ' + BULAN[d.getMonth()];
  }
  KH.dates = { ymd: ymd, parse: parseYmd, today: todayStr, add: addDays, label: dateLabel, HARI: HARI, BULAN: BULAN };
  KH.WEATHER = WEATHER;
  KH.WEATHER_LABEL = WEATHER_LABEL;

  function toast(msg) {
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { toastEl.classList.remove('show'); }, 4000);
  }
  KH.toast = toast;

  function go(hash) {
    if (location.hash === hash) route(); else location.hash = hash;
  }

  function btn(label, onclick, opts) {
    opts = opts || {};
    return h('button', {
      type: opts.type || 'button',
      class: 'btn ' + (opts.kind || 'primary') + (opts.cls ? ' ' + opts.cls : ''),
      onclick: onclick,
      svg: opts.icon ? KH.icon(opts.icon, 28) : null
    }, h('span', null, label));
  }

  function linkBtn(label, hash, opts) {
    opts = opts || {};
    return h('a', { href: hash, class: 'btn ' + (opts.kind || 'secondary'), svg: opts.icon ? KH.icon(opts.icon, 28) : null }, h('span', null, label));
  }

  /** Kolom isian berlabel dengan petunjuk & pesan galat yang terbaca pembaca layar. */
  function field(id, label, input, hint) {
    var hintId = id + '-hint', errId = id + '-err';
    input.id = id;
    input.setAttribute('aria-describedby', (hint ? hintId + ' ' : '') + errId);
    var wrap = h('div', { class: 'field' },
      h('label', { for: id }, label),
      hint ? h('p', { class: 'hint', id: hintId }, hint) : null,
      input,
      h('p', { class: 'err', id: errId, 'aria-live': 'polite' }));
    return wrap;
  }
  function setFieldError(form, id, msg) {
    var input = form.querySelector('#' + id);
    var err = form.querySelector('#' + id + '-err');
    if (!input || !err) return;
    err.textContent = msg || '';
    if (msg) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
  }
  function passwordInput(autocomplete) {
    var input = h('input', { type: 'password', autocomplete: autocomplete, required: true, minlength: '8', maxlength: '200' });
    var toggle = h('button', { type: 'button', class: 'btn ghost small', 'aria-pressed': 'false' }, 'Tampilkan');
    toggle.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', String(show));
      toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
    });
    return { input: input, row: h('div', { class: 'pw-row' }, input, toggle) };
  }
  function passwordField(id, label, autocomplete, hint) {
    var p = passwordInput(autocomplete);
    var f = field(id, label, p.input, hint);
    f.replaceChild(p.row, p.input);
    p.row.insertBefore(p.input, p.row.firstChild);
    return f;
  }

  function formMessage() { return h('p', { class: 'form-msg', role: 'alert' }); }

  function needOnline(msgEl) {
    if (navigator.onLine) return true;
    msgEl.textContent = 'Butuh internet untuk langkah ini. Coba lagi saat tersambung.';
    return false;
  }

  /** Konfirmasi di tempat (tanpa dialog bawaan peramban). */
  function confirmInline(container, message, yesLabel, onYes) {
    var box = h('div', { class: 'confirm', role: 'group', 'aria-label': 'Konfirmasi' },
      h('p', null, message),
      btn(yesLabel, function () { onYes(); }, { kind: 'warn' }),
      btn('Batal', function () { box.remove(); container.hidden = false; container.querySelector('button').focus(); }, { kind: 'ghost' }));
    container.hidden = true;
    container.parentNode.insertBefore(box, container.nextSibling);
    box.querySelector('p').setAttribute('tabindex', '-1');
    box.querySelector('p').focus();
  }

  /* ================= Penyimpanan lokal ================= */

  function loadCheckins() {
    return KH.db.all('checkins').then(function (rows) {
      state.checkins = new Map(rows.map(function (r) { return [r.date, r]; }));
    });
  }
  function pendingCount() {
    var n = 0;
    state.checkins.forEach(function (r) { if (r.sync === 'pending') n++; });
    return n;
  }
  KH.getCheckins = function () { return state.checkins; };

  function saveUser(user) {
    state.user = user;
    return KH.db.put('kv', user, 'user');
  }

  /** Hapus semua data lokal milik pengguna (keluar / hapus akun / ganti pengguna). */
  function wipeLocal() {
    state.checkins = new Map();
    state.pin = null;
    state.locked = false;
    return Promise.all([
      KH.db.clear('checkins'),
      KH.db.del('kv', 'user'),
      KH.db.del('kv', 'pin'),
      KH.db.del('kv', 'pinFails'),
      KH.db.del('kv', 'supportShown')
    ]);
  }

  /* ================= Konten JSON ================= */

  var contentCache = {};
  /** Muat /content/<nama>.json. content_base bisa diarahkan ke WordPress REST API nanti. */
  function loadContent(name) {
    if (!contentCache[name]) {
      var base = state.config.content_base || 'content/';
      contentCache[name] = fetch(base + name + '.json', { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('content ' + r.status); return r.json(); })
        .catch(function (e) { delete contentCache[name]; throw e; });
    }
    return contentCache[name];
  }
  KH.loadContent = loadContent;

  /* ================= Sinkronisasi ================= */

  function requestSync() {
    if (navigator.onLine) return doSync();
    if (state.swReg && state.swReg.sync) state.swReg.sync.register('kh-sync').catch(function () {});
    return Promise.resolve();
  }

  function doSync() {
    if (!state.user || state.authExpired) return Promise.resolve();
    return KH.sync().then(function (n) {
      if (n) return loadCheckins().then(refreshLive);
    }).catch(function (e) {
      if (e.code === 'auth_required') {
        state.authExpired = true;
        updateNetbar();
      } else if (e.code === 'consent_required') {
        refreshSession().then(route);
      }
    });
  }

  function pullRecent() {
    if (!state.user || state.authExpired || !navigator.onLine) return Promise.resolve();
    return KH.pull(addDays(todayStr(), -60)).then(loadCheckins).then(refreshLive).catch(function () {});
  }

  /** Perbarui bagian layar yang bergantung pada data tanpa merender ulang seluruh layar. */
  function refreshLive() {
    var live = main.querySelectorAll('[data-live]');
    Array.prototype.forEach.call(live, function (el) {
      var fn = LIVE[el.getAttribute('data-live')];
      if (fn) fn(el);
    });
  }
  var LIVE = Object.assign({
    syncstatus: function (el) { fill(el, syncStatusNode()); }
  }, KH.extraLive || {});
  KH.LIVE = LIVE;

  function syncStatusNode() {
    var rec = state.checkins.get(todayStr());
    if (!rec) return h('span');
    if (rec.sync === 'synced') return h('span', { class: 'sync ok', svg: KH.icon('centang', 24) }, 'Sudah terkirim');
    if (rec.sync === 'rejected') return h('span', { class: 'sync', svg: KH.icon('info', 24) }, 'Tersimpan di HP saja');
    return h('span', { class: 'sync wait', svg: KH.icon('awan_kirim', 24) },
      navigator.onLine ? 'Sedang dikirim…' : 'Tersimpan di HP. Akan dikirim saat ada internet.');
  }

  /* ================= Sesi ================= */

  function refreshSession() {
    if (!navigator.onLine) return Promise.resolve();
    return KH.refreshSession().then(function (s) {
      state.config = Object.assign({}, DEFAULT_CONFIG, s.config || {});
      state.consentVersion = s.consent_version;
      KH.db.put('kv', state.config, 'config');
      KH.db.put('kv', s.consent_version, 'consentVersion');
      if (s.user) {
        var switched = state.user && state.user.handle !== s.user.handle;
        state.authExpired = false;
        return (switched ? wipeLocal() : Promise.resolve()).then(function () { return saveUser(s.user); });
      }
      if (state.user) state.authExpired = true; // sesi habis; data lokal tetap aman
    }).catch(function () { /* jaringan lemah: pakai data lokal */ }).then(updateNetbar);
  }

  function afterLogin(user) {
    var switched = state.user && state.user.handle !== user.handle;
    state.authExpired = false;
    var p = switched ? wipeLocal() : Promise.resolve();
    return p.then(function () { return saveUser(user); })
      .then(function () { return KH.refreshSession(); })
      .then(function () { return doSync(); })
      .then(pullRecent)
      .then(updateNetbar);
  }

  /* ================= PIN (opsional, lokal) ================= */
  // PIN 4 angka di-hash PBKDF2-SHA256 + garam acak, hanya di HP ini.
  // Ini kunci layar untuk privasi, bukan enkripsi data.

  function b64(buf) { return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))); }
  function unb64(s) { return Uint8Array.from(atob(s), function (c) { return c.charCodeAt(0); }); }

  function hashPin(pin, salt, iter) {
    var enc = new TextEncoder();
    return crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveBits'])
      .then(function (key) {
        return crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: salt, iterations: iter }, key, 256);
      }).then(b64);
  }
  function setPin(pin) {
    var salt = crypto.getRandomValues(new Uint8Array(16));
    var iter = 100000;
    return hashPin(pin, salt, iter).then(function (hash) {
      state.pin = { salt: b64(salt), hash: hash, iter: iter };
      return KH.db.put('kv', state.pin, 'pin');
    });
  }
  function checkPin(pin) {
    return hashPin(pin, unb64(state.pin.salt), state.pin.iter).then(function (hash) { return hash === state.pin.hash; });
  }

  function screenLock() {
    var msg = formMessage();
    var input = h('input', { type: 'password', inputmode: 'numeric', autocomplete: 'off', maxlength: '4', pattern: '[0-9]*', class: 'pin-input' });
    var form = h('form', { class: 'stack', novalidate: true },
      field('pin-buka', 'Masukkan PIN 4 angka', input),
      btn('Buka', null, { type: 'submit', icon: 'gembok' }), msg);
    function attempt() {
      KH.db.get('kv', 'pinFails').then(function (f) {
        f = f || { n: 0, until: 0 };
        if (Date.now() < f.until) {
          msg.textContent = 'Tunggu ' + Math.ceil((f.until - Date.now()) / 1000) + ' detik, lalu coba lagi.';
          return;
        }
        checkPin(input.value).then(function (ok) {
          if (ok) {
            KH.db.del('kv', 'pinFails');
            state.locked = false;
            route();
            return;
          }
          f.n++;
          if (f.n >= 5) f.until = Date.now() + Math.min(15 * 60, 30 * Math.pow(2, f.n - 5)) * 1000;
          KH.db.put('kv', f, 'pinFails');
          input.value = '';
          msg.textContent = 'PIN belum cocok. Coba lagi.';
          input.focus();
        });
      });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); attempt(); });
    input.addEventListener('input', function () {
      input.value = input.value.replace(/\D/g, '').slice(0, 4);
      if (input.value.length === 4) attempt();
    });
    return {
      title: 'Buka aplikasi', hideNav: true, focus: input,
      node: h('div', { class: 'stack center' },
        h('div', { class: 'lock-ico', svg: KH.icon('gembok', 64) }),
        h('h1', null, 'Aplikasi terkunci'),
        form,
        h('button', { type: 'button', class: 'btn ghost', onclick: function () { state.pinForgot = true; go('#/masuk'); } }, 'Lupa PIN? Masuk dengan email'))
    };
  }

  function screenPin(arg) {
    var offer = arg === 'tawaran';
    var msg = formMessage();
    var p1 = h('input', { type: 'password', inputmode: 'numeric', autocomplete: 'off', maxlength: '4', pattern: '[0-9]*', class: 'pin-input' });
    var p2 = h('input', { type: 'password', inputmode: 'numeric', autocomplete: 'off', maxlength: '4', pattern: '[0-9]*', class: 'pin-input' });
    [p1, p2].forEach(function (i) { i.addEventListener('input', function () { i.value = i.value.replace(/\D/g, '').slice(0, 4); }); });
    var form = h('form', { class: 'stack', novalidate: true },
      field('pin1', 'PIN baru (4 angka)', p1),
      field('pin2', 'Ulangi PIN', p2),
      btn('Simpan PIN', null, { type: 'submit', icon: 'gembok' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!/^\d{4}$/.test(p1.value)) { msg.textContent = 'PIN harus 4 angka.'; p1.focus(); return; }
      if (p1.value !== p2.value) { msg.textContent = 'PIN kedua belum sama. Coba lagi.'; p2.value = ''; p2.focus(); return; }
      setPin(p1.value).then(function () { toast('PIN tersimpan.'); go(offer ? '#/' : '#/akun'); });
    });
    return {
      title: 'PIN', hideNav: offer,
      node: h('div', { class: 'stack' },
        h('h1', null, offer ? 'Mau pakai PIN?' : 'Buat PIN'),
        h('p', null, 'PIN 4 angka menjaga aplikasi saat HP dipinjam orang lain. PIN hanya tersimpan di HP ini.'),
        form,
        offer ? btn('Nanti saja', function () { go('#/'); }, { kind: 'ghost' }) : linkBtn('Batal', '#/akun', { kind: 'ghost' }))
    };
  }

  /* ================= Layar: sambutan & persetujuan ================= */

  function screenWelcome() {
    return {
      title: 'Selamat datang', hideNav: true,
      node: h('div', { class: 'stack center welcome' },
        h('img', { src: 'assets/img/logo.svg', alt: 'Logo Keluarga Hebat', width: '120', height: '120', class: 'logo-big' }),
        h('h1', null, 'Selamat datang di KELUARGA HEBAT'),
        h('p', { class: 'lead' }, 'Portal Refleksi Mandiri dan Sahabat Belajar Keluarga Anak Disabilitas'),
        navigator.onLine ? null : h('p', { class: 'note' }, 'Pertama kali memakai aplikasi ini butuh internet.'),
        linkBtn('Saya pengguna baru', '#/persetujuan', { kind: 'primary', icon: 'hati' }),
        linkBtn('Saya sudah punya akun', '#/masuk', { icon: 'akun' }))
    };
  }

  function screenConsent() {
    var loggedIn = !!state.user;
    var withdrawn = loggedIn && state.user.consent.status === 'withdrawn';
    var body = h('div', { class: 'consent-text' }, h('p', null, 'Memuat…'));
    var msg = formMessage();
    var check = h('input', { type: 'checkbox', id: 'setuju', class: 'big-check' });
    var doc = null;
    loadContent('persetujuan').then(function (c) {
      doc = c;
      fill(body,
        c.status !== 'final' ? h('p', { class: 'badge-draft' }, 'Draf — menunggu persetujuan komite etik') : null,
        h('h2', null, c.title),
        (c.paragraphs || []).map(function (p) { return h('p', null, p); }),
        c.points && c.points.length ? h('ul', null, c.points.map(function (p) { return h('li', null, p); })) : null,
        h('p', { class: 'small' }, 'Versi: ' + c.version));
    }).catch(function () {
      fill(body, h('p', null, 'Teks persetujuan belum bisa dimuat. Sambungkan internet lalu coba lagi.'));
    });

    var form = h('form', { class: 'stack', novalidate: true },
      h('div', { class: 'check-row' }, check, h('label', { for: 'setuju' }, 'Saya sudah membaca dan setuju ikut serta.')),
      btn(loggedIn ? 'Saya setuju' : 'Lanjut ke pendaftaran', null, { type: 'submit', icon: 'centang' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!doc) { msg.textContent = 'Tunggu teks persetujuan dimuat.'; return; }
      if (!check.checked) { msg.textContent = 'Centang kotak persetujuan dulu.'; check.focus(); return; }
      if (!loggedIn) {
        state.consentAccepted = doc.version;
        go('#/daftar');
        return;
      }
      if (!needOnline(msg)) return;
      KH.api('consent', { action: 'give', version: doc.version }).then(function (d) {
        return saveUser(d.user);
      }).then(function () { toast('Terima kasih. Persetujuan tersimpan.'); go('#/'); })
        .catch(function (err) { msg.textContent = err.message; });
    });

    return {
      title: 'Persetujuan', hideNav: true,
      node: h('div', { class: 'stack' },
        h('h1', null, 'Persetujuan ikut serta'),
        withdrawn ? h('div', { class: 'notice' }, 'Kamu sudah menarik persetujuan. Catatanmu tidak dipakai untuk penelitian. Untuk memakai aplikasi lagi, berikan persetujuan di bawah. Kamu juga bisa menghapus akun.') : null,
        loggedIn && !withdrawn ? h('div', { class: 'notice' }, 'Teks persetujuan sudah diperbarui. Mohon baca dan setujui lagi.') : null,
        body, form,
        loggedIn ? h('div', { class: 'stack' },
          linkBtn('Hapus akun dan semua data saya', '#/hapus-akun', { kind: 'warn', icon: 'hapus' }),
          btn('Keluar', logout, { kind: 'ghost', icon: 'keluar' }))
          : linkBtn('Kembali', '#/selamat-datang', { kind: 'ghost', icon: 'kembali' }))
    };
  }

  /* ================= Layar: daftar, masuk, lupa, reset ================= */

  function screenRegister() {
    if (!state.consentAccepted) { setTimeout(function () { go('#/persetujuan'); }); return screenWelcome(); }
    var msg = formMessage();
    var handle = h('input', { type: 'text', autocomplete: 'nickname', required: true, maxlength: '24', autocapitalize: 'words' });
    var email = h('input', { type: 'email', autocomplete: 'email', required: true, inputmode: 'email', maxlength: '254' });
    var form = h('form', { class: 'stack', novalidate: true },
      h('div', { class: 'warning', role: 'note' },
        h('strong', null, 'Jangan pakai nama asli.'),
        ' Pakai nama samaran, misalnya "Bunda Melati". Hanya nama samaran yang terlihat oleh peneliti.'),
      field('handle', 'Nama samaran', handle, '3 sampai 24 huruf atau angka.'),
      field('email', 'Email', email, 'Hanya untuk masuk dan membuat kata sandi baru. Peneliti tidak bisa melihat email.'),
      passwordField('password', 'Kata sandi', 'new-password', 'Minimal 8 huruf atau angka.'),
      btn('Daftar', null, { type: 'submit', icon: 'centang' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      ['handle', 'email', 'password'].forEach(function (id) { setFieldError(form, id, ''); });
      msg.textContent = '';
      if (!needOnline(msg)) return;
      var pw = form.querySelector('#password');
      var submit = form.querySelector('button[type=submit]');
      submit.disabled = true;
      KH.api('register', {
        handle: handle.value, email: email.value, password: pw.value,
        consent: true, consent_version: state.consentAccepted
      }).then(function (d) {
        return afterLogin(d.user).then(function () { go('#/pin/tawaran'); });
      }).catch(function (err) {
        submit.disabled = false;
        var fields = (err.data && err.data.fields) || {};
        Object.keys(fields).forEach(function (k) { setFieldError(form, k === 'consent' ? 'handle' : k, fields[k]); });
        msg.textContent = err.code === 'consent_version' || fields.consent
          ? 'Teks persetujuan diperbarui. Muat ulang aplikasi.' : err.message;
        var first = form.querySelector('[aria-invalid=true]');
        if (first) first.focus();
      });
    });
    return {
      title: 'Daftar', hideNav: true,
      node: h('div', { class: 'stack' }, h('h1', null, 'Buat akun'), form,
        linkBtn('Sudah punya akun? Masuk', '#/masuk', { kind: 'ghost' }))
    };
  }

  function screenLogin() {
    var msg = formMessage();
    var email = h('input', { type: 'email', autocomplete: 'email', required: true, inputmode: 'email' });
    var form = h('form', { class: 'stack', novalidate: true },
      state.authExpired ? h('div', { class: 'notice' }, 'Silakan masuk lagi. Catatanmu tetap aman di HP dan akan dikirim setelah masuk.') : null,
      state.pinForgot ? h('div', { class: 'notice' }, 'Masuk dengan email dan kata sandi untuk menghapus PIN lama.') : null,
      field('email', 'Email', email),
      passwordField('password', 'Kata sandi', 'current-password'),
      btn('Masuk', null, { type: 'submit', icon: 'akun' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      msg.textContent = '';
      if (!needOnline(msg)) return;
      var submit = form.querySelector('button[type=submit]');
      submit.disabled = true;
      KH.api('login', { email: email.value, password: form.querySelector('#password').value }).then(function (d) {
        var clearPin = state.pinForgot;
        state.pinForgot = false;
        state.locked = false;
        return (clearPin ? KH.db.del('kv', 'pin').then(function () { state.pin = null; }) : Promise.resolve())
          .then(function () { return afterLogin(d.user); })
          .then(function () { go('#/'); });
      }).catch(function (err) { submit.disabled = false; msg.textContent = err.message; });
    });
    return {
      title: 'Masuk', hideNav: true,
      node: h('div', { class: 'stack' }, h('h1', null, 'Masuk'), form,
        linkBtn('Lupa kata sandi?', '#/lupa', { kind: 'ghost' }),
        state.user ? null : linkBtn('Belum punya akun? Daftar', '#/persetujuan', { kind: 'ghost' }))
    };
  }

  function screenForgot() {
    var msg = formMessage();
    var email = h('input', { type: 'email', autocomplete: 'email', required: true, inputmode: 'email' });
    var form = h('form', { class: 'stack', novalidate: true },
      h('p', null, 'Tulis email akunmu. Kami kirim tautan untuk membuat kata sandi baru.'),
      field('email', 'Email', email),
      btn('Kirim tautan', null, { type: 'submit' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!needOnline(msg)) return;
      KH.api('password-forgot', { email: email.value })
        .then(function (d) { msg.textContent = d.message; })
        .catch(function (err) { msg.textContent = err.message; });
    });
    return {
      title: 'Lupa kata sandi', hideNav: true,
      node: h('div', { class: 'stack' }, h('h1', null, 'Lupa kata sandi'), form,
        linkBtn('Kembali ke Masuk', '#/masuk', { kind: 'ghost', icon: 'kembali' }))
    };
  }

  function screenReset() {
    var msg = formMessage();
    var form = h('form', { class: 'stack', novalidate: true },
      passwordField('password', 'Kata sandi baru', 'new-password', 'Minimal 8 huruf atau angka.'),
      btn('Simpan kata sandi', null, { type: 'submit', icon: 'centang' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!state.resetToken) { msg.textContent = 'Tautan tidak berlaku. Minta tautan baru.'; return; }
      if (!needOnline(msg)) return;
      KH.api('password-reset', { token: state.resetToken, password: form.querySelector('#password').value })
        .then(function (d) {
          state.resetToken = null;
          return afterLogin(d.user).then(function () { toast('Kata sandi baru tersimpan.'); go('#/'); });
        })
        .catch(function (err) { msg.textContent = err.message; });
    });
    return {
      title: 'Kata sandi baru', hideNav: true,
      node: h('div', { class: 'stack' }, h('h1', null, 'Buat kata sandi baru'), form)
    };
  }

  /* ================= Layar: Catat Emosi (beranda) ================= */

  function screenHome() {
    state.renderedDay = todayStr();
    var node = h('div', { class: 'stack' },
      h('h1', { class: 'greet' }, 'Halo, ' + state.user.handle),
      checkinCard());
    var support = supportCard();
    if (support) node.appendChild(support);
    (KH.homeSections || []).forEach(function (fn) { node.appendChild(fn()); });
    return { title: 'Catat Emosi', nav: 'catat', node: node };
  }

  function checkinCard() {
    var box = h('section', { class: 'card checkin', 'aria-labelledby': 'ci-title' });
    var rec = state.checkins.get(todayStr());
    var step = rec ? 'done' : 'weather';
    var draft = null;

    function draw(focus) {
      fill(box);
      rec = state.checkins.get(todayStr());
      if (step === 'weather') {
        add(box, 
          h('h2', { id: 'ci-title', tabindex: '-1' }, 'Bagaimana cuaca hatimu hari ini?'),
          h('div', { class: 'weather-grid', role: 'group', 'aria-labelledby': 'ci-title' },
            WEATHER.map(function (w) {
              return h('button', {
                type: 'button', class: 'weather-btn w-' + w.id,
                'aria-pressed': rec && rec.weather === w.id ? 'true' : 'false',
                svg: KH.icon(w.id, 64),
                onclick: function () { draft = w.id; step = 'rest'; draw(true); }
              }, h('span', null, w.label));
            })),
          rec ? btn('Batal ubah', function () { step = 'done'; draw(true); }, { kind: 'ghost' }) : null);
      } else if (step === 'rest') {
        add(box, 
          h('p', { class: 'chosen', svg: KH.icon(draft, 36) }, 'Cuaca hati: ' + WEATHER_LABEL[draft]),
          h('h2', { id: 'ci-title', tabindex: '-1' }, state.config.checkmark_question),
          h('div', { class: 'yesno' },
            btn('Ya', function () { save(1); }, { icon: 'centang', cls: 'big' }),
            btn('Belum', function () { save(0); }, { kind: 'secondary', icon: 'belum', cls: 'big' })),
          btn('Kembali', function () { step = 'weather'; draw(true); }, { kind: 'ghost', icon: 'kembali' }));
      } else {
        add(box, 
          h('div', { class: 'done-ico', svg: KH.icon(rec.weather, 72) }),
          h('h2', { id: 'ci-title', tabindex: '-1' }, 'Terima kasih sudah mencatat'),
          h('p', { class: 'thanks' }, THANKS[rec.weather]),
          h('ul', { class: 'summary-list' },
            h('li', null, 'Cuaca hati: ' + WEATHER_LABEL[rec.weather]),
            h('li', null, state.config.checkmark_question + ': ' + (rec.rested ? 'Ya' : 'Belum'))),
          h('p', { 'data-live': 'syncstatus', 'aria-live': 'polite' }, syncStatusNode()),
          btn('Ubah catatan hari ini', function () { step = 'weather'; draw(true); }, { kind: 'secondary' }),
          h('p', { class: 'small' }, 'Catatan bisa diubah sampai tengah malam.'));
      }
      if (focus) box.querySelector('h2').focus();
    }

    function save(rested) {
      var date = todayStr();
      var prev = state.checkins.get(date);
      var record = {
        date: date,
        uuid: prev ? prev.uuid : KH.uuid(),
        weather: draft,
        rested: rested,
        updated_at: Math.max(Date.now(), prev ? prev.updated_at + 1 : 0),
        sync: 'pending'
      };
      KH.db.put('checkins', record).then(function () {
        state.checkins.set(date, record);
        step = 'done';
        draw(true);
        refreshSiblings();
        requestSync();
      }).catch(function () { toast('Maaf, catatan belum tersimpan. Coba lagi.'); });
    }

    draw(false);
    return box;
  }

  /** Setelah menyimpan: perbarui pohon & kartu dukungan di layar yang sama. */
  function refreshSiblings() {
    var old = main.querySelector('.support');
    var fresh = supportCard();
    if (old && !fresh) old.remove();
    if (!old && fresh) main.querySelector('.checkin').after(fresh);
    refreshLive();
  }

  /** 3 catatan terakhir semuanya "badai" → kartu dukungan lembut (teks dari config). */
  function supportCard() {
    var cfg = state.config.support_card;
    if (!cfg) return null;
    var dates = Array.from(state.checkins.keys()).sort().reverse().slice(0, 3);
    if (dates.length < 3 || !dates.every(function (d) { return state.checkins.get(d).weather === 'badai'; })) return null;

    var today = todayStr();
    KH.db.get('kv', 'supportShown').then(function (d) {
      if (d !== today && navigator.onLine && !state.authExpired) {
        KH.api('support-event', { kind: 'card_shown' }).then(function () { KH.db.put('kv', today, 'supportShown'); }).catch(function () {});
      }
    });
    return h('section', { class: 'card support', 'aria-labelledby': 'sup-title' },
      h('div', { class: 'support-ico', svg: KH.icon('hati', 48) }),
      h('h2', { id: 'sup-title' }, cfg.title),
      h('p', null, cfg.text),
      cfg.contact_url ? h('a', {
        class: 'btn primary', href: cfg.contact_url, target: '_blank', rel: 'noopener noreferrer',
        svg: KH.icon('telepon', 28),
        onclick: function () { if (navigator.onLine) KH.api('support-event', { kind: 'contact_tapped' }).catch(function () {}); }
      }, h('span', null, cfg.contact_label || 'Hubungi')) : null);
  }

  /* ================= Layar: Akun ================= */

  function screenAkun() {
    var pending = pendingCount();
    var syncMsg = h('p', { 'aria-live': 'polite' },
      pending ? pending + ' catatan menunggu dikirim.' : 'Semua catatan sudah terkirim.');

    var hematSwitch = h('button', {
      type: 'button', role: 'switch', class: 'switch', 'aria-checked': String(state.hemat),
      'aria-labelledby': 'hemat-label', 'aria-describedby': 'hemat-desc'
    }, h('span', { class: 'switch-knob' }), h('span', { class: 'switch-text' }, state.hemat ? 'Nyala' : 'Mati'));
    hematSwitch.addEventListener('click', function () {
      state.hemat = !state.hemat;
      KH.db.put('kv', state.hemat, 'hemat');
      document.documentElement.classList.toggle('hemat', state.hemat);
      hematSwitch.setAttribute('aria-checked', String(state.hemat));
      hematSwitch.querySelector('.switch-text').textContent = state.hemat ? 'Nyala' : 'Mati';
    });

    var pinArea = state.pin
      ? h('div', { class: 'stack' }, h('p', null, 'PIN aktif di HP ini.'),
          linkBtn('Ganti PIN', '#/pin', { icon: 'gembok' }),
          btn('Hapus PIN', function () {
            KH.db.del('kv', 'pin').then(function () { state.pin = null; toast('PIN dihapus.'); route(); });
          }, { kind: 'ghost' }))
      : h('div', { class: 'stack' }, h('p', null, 'Belum ada PIN.'), linkBtn('Buat PIN 4 angka', '#/pin', { icon: 'gembok' }));

    var logoutWrap = h('div', null, btn('Keluar', function () {
      if (pendingCount() > 0) {
        confirmInline(logoutWrap, 'Ada catatan yang belum terkirim. Jika keluar sekarang, catatan itu hilang. Sambungkan internet dulu agar terkirim.', 'Tetap keluar', logout);
      } else {
        logout();
      }
    }, { kind: 'secondary', icon: 'keluar' }));

    return {
      title: 'Akun', nav: null,
      node: h('div', { class: 'stack' },
        h('h1', null, 'Akun'),
        h('section', { class: 'card' }, h('h2', null, 'Nama samaran'), h('p', { class: 'big-text' }, state.user.handle)),
        h('section', { class: 'card' },
          h('h2', { id: 'hemat-label' }, 'Mode hemat data'),
          h('p', { id: 'hemat-desc' }, 'Gambar dan suara hanya dimuat saat kamu ketuk.'),
          hematSwitch),
        h('section', { class: 'card' }, h('h2', null, 'PIN pembuka aplikasi'), pinArea),
        h('section', { class: 'card' }, h('h2', null, 'Pengiriman catatan'), syncMsg,
          pending ? btn('Kirim sekarang', function () {
            if (!navigator.onLine) { syncMsg.textContent = 'Belum ada internet. Catatan tetap aman di HP.'; return; }
            doSync().then(function () {
              var n = pendingCount();
              syncMsg.textContent = n ? n + ' catatan menunggu dikirim.' : 'Semua catatan sudah terkirim.';
            });
          }, { icon: 'awan_kirim' }) : null),
        h('section', { class: 'card' },
          h('h2', null, 'Persetujuan penelitian'),
          h('p', null, 'Versi yang kamu setujui: ' + state.user.consent.version),
          linkBtn('Tarik persetujuan', '#/tarik-persetujuan', { kind: 'secondary' })),
        logoutWrap,
        linkBtn('Hapus akun dan semua data saya', '#/hapus-akun', { kind: 'warn', icon: 'hapus' }),
        h('p', { class: 'small center' }, 'Keluarga Hebat versi ' + APP_VERSION))
    };
  }

  function logout() {
    var p = navigator.onLine ? KH.api('logout', {}).catch(function () {}) : Promise.resolve();
    p.then(wipeLocal).then(function () {
      state.user = null;
      state.authExpired = false;
      toast('Kamu sudah keluar.');
      go('#/selamat-datang');
    });
  }

  function screenWithdraw() {
    var msg = formMessage();
    return {
      title: 'Tarik persetujuan',
      node: h('div', { class: 'stack' },
        h('h1', null, 'Tarik persetujuan'),
        h('p', null, 'Jika kamu menarik persetujuan:'),
        h('ul', null,
          h('li', null, 'Catatanmu tidak lagi dipakai untuk penelitian.'),
          h('li', null, 'Aplikasi tidak bisa dipakai sampai kamu memberi persetujuan lagi.'),
          h('li', null, 'Datamu tetap tersimpan sampai kamu memilih "Hapus akun dan semua data saya".')),
        btn('Ya, tarik persetujuan', function () {
          if (!needOnline(msg)) return;
          KH.api('consent', { action: 'withdraw' })
            .then(function (d) { return saveUser(d.user); })
            .then(function () { toast('Persetujuan sudah ditarik.'); go('#/persetujuan'); })
            .catch(function (err) { msg.textContent = err.message; });
        }, { kind: 'warn' }),
        linkBtn('Batal', '#/akun', { kind: 'ghost', icon: 'kembali' }),
        msg)
    };
  }

  function screenDelete() {
    var msg = formMessage();
    var sure = h('input', { type: 'checkbox', id: 'yakin', class: 'big-check' });
    var form = h('form', { class: 'stack', novalidate: true },
      passwordField('password', 'Kata sandi', 'current-password', 'Untuk memastikan ini benar kamu.'),
      h('div', { class: 'check-row' }, sure, h('label', { for: 'yakin' }, 'Saya mengerti akun dan semua catatan akan dihapus selamanya.')),
      btn('Hapus akun saya selamanya', null, { type: 'submit', kind: 'warn', icon: 'hapus' }), msg);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!sure.checked) { msg.textContent = 'Centang kotak di atas dulu.'; sure.focus(); return; }
      if (!needOnline(msg)) return;
      KH.api('account-delete', { password: form.querySelector('#password').value })
        .then(wipeLocal)
        .then(function () {
          state.user = null;
          toast('Akun dan semua datamu sudah dihapus.');
          go('#/selamat-datang');
        })
        .catch(function (err) { msg.textContent = err.message; });
    });
    return {
      title: 'Hapus akun', hideNav: state.user && state.user.consent.required,
      node: h('div', { class: 'stack' },
        h('h1', null, 'Hapus akun dan semua data saya'),
        h('p', null, 'Akun, nama samaran, semua catatan cuaca hati, dan riwayat persetujuan akan dihapus dari server dan dari HP ini. Ini tidak bisa dibatalkan.'),
        form,
        linkBtn('Batal', state.user && state.user.consent.required ? '#/persetujuan' : '#/akun', { kind: 'ghost', icon: 'kembali' }))
    };
  }

  /* ================= Router ================= */

  var SCREENS = {
    '': screenHome,
    'selamat-datang': screenWelcome,
    'persetujuan': screenConsent,
    'daftar': screenRegister,
    'masuk': screenLogin,
    'lupa': screenForgot,
    'reset': screenReset,
    'pin': screenPin,
    'akun': screenAkun,
    'tarik-persetujuan': screenWithdraw,
    'hapus-akun': screenDelete
  };
  Object.assign(SCREENS, KH.extraScreens || {}); // layar dari charts.js & content.js

  var PUBLIC = ['selamat-datang', 'persetujuan', 'daftar', 'masuk', 'lupa', 'reset'];
  var NEEDS_CONSENT_OK = ['persetujuan', 'hapus-akun', 'masuk'];
  var firstRender = true;

  function route() {
    var parts = location.hash.replace(/^#\/?/, '').split('/');
    var name = parts[0] || '';
    var arg = parts[1] ? decodeURIComponent(parts[1]) : null;
    var redirect = null;

    if (state.locked && !(state.pinForgot && (name === 'masuk' || name === 'lupa'))) {
      return render(screenLock());
    }
    if (!state.user) {
      if (PUBLIC.indexOf(name) < 0) redirect = '#/selamat-datang';
    } else if (state.user.consent.required) {
      if (NEEDS_CONSENT_OK.indexOf(name) < 0) redirect = '#/persetujuan';
    } else if (['selamat-datang', 'daftar', 'persetujuan'].indexOf(name) >= 0 ||
               (name === 'masuk' && !state.authExpired && !state.pinForgot)) {
      redirect = '#/';
    }
    if (redirect) { history.replaceState(null, '', redirect); return route(); }

    render((SCREENS[name] || screenHome)(arg));
  }
  KH.route = route;

  function render(scr) {
    document.title = scr.title + ' · Keluarga Hebat';
    fill(main, scr.node);
    var showNav = !!state.user && !state.user.consent.required && !scr.hideNav && !state.locked;
    nav.hidden = !showNav;
    akunBtn.hidden = !showNav;
    document.body.classList.toggle('has-nav', showNav);
    Array.prototype.forEach.call(nav.querySelectorAll('a'), function (a) {
      if (a.getAttribute('data-nav') === scr.nav) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    window.scrollTo(0, 0);
    // Pindahkan fokus ke judul agar TalkBack membacakan layar baru.
    var target = scr.focus || main.querySelector('h1');
    if (target) {
      if (!scr.focus) target.setAttribute('tabindex', '-1');
      if (!firstRender || scr.focus) target.focus();
    }
    firstRender = false;
  }

  /* ================= Status jaringan ================= */

  function updateNetbar() {
    var msg = null;
    if (!navigator.onLine) msg = 'Tidak ada internet. Catatan tetap tersimpan di HP.';
    else if (state.authExpired && state.user) msg = 'Sesi berakhir. Ketuk di sini untuk masuk lagi.';
    netbar.hidden = !msg;
    if (!msg) return;
    fill(netbar, 
      state.authExpired && navigator.onLine
        ? h('a', { href: '#/masuk', svg: KH.icon('akun', 24) }, msg)
        : h('span', { svg: KH.icon('offline', 24) }, msg));
  }

  /* ================= Mulai ================= */

  function boot() {
    main = document.getElementById('main');
    nav = document.getElementById('bottomnav');
    akunBtn = document.getElementById('btn-akun');
    netbar = document.getElementById('netbar');
    toastEl = document.getElementById('toast');

    // Ikon navigasi disisipkan dari satu sumber (icons.js).
    Array.prototype.forEach.call(document.querySelectorAll('[data-icon]'), function (el) {
      el.insertAdjacentHTML('afterbegin', KH.icon(el.getAttribute('data-icon'), 30));
    });

    // Tautan reset dari email: ?reset=TOKEN → simpan di memori, hapus dari URL.
    var params = new URLSearchParams(location.search);
    if (params.get('reset')) {
      state.resetToken = params.get('reset');
      history.replaceState(null, '', location.pathname + '#/reset');
    }

    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js').then(function (reg) { state.swReg = reg; }).catch(function () {});
      navigator.serviceWorker.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'synced') loadCheckins().then(refreshLive);
      });
    }

    Promise.all(['user', 'config', 'hemat', 'pin', 'consentVersion'].map(function (k) { return KH.db.get('kv', k); }))
      .then(function (v) {
        state.user = v[0] || null;
        state.config = Object.assign({}, DEFAULT_CONFIG, v[1] || {});
        var saveData = navigator.connection && navigator.connection.saveData;
        state.hemat = v[2] == null ? !!saveData : !!v[2];
        state.pin = v[3] || null;
        state.consentVersion = v[4] || null;
        state.locked = !!(state.user && state.pin);
        document.documentElement.classList.toggle('hemat', state.hemat);
        return loadCheckins();
      })
      .catch(function () { /* IndexedDB tidak tersedia (mode privat lama) */ })
      .then(function () {
        if (state.resetToken && !location.hash) location.hash = '#/reset';
        route();
        updateNetbar();
        return refreshSession();
      })
      .then(function () {
        route();
        doSync();
        pullRecent();
      });

    window.addEventListener('hashchange', route);
    window.addEventListener('online', function () { updateNetbar(); refreshSession().then(doSync).then(pullRecent); refreshLive(); });
    window.addEventListener('offline', function () { updateNetbar(); refreshLive(); });

    // Kunci lagi setelah 5 menit di latar belakang; muat ulang beranda saat ganti hari.
    var hiddenAt = 0;
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { hiddenAt = Date.now(); return; }
      if (state.pin && state.user && Date.now() - hiddenAt > LOCK_AFTER_MS && hiddenAt) { state.locked = true; route(); return; }
      if (state.renderedDay && state.renderedDay !== todayStr() && (location.hash === '' || location.hash === '#/')) route();
      doSync();
    });
    setInterval(function () {
      if (state.renderedDay && state.renderedDay !== todayStr() && (location.hash === '' || location.hash === '#/') &&
          !main.querySelector('.checkin [aria-pressed], .yesno')) route();
    }, 60000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})(self.KH);

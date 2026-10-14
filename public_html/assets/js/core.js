/*
 * KELUARGA HEBAT — inti bersama (halaman + service worker).
 * IndexedDB, panggilan API, dan sinkronisasi check-in yang idempoten.
 * Dimuat dengan <script> di halaman dan importScripts() di sw.js.
 */
(function (g) {
  'use strict';
  var KH = g.KH || (g.KH = {});

  var base = g.registration ? g.registration.scope : g.location.href;
  KH.API = new URL('api/index.php', base).href;

  /* ---------------- IndexedDB ---------------- */
  // Store "checkins": keyPath "date" (satu catatan per hari).
  //   {date, uuid, weather, rested, updated_at, sync: 'pending'|'synced'|'rejected'}
  // Store "kv": pengaturan lokal (user, csrf, config, pin, hemat, ...).
  var dbPromise = null;
  function openDb() {
    if (!dbPromise) {
      dbPromise = new Promise(function (resolve, reject) {
        var req = indexedDB.open('keluarga-hebat', 1);
        req.onupgradeneeded = function () {
          var db = req.result;
          if (!db.objectStoreNames.contains('checkins')) db.createObjectStore('checkins', { keyPath: 'date' });
          if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv');
        };
        req.onsuccess = function () { resolve(req.result); };
        req.onerror = function () { reject(req.error); };
      });
    }
    return dbPromise;
  }

  function tx(store, mode, fn) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var t = db.transaction(store, mode);
        var result;
        var req = fn(t.objectStore(store), function (v) { result = v; });
        if (req) req.onsuccess = function () { result = req.result; };
        t.oncomplete = function () { resolve(result); };
        t.onerror = t.onabort = function () { reject(t.error); };
      });
    });
  }

  KH.db = {
    get: function (store, key) { return tx(store, 'readonly', function (s) { return s.get(key); }); },
    all: function (store) { return tx(store, 'readonly', function (s) { return s.getAll(); }); },
    put: function (store, value, key) {
      return tx(store, 'readwrite', function (s) { return key === undefined ? s.put(value) : s.put(value, key); });
    },
    del: function (store, key) { return tx(store, 'readwrite', function (s) { return s.delete(key); }); },
    clear: function (store) { return tx(store, 'readwrite', function (s) { return s.clear(); }); },
    // Baca-ubah-tulis dalam satu transaksi. fn(nilaiLama) → nilai baru, atau undefined untuk batal.
    update: function (store, key, fn) {
      return tx(store, 'readwrite', function (s, setResult) {
        var req = s.get(key);
        req.onsuccess = function () {
          var next = fn(req.result);
          if (next !== undefined) { s.put(next); setResult(next); } else { setResult(req.result); }
        };
      });
    }
  };

  /* ---------------- UUID v4 ---------------- */
  KH.uuid = function () {
    if (g.crypto && g.crypto.randomUUID) return g.crypto.randomUUID();
    var b = g.crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    var hex = Array.prototype.map.call(b, function (x) { return (x + 0x100).toString(16).slice(1); }).join('');
    return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
  };

  /* ---------------- API ---------------- */
  KH.refreshSession = function () {
    return fetch(KH.API + '?r=session', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { if (!r.ok) throw new Error('session ' + r.status); return r.json(); })
      .then(function (d) { return KH.db.put('kv', d.csrf, 'csrf').then(function () { return d; }); });
  };

  /**
   * KH.api('login', {email, password}) → POST; KH.api('checkins&since=2026-01-01') → GET.
   * Melempar Error dengan .code (mis. 'auth_required') dan .status.
   */
  KH.api = function (route, body) {
    var isPost = body !== undefined;
    function send(csrf) {
      var opts = { method: isPost ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', headers: {} };
      if (isPost) {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-Token'] = csrf || '';
        opts.body = JSON.stringify(body);
      }
      return fetch(KH.API + '?r=' + route, opts).then(function (res) {
        return res.json().catch(function () { return { ok: false, error: 'bad_response' }; })
          .then(function (data) { return { res: res, data: data }; });
      });
    }
    return KH.db.get('kv', 'csrf').then(function (csrf) {
      return send(csrf).then(function (r) {
        // Token CSRF kedaluwarsa/hilang → ambil baru, ulangi sekali.
        if (isPost && r.res.status === 403 && r.data.error === 'csrf') {
          return KH.refreshSession().then(function (s) { return send(s.csrf); });
        }
        return r;
      });
    }).then(function (r) {
      if (!r.res.ok || r.data.ok === false) {
        var err = new Error(r.data.message || 'Gagal (' + r.res.status + ')');
        err.code = r.data.error || 'http_' + r.res.status;
        err.status = r.res.status;
        err.data = r.data;
        throw err;
      }
      return r.data;
    });
  };

  /* ---------------- Sinkronisasi ---------------- */
  var inflight = null;

  /** Kirim semua check-in "pending". Aman dipanggil berkali-kali (idempoten berkat uuid). */
  KH.sync = function () {
    if (!inflight) {
      inflight = runSync().then(function (n) { inflight = null; return n; },
                               function (e) { inflight = null; throw e; });
    }
    return inflight;
  };

  function runSync() {
    return KH.db.all('checkins').then(function (all) {
      var pending = all.filter(function (r) { return r.sync === 'pending'; });
      if (!pending.length) return 0;
      var batch = pending.slice(0, 60);
      return KH.api('checkins-sync', {
        items: batch.map(function (r) {
          return { uuid: r.uuid, date: r.date, weather: r.weather, rested: r.rested, updated_at: r.updated_at };
        })
      }).then(function (data) {
        var sentAt = {};
        batch.forEach(function (r) { sentAt[r.date] = r.updated_at; });
        return Promise.all(data.results.map(function (res) {
          if (!res.date || !(res.date in sentAt)) return null;
          return KH.db.update('checkins', res.date, function (local) {
            // Diubah lagi selagi terkirim → biarkan pending untuk putaran berikutnya.
            if (!local || local.updated_at !== sentAt[res.date]) return undefined;
            if (res.status === 'rejected') {
              local.sync = 'rejected';
              local.reason = res.reason;
              return local;
            }
            var rec = res.record;
            return { date: rec.date, uuid: rec.uuid, weather: rec.weather, rested: rec.rested, updated_at: rec.updated_at, sync: 'synced' };
          });
        })).then(function () {
          return pending.length > batch.length ? runSync().then(function (n) { return n + batch.length; }) : batch.length;
        });
      });
    });
  }

  /** Ambil catatan dari server (mis. HP baru) dan gabungkan tanpa menimpa yang belum terkirim. */
  KH.pull = function (since) {
    return KH.api('checkins&since=' + encodeURIComponent(since)).then(function (data) {
      return Promise.all(data.items.map(function (item) {
        return KH.db.update('checkins', item.date, function (local) {
          if (local && local.sync === 'pending') return undefined;
          if (local && local.updated_at >= item.updated_at && local.sync === 'synced') return undefined;
          return { date: item.date, uuid: item.uuid, weather: item.weather, rested: item.rested, updated_at: item.updated_at, sync: 'synced' };
        });
      })).then(function () { return data.items.length; });
    });
  };
})(self);

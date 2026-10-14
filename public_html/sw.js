/*
 * Service worker KELUARGA HEBAT.
 *  - App shell: cache-first (versi di CACHE). Ubah VERSION setiap rilis.
 *  - Konten JSON: stale-while-revalidate (tetap bisa dibaca offline).
 *  - Audio/gambar konten: hanya disimpan setelah diminta (tidak diunduh di muka).
 *  - API: selalu jaringan, tidak pernah di-cache.
 *  - Background Sync: kirim check-in tertunda saat sinyal kembali.
 */
importScripts('assets/js/core.js');

var VERSION = 'kh-2026-10-14-3';
var SHELL = 'shell-' + VERSION;
var CONTENT = 'content-v1';
var MEDIA = 'media-v1';

var SHELL_FILES = [
  './',
  'index.html',
  'manifest.webmanifest',
  'assets/css/app.css',
  'assets/js/core.js',
  'assets/js/icons.js',
  'assets/js/charts.js',
  'assets/js/content.js',
  'assets/js/app.js',
  'assets/img/logo.svg',
  'assets/img/icon-192.png',
  'content/persetujuan.json',
  'content/pertolongan.json',
  'content/belajar.json'
];

self.addEventListener('install', function (event) {
  event.waitUntil(caches.open(SHELL).then(function (c) { return c.addAll(SHELL_FILES); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener('activate', function (event) {
  event.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k.indexOf('shell-') === 0 && k !== SHELL; })
      .map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  var path = url.pathname.slice(new URL(self.registration.scope).pathname.length);

  // Jangan sentuh API, dashboard peneliti, dan halaman PHP lain (mis. setup.php).
  if (path.indexOf('api/') === 0 || path.indexOf('peneliti/') === 0 || /\.php$/.test(path)) return;

  if (req.mode === 'navigate') {
    event.respondWith(caches.match('index.html', { cacheName: SHELL }).then(function (hit) {
      return hit || fetch(req);
    }));
    return;
  }

  if (path.indexOf('content/') === 0 && path.slice(-5) === '.json') {
    event.respondWith(staleWhileRevalidate(req, CONTENT));
    return;
  }

  if (path.indexOf('content/') === 0) {
    // Audio & gambar: ambil saat diminta, simpan untuk pemutaran offline berikutnya.
    // Permintaan Range (audio) dilewatkan langsung ke jaringan.
    if (req.headers.has('range')) return;
    event.respondWith(caches.open(MEDIA).then(function (c) {
      return c.match(req).then(function (hit) {
        return hit || fetch(req).then(function (res) {
          if (res.ok && res.status === 200) c.put(req, res.clone());
          return res;
        });
      });
    }));
    return;
  }

  event.respondWith(caches.match(req, { ignoreSearch: true }).then(function (hit) { return hit || fetch(req); }));
});

function staleWhileRevalidate(req, cacheName) {
  return caches.open(cacheName).then(function (c) {
    return c.match(req).then(function (hit) {
      var net = fetch(req).then(function (res) {
        if (res.ok) c.put(req, res.clone());
        return res;
      });
      if (hit) { net.catch(function () {}); return hit; }
      return net.catch(function () { return caches.match(req); });
    });
  });
}

function syncAndNotify() {
  return self.KH.sync().then(function (n) {
    if (!n) return;
    return self.clients.matchAll().then(function (list) {
      list.forEach(function (c) { c.postMessage({ type: 'synced', count: n }); });
    });
  });
}

self.addEventListener('sync', function (event) {
  if (event.tag === 'kh-sync') event.waitUntil(syncAndNotify());
});

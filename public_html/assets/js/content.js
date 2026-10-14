/*
 * Tahap 4: "Pertolongan Pertama Pengasuhan" (4 kartu) dan "Ruang Belajar".
 * Semua isi dibaca dari /content/*.json (nanti bisa dari WordPress REST API, lihat README).
 * Isi yang belum ditinjau tenaga profesional TIDAK ditampilkan; yang tampil hanya placeholder.
 */
(function (KH) {
  'use strict';

  var PLACEHOLDER = 'Konten sedang ditinjau tenaga profesional';

  function h() { return KH.h.apply(null, arguments); }

  function placeholderBox() {
    return h('div', { class: 'placeholder', role: 'note', svg: KH.icon('info', 32) },
      h('p', null, h('strong', null, PLACEHOLDER), h('br'), 'Isi kartu ini akan muncul setelah selesai ditinjau.'));
  }

  /** Isi kartu: hanya ditampilkan jika reviewed === true. */
  function bodyNodes(item) {
    if (item.reviewed !== true) return [placeholderBox()];
    var out = (item.body || []).map(function (b) {
      if (typeof b === 'string') return h('p', null, b);
      if (b && b.type === 'list') return h('ul', null, (b.items || []).map(function (li) { return h('li', null, String(li)); }));
      return null;
    });
    if (item.reviewed_by) {
      out.push(h('p', { class: 'small' }, 'Ditinjau oleh: ' + item.reviewed_by + (item.reviewed_at ? ' (' + item.reviewed_at + ')' : '')));
    }
    return out;
  }

  /* ---------- Audio: hanya diunduh saat diketuk, tidak pernah autoplay ---------- */
  var current = null; // {audio, button, url}

  function stopCurrent() {
    if (!current) return;
    current.audio.pause();
    setAudioButton(current.button, false);
    current = null;
  }

  function setAudioButton(button, playing, label) {
    button.setAttribute('aria-pressed', String(playing));
    button.querySelector('svg').outerHTML = KH.icon(playing ? 'jeda' : 'dengar', 28);
    button.querySelector('span').textContent = label || (playing ? 'Jeda' : button.getAttribute('data-label'));
  }

  function audioButton(audio) {
    var label = 'Dengarkan' + (audio.duration_label ? ' (' + audio.duration_label + ')' : '');
    var size = audio.size_kb ? h('p', { class: 'small' }, 'Ukuran suara ± ' + audio.size_kb + ' KB') : null;
    var msg = h('p', { class: 'small', 'aria-live': 'polite' });
    var button = h('button', { type: 'button', class: 'btn secondary', 'aria-pressed': 'false', 'data-label': label, svg: KH.icon('dengar', 28) }, h('span', null, label));
    var blobUrl = null;
    button.addEventListener('click', function () {
      if (current && current.button === button) {
        if (current.audio.paused) { current.audio.play(); setAudioButton(button, true); } else { current.audio.pause(); setAudioButton(button, false, 'Lanjutkan'); }
        return;
      }
      stopCurrent();
      msg.textContent = '';
      button.disabled = true;
      setAudioButton(button, false, 'Memuat…');
      // Unduh utuh lewat fetch agar service worker bisa menyimpannya untuk diputar offline.
      var p = blobUrl ? Promise.resolve(blobUrl) : fetch(audio.src, { credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.blob(); })
        .then(function (b) { blobUrl = URL.createObjectURL(b); return blobUrl; });
      p.then(function (url) {
        var a = new Audio(url);
        current = { audio: a, button: button };
        a.addEventListener('ended', function () { setAudioButton(button, false); current = null; });
        button.disabled = false;
        return a.play().then(function () { setAudioButton(button, true); });
      }).catch(function () {
        button.disabled = false;
        setAudioButton(button, false);
        msg.textContent = navigator.onLine ? 'Suara belum bisa diputar.' : 'Suara belum tersimpan di HP. Sambungkan internet untuk mendengarkan.';
      });
    });
    return h('div', { class: 'audio' }, button, size, msg);
  }

  /* ---------- Gambar: lazy; pada mode hemat hanya dimuat jika diketuk ---------- */
  function imageNode(img) {
    var make = function () {
      return h('img', { src: img.src, alt: img.alt || '', loading: 'lazy', decoding: 'async', class: 'content-img', width: img.width || null, height: img.height || null });
    };
    if (!KH.state.hemat) return make();
    var wrap = h('div', { class: 'img-wrap' });
    var b = h('button', { type: 'button', class: 'btn ghost', svg: KH.icon('gambar', 28) },
      h('span', null, 'Tampilkan gambar' + (img.size_kb ? ' (± ' + img.size_kb + ' KB)' : '')));
    b.addEventListener('click', function () { KH.fill(wrap, make()); });
    wrap.appendChild(b);
    return wrap;
  }

  function loadingThen(container, name, render) {
    KH.fill(container, h('p', { class: 'loading' }, 'Memuat…'));
    KH.loadContent(name).then(function (data) { KH.fill(container, render(data)); })
      .catch(function () {
        KH.fill(container, h('p', { class: 'notice' }, 'Konten belum bisa dimuat. Sambungkan internet sekali, setelah itu bisa dibuka tanpa internet.'));
      });
  }

  /* ---------- Beranda: 4 kartu besar ---------- */
  function firstAidSection() {
    var grid = h('div', { class: 'aid-grid' });
    loadingThen(grid, 'pertolongan', function (data) {
      return data.items.map(function (it) {
        return h('a', { href: '#/kartu/' + encodeURIComponent(it.id), class: 'aid-card aid-' + it.id, svg: KH.icon(it.icon, 48) },
          h('span', null, it.title));
      });
    });
    return h('section', { class: 'aid', 'aria-labelledby': 'aid-title' },
      h('h2', { id: 'aid-title' }, 'Pertolongan Pertama Pengasuhan'),
      grid);
  }

  /* ---------- Layar detail kartu ---------- */
  function emergencyBox() {
    var nums = KH.state.config.emergency_numbers || [];
    return h('section', { class: 'emergency', 'aria-labelledby': 'em-title' },
      h('h2', { id: 'em-title', svg: KH.icon('telepon', 36) }, 'Nomor darurat'),
      h('p', null, 'Ketuk untuk menelepon.'),
      nums.length ? nums.map(function (n) {
        return h('a', { class: 'btn em-call', href: 'tel:' + String(n.number).replace(/[^0-9+]/g, '') },
          h('span', { class: 'em-num' }, String(n.number)), h('span', null, n.label));
      }) : h('p', null, 'Nomor darurat belum diatur.'));
  }

  function screenKartu(id) {
    var container = h('div', { class: 'stack' }, h('h1', null, 'Memuat…'));
    KH.loadContent('pertolongan').then(function (data) {
      var it = data.items.filter(function (x) { return x.id === id; })[0];
      if (!it) { KH.fill(container, h('h1', null, 'Kartu tidak ditemukan'), backLink()); return; }
      document.title = it.title + ' · Keluarga Hebat';
      var parts = [h('h1', { class: 'with-ico', svg: KH.icon(it.icon, 48) }, it.title)];
      if (it.emergency) parts.push(emergencyBox());
      if (it.type === 'whatsapp') {
        var url = KH.state.config.whatsapp_group_url;
        parts.push(h('section', { class: 'card' },
          (it.body || []).map(function (p) { return h('p', null, p); }),
          url ? h('a', { class: 'btn primary', href: url, target: '_blank', rel: 'noopener noreferrer', svg: KH.icon('curhat', 28) },
            h('span', null, 'Buka grup WhatsApp')) : h('p', null, 'Tautan grup belum diatur.'),
          h('p', { class: 'small' }, 'Tautan membuka aplikasi WhatsApp. Belum ada fitur obrolan di aplikasi ini.')));
      } else {
        parts.push(h('section', { class: 'card' }, bodyNodes(it), it.reviewed === true && it.audio ? audioButton(it.audio) : null));
      }
      parts.push(backLink());
      KH.fill(container, parts);
      container.querySelector('h1').setAttribute('tabindex', '-1');
      container.querySelector('h1').focus();
    }).catch(function () {
      KH.fill(container, h('h1', null, 'Kartu'), h('p', { class: 'notice' }, 'Konten belum bisa dimuat. Sambungkan internet lalu coba lagi.'),
        id === 'kejang' ? emergencyBox() : null, backLink());
    });
    return { title: 'Pertolongan Pertama', nav: 'catat', node: container };
  }

  function backLink() {
    return h('a', { href: '#/', class: 'btn ghost', svg: KH.icon('kembali', 28) }, h('span', null, 'Kembali ke beranda'));
  }

  /* ---------- Ruang Belajar ---------- */
  function screenBelajar() {
    var list = h('div', { class: 'learn-list' });
    loadingThen(list, 'belajar', function (data) {
      return data.items.map(function (it) {
        var d = h('details', { class: 'learn' },
          h('summary', { svg: KH.icon(it.icon, 44) }, h('span', { class: 'learn-title' }, it.title,
            it.summary ? h('span', { class: 'learn-sum' }, it.summary) : null)),
          h('div', { class: 'learn-body' },
            bodyNodes(it),
            it.reviewed === true && it.image ? imageNode(it.image) : null,
            it.reviewed === true && it.audio ? audioButton(it.audio) : null));
        d.addEventListener('toggle', function () { if (!d.open) stopCurrent(); });
        return d;
      });
    });
    return {
      title: 'Ruang Belajar', nav: 'belajar',
      node: h('div', { class: 'stack' },
        h('h1', null, 'Ruang Belajar'),
        h('p', null, 'Kartu belajar singkat. Ketuk judul untuk membuka.'),
        list)
    };
  }

  window.addEventListener('hashchange', stopCurrent);

  KH.extraScreens = Object.assign(KH.extraScreens || {}, { kartu: screenKartu, belajar: screenBelajar });
  KH.homeSections = (KH.homeSections || []).concat([firstAidSection]);
})(self.KH);

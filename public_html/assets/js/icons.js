/*
 * Ikon SVG orisinal (inline). Tanpa atribut style agar lolos CSP ketat.
 * Semua ikon aria-hidden: makna selalu disampaikan oleh label teks di sebelahnya.
 */
(function (KH) {
  'use strict';

  // Warna lembut + garis tepi gelap (kontras ≥ 3:1 terhadap latar krem). Tanpa merah.
  var W = {
    cerah:   { fill: '#F6C453', ink: '#6B4A0E' },
    berawan: { fill: '#CBD5DE', ink: '#3F4D59' },
    gerimis: { fill: '#9CC5DE', ink: '#23506B' },
    badai:   { fill: '#C9BEE6', ink: '#463A73' }
  };
  KH.WEATHER_COLORS = W;

  var CLOUD = 'M14 33h20a7 7 0 0 0 .9-13.94A10 10 0 0 0 15.3 17.4 7.8 7.8 0 0 0 14 33z';

  var weather = {
    cerah:
      '<circle cx="24" cy="24" r="9" fill="' + W.cerah.fill + '" stroke="' + W.cerah.ink + '" stroke-width="2.5"/>' +
      '<g stroke="' + W.cerah.ink + '" stroke-width="2.5" stroke-linecap="round">' +
      '<path d="M24 5v6M24 37v6M5 24h6M37 24h6M10.6 10.6l4.2 4.2M33.2 33.2l4.2 4.2M10.6 37.4l4.2-4.2M33.2 14.8l4.2-4.2"/></g>',
    berawan:
      '<path d="' + CLOUD + '" fill="' + W.berawan.fill + '" stroke="' + W.berawan.ink + '" stroke-width="2.5" stroke-linejoin="round"/>',
    gerimis:
      '<path d="' + CLOUD + '" transform="translate(0 -5)" fill="' + W.gerimis.fill + '" stroke="' + W.gerimis.ink + '" stroke-width="2.5" stroke-linejoin="round"/>' +
      '<g stroke="' + W.gerimis.ink + '" stroke-width="2.5" stroke-linecap="round"><path d="M17 34l-2 5M25 34l-2 5M33 34l-2 5"/></g>',
    badai:
      '<path d="' + CLOUD + '" transform="translate(0 -6)" fill="' + W.badai.fill + '" stroke="' + W.badai.ink + '" stroke-width="2.5" stroke-linejoin="round"/>' +
      '<path d="M25 28l-5 8h5l-3 8 9-11h-5l3-5z" fill="#F7DC8A" stroke="' + W.badai.ink + '" stroke-width="2" stroke-linejoin="round"/>'
  };

  var ui = {
    // Navigasi
    catat: '<path d="M24 41s-15-8.6-15-19.5A8.5 8.5 0 0 1 24 16a8.5 8.5 0 0 1 15 5.5C39 32.4 24 41 24 41z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M18 25l4.5 4.5L31 21" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
    grafik: '<rect x="7" y="9" width="34" height="32" rx="4" fill="none" stroke="currentColor" stroke-width="3"/><path d="M7 18h34M16 5v8M32 5v8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><circle cx="16" cy="26" r="2.6" fill="currentColor"/><circle cx="24" cy="26" r="2.6" fill="currentColor"/><circle cx="32" cy="26" r="2.6" fill="currentColor"/><circle cx="16" cy="34" r="2.6" fill="currentColor"/><circle cx="24" cy="34" r="2.6" fill="currentColor"/>',
    belajar: '<path d="M24 13c-5-4-11-5-17-4v27c6-1 12 0 17 4 5-4 11-5 17-4V9c-6-1-12 0-17 4z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M24 13v27" stroke="currentColor" stroke-width="3"/>',
    akun: '<circle cx="24" cy="17" r="8" fill="none" stroke="currentColor" stroke-width="3"/><path d="M9 41c1.5-8 7.5-12 15-12s13.5 4 15 12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
    // Umum
    kembali: '<path d="M29 10L15 24l14 14" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>',
    centang: '<circle cx="24" cy="24" r="18" fill="none" stroke="currentColor" stroke-width="3"/><path d="M15 24.5l6 6L33 18" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>',
    belum: '<circle cx="24" cy="24" r="18" fill="none" stroke="currentColor" stroke-width="3"/><path d="M16 24h16" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>',
    gembok: '<rect x="10" y="21" width="28" height="20" rx="4" fill="none" stroke="currentColor" stroke-width="3"/><path d="M16 21v-5a8 8 0 0 1 16 0v5" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="24" cy="31" r="2.6" fill="currentColor"/>',
    offline: '<path d="M6 19a26 26 0 0 1 36 0M12 26a17 17 0 0 1 24 0M18 33a8 8 0 0 1 12 0" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M8 8l32 32" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
    awan_kirim: '<path d="' + CLOUD + '" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M24 30v-9M20 25l4-4 4 4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
    hapus: '<path d="M9 13h30M19 13V8h10v5M13 13l2 27h18l2-27" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>',
    keluar: '<path d="M20 8H10v32h10M28 15l9 9-9 9M37 24H18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>',
    hemat: '<path d="M24 6c8 7 13 14 13 21a13 13 0 0 1-26 0c0-7 5-14 13-21z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M24 39V24M24 31l-6-5M24 28l5-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>',
    telepon: '<path d="M14 7l6 1 3 8-4 3a22 22 0 0 0 10 10l3-4 8 3 1 6c0 3-3 6-6 6C21 40 8 27 8 13c0-3 3-6 6-6z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>',
    dengar: '<path d="M8 19h7l10-8v26l-10-8H8z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M31 18a8 8 0 0 1 0 12M35 13a15 15 0 0 1 0 22" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
    jeda: '<rect x="13" y="10" width="7" height="28" rx="2" fill="currentColor"/><rect x="28" y="10" width="7" height="28" rx="2" fill="currentColor"/>',
    gambar: '<rect x="6" y="9" width="36" height="30" rx="4" fill="none" stroke="currentColor" stroke-width="3"/><circle cx="17" cy="19" r="4" fill="currentColor"/><path d="M8 36l11-11 7 7 5-5 10 9" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>',
    hati: '<path d="M24 40s-15-8.6-15-19.5A8.5 8.5 0 0 1 24 15a8.5 8.5 0 0 1 15 5.5C39 31.4 24 40 24 40z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>',
    // Kartu Pertolongan Pertama
    tantrum: '<circle cx="24" cy="24" r="17" fill="none" stroke="currentColor" stroke-width="3"/><path d="M15 30c3-3 6-4 9-4s6 1 9 4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M15 18l4 2M33 18l-4 2" stroke="currentColor" stroke-width="3" stroke-linecap="round"/><path d="M38 8c2 2 3 4 2 6M10 8c-2 2-3 4-2 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>',
    motorik: '<circle cx="31" cy="33" r="8" fill="none" stroke="currentColor" stroke-width="3"/><path d="M25 30c4 1 9 4 11 7" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M8 26V13a3 3 0 0 1 6 0v8V9a3 3 0 0 1 6 0v12V11a3 3 0 0 1 6 0v11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 26c0 6 3 10 9 11" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>',
    kejang: '<path d="M24 5l15 6v11c0 10-6 17-15 21C15 39 9 32 9 22V11z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M24 15v15M16.5 22.5h15" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>',
    curhat: '<path d="M6 10h24v15H16l-6 5v-5H6z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/><path d="M34 18h8v15h-4v5l-6-5H20v-4" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>',
    info: '<circle cx="24" cy="24" r="18" fill="none" stroke="currentColor" stroke-width="3"/><path d="M24 22v12" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/><circle cx="24" cy="15" r="2.4" fill="currentColor"/>',
    rumah: '<path d="M7 22L24 8l17 14M12 19v20h24V19" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/><path d="M24 34s-6-3.4-6-7.6a3.3 3.3 0 0 1 6-2 3.3 3.3 0 0 1 6 2c0 4.2-6 7.6-6 7.6z" fill="currentColor"/>'
  };

  /** Kembalikan string <svg>. size: piksel. */
  KH.icon = function (name, size, cls) {
    var body = weather[name] || ui[name] || ui.info;
    var s = size || 32;
    return '<svg class="ico' + (cls ? ' ' + cls : '') + '" viewBox="0 0 48 48" width="' + s + '" height="' + s +
      '" aria-hidden="true" focusable="false">' + body + '</svg>';
  };
  KH.iconBody = function (name) { return weather[name] || ui[name] || ''; };
})(self.KH);

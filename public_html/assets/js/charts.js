/*
 * Tahap 3: "Pohon Ketabahan" (7 hari terakhir) dan "Grafik Kemajuanku" (4 minggu).
 * SVG inline buatan sendiri. Setiap cuaca punya BENTUK daun berbeda + tanda di dalamnya,
 * jadi warna tidak pernah menjadi satu-satunya penanda. Tanpa merah, tanpa skor.
 */
(function (KH) {
  'use strict';

  var C = KH.WEATHER_COLORS;
  var BARK = '#7A5C3E';
  var DAY_SHORT = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

  /* ---------- Bentuk daun (koordinat lokal, kira-kira ±19) ---------- */
  var LEAF = {
    cerah: function () {
      var c = C.cerah;
      return '<circle r="18" fill="' + c.fill + '" stroke="' + c.ink + '" stroke-width="2.5"/>' +
        '<circle r="5" fill="' + c.ink + '"/>' +
        '<path d="M0-13v4M0 9v4M-13 0h4M9 0h4" stroke="' + c.ink + '" stroke-width="2.5" stroke-linecap="round"/>';
    },
    berawan: function () {
      var c = C.berawan;
      return '<path d="M0-20C16-11 16 11 0 20C-16 11-16-11 0-20z" fill="' + c.fill + '" stroke="' + c.ink + '" stroke-width="2.5"/>' +
        '<path d="M-6 5h11a4 4 0 0 0 .4-7.9A5.5 5.5 0 0 0-5.3-3.5 4.3 4.3 0 0 0-6 5z" fill="#fff" stroke="' + c.ink + '" stroke-width="1.8"/>';
    },
    gerimis: function () {
      var c = C.gerimis;
      return '<path d="M0-21C7-9 14-1 14 7A14 14 0 0 1-14 7C-14-1-7-9 0-21z" fill="' + c.fill + '" stroke="' + c.ink + '" stroke-width="2.5"/>' +
        '<g fill="' + c.ink + '"><circle cx="-5" cy="4" r="2.4"/><circle cx="5" cy="4" r="2.4"/><circle cy="11" r="2.4"/></g>';
    },
    badai: function () {
      var c = C.badai;
      return '<path d="M0-20L18 0 0 20-18 0z" fill="' + c.fill + '" stroke="' + c.ink + '" stroke-width="2.5" stroke-linejoin="round"/>' +
        '<path d="M-8-3l5 6 4-6 5 6" fill="none" stroke="' + c.ink + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
    },
    kosong: function () {
      return '<circle r="13" fill="#FFF8EF" stroke="#8C7660" stroke-width="2.5" stroke-dasharray="4 4"/>';
    }
  };
  KH.leafSvg = function (kind, size) {
    return '<svg class="ico" viewBox="-22 -22 44 44" width="' + size + '" height="' + size + '" aria-hidden="true" focusable="false">' +
      (LEAF[kind] || LEAF.kosong)() + '</svg>';
  };

  /* ---------- Pohon Ketabahan ---------- */
  // Posisi 7 daun, dari yang terlama (kiri bawah) ke hari ini (kanan bawah).
  var SPOTS = [[40, 212], [62, 120], [110, 68], [170, 44], [230, 68], [278, 120], [300, 212]];
  var ROOTS = [[166, 214], [167, 184], [168, 166], [170, 156], [172, 166], [173, 184], [174, 214]];

  function last7() {
    var today = KH.dates.today();
    var out = [];
    for (var i = 6; i >= 0; i--) out.push(KH.dates.add(today, -i));
    return out;
  }

  function treeSvg() {
    var checkins = KH.getCheckins();
    var days = last7();
    var today = KH.dates.today();
    var spoken = [];
    var svg = '<ellipse cx="170" cy="292" rx="150" ry="12" fill="#E9DCC5"/>' +
      '<path d="M150 292C157 250 157 205 162 156h16c5 49 5 94 12 136z" fill="' + BARK + '"/>';
    // Cabang
    SPOTS.forEach(function (p, i) {
      var r = ROOTS[i];
      var mx = (p[0] + r[0]) / 2, my = Math.min(p[1], r[1]) + 18;
      svg += '<path d="M' + r[0] + ' ' + r[1] + 'Q' + mx + ' ' + my + ' ' + p[0] + ' ' + p[1] + '" fill="none" stroke="' + BARK + '" stroke-width="7" stroke-linecap="round"/>';
    });
    // Daun + label hari
    days.forEach(function (date, i) {
      var rec = checkins.get(date);
      var kind = rec ? rec.weather : 'kosong';
      var p = SPOTS[i];
      var d = KH.dates.parse(date);
      var isToday = date === today;
      var label = isToday ? 'Hari ini' : DAY_SHORT[d.getDay()];
      svg += '<g transform="translate(' + p[0] + ' ' + p[1] + ')">' +
        (isToday ? '<circle r="33" fill="none" stroke="#1E5B57" stroke-width="2.5"/>' : '') +
        '<g class="leaf' + (isToday && rec ? ' leaf-new' : '') + '" transform="scale(1.4)">' + LEAF[kind]() + '</g>' +
        '<text y="' + (isToday ? 52 : 46) + '" text-anchor="middle" font-size="16" font-weight="700" fill="#2A211B" stroke="#FFFFFF" stroke-width="5" stroke-linejoin="round" paint-order="stroke">' + label + '</text></g>';
      spoken.push(KH.dates.label(date) + ': ' + (rec ? KH.WEATHER_LABEL[rec.weather] : 'belum dicatat'));
    });
    return {
      markup: '<svg viewBox="0 0 340 306" class="tree-svg" role="img" aria-label="Pohon Ketabahan, 7 hari terakhir. ' +
        spoken.join('. ').replace(/"/g, '') + '.">' + svg + '</svg>'
    };
  }

  function legend(withRest) {
    var items = KH.WEATHER.map(function (w) { return [w.id, w.label]; }).concat([['kosong', 'Belum dicatat']]);
    var list = KH.h('ul', { class: 'legend', 'aria-label': 'Keterangan' },
      items.map(function (it) { return KH.h('li', { svg: KH.leafSvg(it[0], 28) }, it[1]); }));
    if (withRest) {
      list.appendChild(KH.h('li', null, KH.h('span', { class: 'rest-mark', 'aria-hidden': 'true' }, '✓'), 'Sempat istirahat'));
    }
    return list;
  }

  function treeSection() {
    var holder = KH.h('div', { class: 'tree', 'data-live': 'tree' });
    holder.insertAdjacentHTML('afterbegin', treeSvg().markup);
    return KH.h('section', { class: 'card tree-card', 'aria-labelledby': 'tree-title' },
      KH.h('h2', { id: 'tree-title' }, 'Pohon Ketabahan'),
      KH.h('p', null, 'Setiap daun adalah satu hari dari 7 hari terakhir.'),
      holder,
      legend(false));
  }

  /* ---------- Grafik Kemajuanku (4 minggu) ---------- */

  function mondayOf(dateStr) {
    var d = KH.dates.parse(dateStr);
    return KH.dates.add(dateStr, -((d.getDay() + 6) % 7));
  }

  function weekTitle(i) {
    return ['Minggu ini', 'Minggu lalu', '2 minggu lalu', '3 minggu lalu'][i];
  }

  function rangeLabel(start) {
    var a = KH.dates.parse(start), b = KH.dates.parse(KH.dates.add(start, 6));
    var B = KH.dates.BULAN;
    return a.getMonth() === b.getMonth()
      ? a.getDate() + '–' + b.getDate() + ' ' + B[b.getMonth()]
      : a.getDate() + ' ' + B[a.getMonth()] + ' – ' + b.getDate() + ' ' + B[b.getMonth()];
  }

  function screenGrafik() {
    var h = KH.h;
    var checkins = KH.getCheckins();
    var today = KH.dates.today();
    var thisMonday = mondayOf(today);
    var question = KH.state.config.checkmark_question;
    var counts = { cerah: 0, berawan: 0, gerimis: 0, badai: 0 };
    var total = 0, rested = 0;

    var weeks = [];
    for (var w = 0; w < 4; w++) {
      var start = KH.dates.add(thisMonday, -7 * w);
      var cells = [];
      for (var d = 0; d < 7; d++) {
        var date = KH.dates.add(start, d);
        var rec = checkins.get(date);
        var future = date > today;
        var dayName = KH.dates.HARI[KH.dates.parse(date).getDay()];
        if (rec && !future) {
          counts[rec.weather]++;
          total++;
          if (rec.rested) rested++;
        }
        var spoken = KH.dates.label(date) + ': ' + (future ? 'belum tiba' : rec
          ? KH.WEATHER_LABEL[rec.weather] + (rec.rested ? ', sempat istirahat' : '')
          : 'belum dicatat');
        cells.push(h('li', { class: 'day' + (future ? ' future' : '') + (date === today ? ' today' : '') },
          h('span', { class: 'sr-only' }, spoken),
          h('span', { class: 'dname', 'aria-hidden': 'true' }, dayName.slice(0, 3)),
          h('span', { class: 'dicon', 'aria-hidden': 'true', svg: future ? null : KH.leafSvg(rec ? rec.weather : 'kosong', 34) }),
          h('span', { class: 'rest-mark', 'aria-hidden': 'true' }, rec && rec.rested && !future ? '✓' : '')));
      }
      weeks.push(h('section', { class: 'week', 'aria-label': weekTitle(w) + ', ' + rangeLabel(start) },
        h('h3', null, weekTitle(w), h('span', { class: 'range' }, ' · ' + rangeLabel(start))),
        h('ol', { class: 'week-row' }, cells)));
    }

    var summary;
    if (!total) {
      summary = [h('p', null, 'Belum ada catatan dalam 4 minggu ini. Kamu bisa mulai kapan saja, cukup dua ketukan.')];
    } else {
      var max = Math.max(counts.cerah, counts.berawan, counts.gerimis, counts.badai);
      var top = KH.WEATHER.filter(function (x) { return counts[x.id] === max; }).map(function (x) { return x.label; });
      summary = [
        h('p', { class: 'big-text' }, 'Kamu sudah mencatat ' + total + ' hari dalam 4 minggu ini.'),
        h('p', null, 'Terima kasih sudah meluangkan waktu untuk dirimu.'),
        h('p', null, 'Cuaca hati yang paling sering: ' + top.join(' dan ') + '.'),
        h('ul', { class: 'count-list' }, KH.WEATHER.map(function (x) {
          return h('li', { svg: KH.leafSvg(x.id, 28) }, x.label + ': ' + counts[x.id] + ' hari');
        })),
        h('p', null, '"' + question + '": ' + rested + ' hari.')
      ];
    }

    return {
      title: 'Grafik Kemajuanku', nav: 'grafik',
      node: h('div', { class: 'stack' },
        h('h1', null, 'Grafik Kemajuanku'),
        h('section', { class: 'card', 'aria-label': 'Ringkasan' }, summary),
        h('div', { class: 'weeks' }, weeks),
        h('section', { class: 'card' }, h('h2', null, 'Keterangan'), legend(true)))
    };
  }

  KH.extraScreens = Object.assign(KH.extraScreens || {}, { grafik: screenGrafik });
  KH.homeSections = (KH.homeSections || []).concat([treeSection]);
  KH.extraLive = Object.assign(KH.extraLive || {}, {
    tree: function (el) { el.innerHTML = treeSvg().markup; }
  });
})(self.KH);

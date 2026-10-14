# Test checklist — KELUARGA HEBAT

Run these on the **staging** site served over HTTPS with gzip on, and on at least one **low-end Android phone** (2 GB RAM, Chrome, Android 9+). Write the date, tester, device and result next to each item.

Prototype baseline, measured during development on the local dev server (no gzip, mobile emulation):
Lighthouse Performance **100**, Accessibility **100**, Best Practices **100**, welcome screen **104 KB** transferred, no horizontal scroll at 360 px, no CSP violations in the console.

---

## 1. Lighthouse (Chrome DevTools → Lighthouse, mode "Navigation", device "Mobile")

| Screen | URL | Perf ≥ 90 | A11y ≥ 95 | Best practices ≥ 95 | Result |
|---|---|---|---|---|---|
| Welcome (logged out) | `/` | | | | |
| Home / Catat Emosi (logged in) | `/#/` | | | | |
| Grafik Kemajuanku | `/#/grafik` | | | | |
| Ruang Belajar | `/#/belajar` | | | | |
| Kartu Kejang/Kaku | `/#/kartu/kejang` | | | | |
| Researcher dashboard | `/peneliti/` | | | | |

For the logged-in screens, log in first in the same browser profile, then run Lighthouse with **"Clear storage" unchecked**.

### PWA installability (Lighthouse 12+ removed the PWA category)
- [ ] DevTools → Application → **Manifest**: no errors; name, icons 192/512, maskable icon and `display: standalone` are detected
- [ ] DevTools → Application → **Service workers**: `sw.js` is activated and running, scope `/`
- [ ] Chrome on Android shows **"Install app" / "Tambahkan ke layar utama"**. After installing, the app opens full-screen with the theme color
- [ ] DevTools → Application → **Cache storage**: `shell-kh-…` contains all shell files

## 2. Page weight per screen (target < 300 KB, hard limit 1 MB)

DevTools → Network → tick **Disable cache** → reload each screen and record the **"transferred"** total in the status bar.

| Screen | First load (cold) | Revisit (SW warm) | ≤ 300 KB? |
|---|---|---|---|
| Welcome | | | |
| Home (with tree + 4 cards) | | | |
| Grafik Kemajuanku | | | |
| Ruang Belajar (no card opened) | | | |
| Ruang Belajar after tapping "Dengarkan" | | | |
| Researcher dashboard | | | |

Reference sizes from the repository (raw / gzip -9):

| File | Raw | Gzip |
|---|---|---|
| index.html | 1.7 KB | 0.7 KB |
| app.css | 13.2 KB | 3.4 KB |
| app.js + core.js + icons.js + charts.js + content.js | 79.3 KB | 23.4 KB |
| content JSON (3 files) | 4.1 KB | 1.8 KB |
| sw.js + manifest + logo + icon-192 | 8.6 KB | 5.9 KB |
| **Whole app shell** | **≈ 108 KB** | **≈ 36 KB** |
| Audio sample (only on tap) | 16 KB | — |

- [ ] Nothing is loaded from another domain (Network filter: `-domain:<your domain>` shows 0 requests)
- [ ] With **Mode hemat** ON, opening a learning card requests **no image** until "Tampilkan gambar" is tapped
- [ ] No audio request happens until "Dengarkan" is tapped, and nothing autoplays
- [ ] Throttle to **"Slow 3G"**: the home screen is usable in under 5 s on first load and under 1 s on revisit

## 3. Offline test

1. [ ] Log in online. Let the app load fully. Then put the phone in **airplane mode**.
2. [ ] Close the app completely and reopen it. The app opens (from the service worker), the grey banner says *"Tidak ada internet. Catatan tetap tersimpan di HP."*, and the tree and chart show the existing data.
3. [ ] Do a check-in (2 taps). The confirmation shows *"Tersimpan di HP. Akan dikirim saat ada internet."*
4. [ ] Edit today's check-in while still offline. It still says it will be sent later.
5. [ ] Open Ruang Belajar and a first-aid card. The content shows (cached JSON). Audio that has already been played once still plays.
6. [ ] Akun shows *"1 catatan menunggu dikirim."* Tap **Keluar**. A warning appears that unsent records would be lost → tap **Batal**.
7. [ ] Turn airplane mode **off**. Within a few seconds, the status changes to *"Sudah terkirim"* (or after reopening the app on iOS).
8. [ ] In the database, there is exactly **one** row for that user and date, with the edited value:
   `SELECT checkin_date, weather, rested FROM checkins WHERE user_id = … ORDER BY checkin_date DESC LIMIT 3;`
9. [ ] **Idempotency:** in DevTools → Network, right-click the `checkins-sync` request → *Replay XHR* several times. The row count stays the same.
10. [ ] **Background sync (Android Chrome):** make a check-in offline, close the app, and go online. The record reaches the server without reopening the app (this can take a few minutes).
11. [ ] **Midnight rule:** make an offline check-in before midnight and go online after midnight. It is accepted. Try to edit yesterday's record: the app does not offer it.

## 4. 360 px layout

DevTools device toolbar → **360 × 640** (also test 320 × 568 and landscape 640 × 360), plus a real small phone.

- [ ] No horizontal scrolling on any screen (welcome, consent, register, login, home, grafik, belajar, every card, akun, PIN, delete account, dashboard)
- [ ] The bottom nav shows all 3 buttons with icon + label. "Grafik Kemajuanku" may wrap to 2 lines but is not cut off
- [ ] Weather buttons form a 2×2 grid, each ≥ 56 px tall (they are 128 px)
- [ ] All tap targets are ≥ 56 × 56 px (DevTools → Inspect → computed size), including links styled as buttons, the switch, and the password "Tampilkan" toggle
- [ ] Base text is 18 px. Zoom the browser to 200%: everything is still readable and usable
- [ ] Android **Settings → Display → Font size: largest**: nothing overlaps, and the nav is still usable
- [ ] The tree's day labels do not collide with leaves. The 4-week grid fits 7 columns

## 5. TalkBack test (Android → Settings → Accessibility → TalkBack)

Navigate with swipe right/left and double-tap only:

- [ ] The page language is read in Indonesian (`lang="id"`)
- [ ] On each screen change, focus moves to the screen title (h1) and TalkBack reads it
- [ ] Bottom nav: each item is read as a link with its label; the current one says "halaman saat ini"/"current page"
- [ ] Weather buttons are read as *"Cerah, tombol"* etc. When editing, the current choice is read as "pressed"/"ditekan"
- [ ] After choosing a weather, focus moves to the question (the configured checkmark question), and "Ya"/"Belum" are read as buttons
- [ ] The confirmation heading *"Terima kasih sudah mencatat"* is read. The sync status is announced
- [ ] The Pohon Ketabahan tree is read as one image with a summary of all 7 days ("Kamis, 8 Oktober: Berawan. …"). The legend items are read
- [ ] Grafik: each day cell reads e.g. *"Senin, 5 Oktober: Cerah, sempat istirahat"*; future days say "belum tiba"
- [ ] Form errors (register, login) are announced. Fields have labels and hints. The password show/hide toggle reports its state
- [ ] Mode hemat is read as a switch with on/off state
- [ ] The support card (3× badai; use seed user `contoh07@contoh.invalid`) is read in order, and its contact button is a link
- [ ] Learning cards are expandable (details/summary) and report expanded/collapsed
- [ ] The Kejang/Kaku card: the emergency box comes first, and each number is a link that opens the dialer
- [ ] All decorative SVG icons are skipped (they are `aria-hidden`); every icon has a visible text label

## 6. Reduced motion & contrast
- [ ] Android **Remove animations** (or DevTools → Rendering → `prefers-reduced-motion: reduce`): no leaf animation, no switch/toast transitions
- [ ] Text contrast ≥ 4.5:1 (ink `#2A211B` on cream `#FFF8EF` ≈ 15:1; primary `#1E5B57` on white ≈ 7.5:1)
- [ ] The tree and chart can be understood in greyscale (DevTools → Rendering → Emulate vision deficiency: achromatopsia / protanopia / deuteranopia / tritanopia). The leaf shapes differ: circle, almond, drop, diamond, dashed circle
- [ ] No red anywhere in the participant app. No "good/bad" wording, no scores

## 7. Functional & security
- [ ] Register → consent version stored (`SELECT * FROM consents`). The email column is not readable (`email_enc` is binary)
- [ ] One check-in per day: a second check-in on the same day edits the existing row and does not create a new one
- [ ] Password reset: the email arrives through SMTP, the link works once, expires after 1 hour, and other devices are signed out
- [ ] 9 wrong logins for the same email → "Terlalu banyak percobaan" (HTTP 429)
- [ ] POST to the API without the `X-CSRF-Token` header → 403. A request with a foreign `Origin` header → 403
- [ ] User A's session cannot read or modify user B's check-ins. Re-sending B's UUID as A is rejected and B's row is unchanged
- [ ] Tarik persetujuan → the user disappears from the dashboard counts and the CSV at once. The app shows the consent screen
- [ ] Hapus akun → every row for that user is gone (users, sessions, checkins, consents, support_events)
- [ ] PIN: wrong PIN 5× → wait time. App backgrounded > 5 min → locked again. "Lupa PIN" → login clears the PIN
- [ ] Researcher dashboard: a wrong TOTP code fails, reusing the same code fails, idle 30 min → logged out
- [ ] CSV header is exactly `handle,date,weather,checkmark`, and the file contains no `@`
- [ ] `https://securityheaders.com` (or `curl -I`) shows the CSP, HSTS, nosniff, Referrer-Policy and X-Frame-Options headers
- [ ] `/app/`, `config.php` and `.git` are not reachable from the web
- [ ] HTTP URLs redirect to HTTPS

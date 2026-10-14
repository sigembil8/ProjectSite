# KELUARGA HEBAT

**Portal Refleksi Mandiri dan Sahabat Belajar Keluarga Anak Disabilitas**

A lightweight, offline-first Progressive Web App (in Bahasa Indonesia) for parents of children with disabilities. Each day they record their "cuaca hati" (heart weather) with two taps. The app also has a 7-day "Pohon Ketabahan" tree, a 4-week progress view, first-aid parenting cards, short learning cards, and a separate researcher dashboard that shows aggregates only.

This is a **prototype**. Every piece of medical or parenting content is a placeholder until it has been professionally reviewed. The consent text and the support-card text are also placeholders until the research ethics committee approves them.

---

## 1. Stack

| Layer | Choice |
|---|---|
| Hosting | cPanel shared hosting: Apache, PHP 8.1+, MariaDB 10.4+, cron, SSH |
| Frontend | Plain HTML, CSS and vanilla JS. No frameworks, no build step, no CDN, no trackers, no web fonts (system fonts only). Icons and charts are inline SVG |
| Backend | A small PHP JSON API using PDO prepared statements. No Composer packages |
| Offline | Service worker (app shell cache) and IndexedDB (check-ins). Sync is idempotent because every check-in has a UUID generated on the phone |

## 2. Folder structure

```
app/                       ← PRIVATE: upload OUTSIDE public_html (e.g. /home/USER/app)
  bootstrap.php            loads config + libraries
  api.php                  all JSON API routes
  config.example.php       copy to config.php and fill in
  keys.php                 encryption keys (created by setup.php or gen_keys.php --write; not in Git)
  lib/                     db, http (headers, CSRF), crypto, auth, rate limit, mailer (SMTP), TOTP, validation
  sql/schema.sql           tables + researcher views
  scripts/
    gen_keys.php           prints random keys for config.php
    seed.php               fake users + check-ins (--purge removes them)
    create_researcher.php  creates a researcher login + TOTP secret
    cron_cleanup.php       daily cleanup (expired sessions, reset tokens, rate-limit rows)
    make_icons.php         regenerates placeholder PWA icons (needs GD)
    dev_router.php         local testing only (php -S)
  storage/                 logs (mail.log in 'log' mode, php-error.log); denied by .htaccess
  .htaccess                "Require all denied" (fallback if app/ must sit inside public_html)

public_html/               ← PUBLIC web root
  index.html               app shell
  kh-private-path.php      finds the private folder (env KH_APP_DIR → ../../keluarga-private → ../app)
  setup.php                one-time browser setup page (delete after use)
  sw.js                    service worker
  manifest.webmanifest
  .htaccess                HTTPS redirect, strict CSP, security + cache headers
  api/index.php            API entry point → ../../app
  peneliti/index.php       researcher dashboard (+ peneliti.css)
  assets/css/app.css
  assets/js/core.js        IndexedDB, API client, sync (shared with sw.js)
  assets/js/icons.js       original inline SVG icons
  assets/js/charts.js      Pohon Ketabahan + Grafik Kemajuanku
  assets/js/content.js     first-aid cards + Ruang Belajar
  assets/js/app.js         router, screens, check-in, PIN, account
  assets/img/              logo.svg (PLACEHOLDER) + PWA icons (placeholders)
  content/                 persetujuan.json, pertolongan.json, belajar.json, audio/, img/

docs/TEST_CHECKLIST.md     QA checklist (Lighthouse, page weight, offline, 360 px, TalkBack)
```

## 3. Deploying to cPanel, step by step

> **Browser-only deployment to the Rumahweb TEST server** (File Manager + phpMyAdmin + one-time `setup.php`): see [`docs/DEPLOY_RUMAHWEB.md`](docs/DEPLOY_RUMAHWEB.md). The upload zip is built with `python3 deploy/build_zip.py`.


> In the examples below, `USER` is your cPanel username and `keluargahebat.example.id` is your domain.

### 3.1 Database
1. In cPanel, open **MySQL® Databases**.
2. Create a database, for example `USER_kh`.
3. Create a user, for example `USER_khapp`, with a long random password.
4. Add the user to the database with **ALL PRIVILEGES**. The import creates views, so it needs `CREATE VIEW`.
5. Open **phpMyAdmin**, select `USER_kh`, go to **Import**, and upload `app/sql/schema.sql`.
   Or over SSH: `mysql -u USER_khapp -p USER_kh < app/sql/schema.sql`

### 3.2 Upload the files
1. Upload `app/` to `/home/USER/app`, **next to** `public_html`, not inside it.
2. Upload the **contents** of `public_html/` to `/home/USER/public_html/`. Make sure the hidden `.htaccess` files are included: in File Manager, open **Settings → Show Hidden Files**.
3. `public_html/kh-private-path.php` finds the private folder in this order: the environment variable `KH_APP_DIR`, then `<two levels above the web root>/keluarga-private` (e.g. `/home/USER/keluarga-private` for a web root `/home/USER/public_html/<sub>`), then `../app` next to the web root (repository layout).

### 3.3 Configure
```bash
ssh USER@host
cd ~/app
cp config.example.php config.php
php scripts/gen_keys.php        # paste the three lines into the 'keys' section
nano config.php                 # app_url, db, mail (SMTP), public texts/links
chmod 600 config.php
```
- `app_url` must be the exact HTTPS origin, for example `https://keluargahebat.example.id`. It is used for the Origin/CSRF check and for reset links.
- **Never change `keys` after users exist.** Encrypted emails and researcher TOTP secrets would become unreadable. Back the keys up somewhere safe (a password manager).
- **SMTP:** create a mailbox in cPanel (**Email Accounts**, e.g. `noreply@…`). Usually the host is `mail.yourdomain`, the port is `465` and `encryption` is `ssl`. For STARTTLS, use port `587` with `tls`.
- `consent_version` must equal `"version"` in `public_html/content/persetujuan.json`.
- `public.*` holds everything the app shows that has to be configurable: the checkmark question, the WhatsApp group link, the emergency numbers, and the support card text and contact. **Check the emergency numbers before launch.**

### 3.4 PHP version and HTTPS
1. In cPanel, open **MultiPHP Manager** and pick **PHP 8.1 or newer** for the domain. Required extensions: `pdo_mysql`, `sodium`, `mbstring`, `openssl`, and `gd` (only for icon generation).
2. In cPanel, open **SSL/TLS Status** and run **AutoSSL** so the site has a valid certificate. `.htaccess` redirects all HTTP traffic to HTTPS and sends HSTS.
3. If the site sits behind Cloudflare or another proxy, set `trusted_ip_header` (for example `HTTP_CF_CONNECTING_IP`) so rate limiting sees real client IPs.

### 3.5 Cron
In cPanel, open **Cron Jobs** and add a daily job:
```
15 3 * * * /usr/local/bin/php /home/USER/app/scripts/cron_cleanup.php >/dev/null 2>&1
```
(Check the PHP binary path with `which php`. On some hosts it is `/opt/cpanel/ea-php81/root/usr/bin/php`.)

### 3.6 Researcher account (TOTP two-factor)
```bash
cd ~/app && php scripts/create_researcher.php peneliti1
```
The script asks for a password of at least 14 characters, then prints a TOTP secret and an `otpauth://` URI. Add the secret to an authenticator app (Google Authenticator, Aegis, 2FAS…). The dashboard is at `https://keluargahebat.example.id/peneliti/`. Running the script again for the same username resets the password and the TOTP secret.

**Optional hardening (recommended):** create a second MariaDB user for the dashboard and set `db_research` in config:
```sql
GRANT SELECT ON USER_kh.research_participants TO 'USER_khres'@'localhost';
GRANT SELECT ON USER_kh.research_checkins TO 'USER_khres'@'localhost';
GRANT SELECT ON USER_kh.research_support_events TO 'USER_khres'@'localhost';
GRANT SELECT, UPDATE ON USER_kh.researchers TO 'USER_khres'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON USER_kh.rate_limits TO 'USER_khres'@'localhost';
```
(On cPanel, phpMyAdmin may not allow per-table grants. In that case keep `db_research = null`. The dashboard code already reads only from the views.)

### 3.7 Logo and icons
- Replace `public_html/assets/img/logo.svg` with the official logo. It must be a square SVG.
- Replace the PNG icons in `assets/img/`: `icon-192.png`, `icon-512.png`, `icon-maskable-512.png` (keep the logo inside the central 80%), and `apple-touch-icon.png`.

### 3.8 Before going live
- [ ] `php scripts/seed.php --purge` if you ever seeded this database
- [ ] Consent text approved by the ethics committee is in `content/persetujuan.json` (with `"status": "final"`), and the version matches `consent_version`
- [ ] Support card text and contact are approved
- [ ] WhatsApp group link and emergency numbers are set and checked
- [ ] Bump `VERSION` in `public_html/sw.js` on **every** release so phones fetch the new app shell
- [ ] Run `docs/TEST_CHECKLIST.md`

## 4. Updating content (and later: WordPress)

All texts in cards live in `public_html/content/*.json`:

- `pertolongan.json`: the four first-aid cards. The `body` is shown **only** when `"reviewed": true`. Until then, the app shows *"Konten sedang ditinjau tenaga profesional"*. The `kejang` card always shows the emergency-numbers box first. Optional fields: `reviewed_by`, `reviewed_at`, `audio`.
- `belajar.json`: learning cards. `body` items are strings (paragraphs) or `{"type":"list","items":[…]}`. Optional fields: `image: {src, alt, size_kb, width, height}` and `audio: {src, duration_label, size_kb}`.
- `persetujuan.json`: the consent text.

Content is rendered as plain text (never as HTML), so a JSON file cannot inject scripts.

**Audio:** the placeholder is a 2-second chime (`content/audio/contoh-panduan.wav`). Replace it with narrated recordings as mono MP3/M4A at about 32 kbps, which works out to about 240 KB per minute. Audio is downloaded only when the user taps **Dengarkan**, never autoplays, and is cached afterwards for offline replay.

**Switching to a WordPress REST API later:** set `public.content_base` in config to a URL that returns the same JSON shapes. For example, a small WP plugin can register `/wp-json/kh/v1/pertolongan.json`, `/belajar.json` and `/persetujuan.json`. The app fetches `content_base + '<name>.json'`. If WordPress is on a different domain:
1. add that origin to `connect-src`, `img-src` and `media-src` in the CSP in `public_html/.htaccess`;
2. send `Access-Control-Allow-Origin` from WordPress;
3. update the content rule in `sw.js` (it currently caches same-origin `content/` only).

## 5. How it works

### Check-in and offline sync
- The phone keeps one record per day in IndexedDB: `{date, uuid, weather, rested, updated_at, sync}`.
- Saving is local first. Sync runs immediately if online. Otherwise it runs on the `online` event, when the app is reopened, or through Background Sync in the service worker (Chrome/Android), even if the app is closed.
- `POST api/?r=checkins-sync` is idempotent:
  - The same UUID sent twice never creates a second row.
  - Two writes for the same `(user, date)` resolve by last-write-wins on `updated_at`.
  - A UUID that belongs to another row is rejected and never touched.
- "Editable until midnight" is enforced on both sides. The phone only edits today's record. The server accepts a write only if `updated_at` falls within that calendar day in any Indonesian time zone (`client_utc_offsets`, WIB–WIT) and the date is at most 31 days old. Late offline syncs of a same-day edit are still accepted.

### Accounts and privacy
- Users register with an email, a password and a handle (pseudonym). The app warns against using a real name. Nothing about the child is ever asked.
- Emails are stored **encrypted** (libsodium secretbox) plus an HMAC "blind index" used for login lookup. They are used only for login and password reset. The researcher dashboard reads only the `research_*` views, which contain no email and only participants with active consent.
- "Stay signed in" uses an HttpOnly, Secure, SameSite=Strict cookie (`selector:validator`). Only a SHA-256 hash of the validator is stored. Sessions last 365 days and extend on use. A password reset signs out every other device.
- The optional 4-digit PIN is hashed locally with PBKDF2-SHA256 (100k iterations, random salt) and never leaves the phone. After 5 wrong tries there is an escalating wait. The app re-locks after 5 minutes in the background. *Note:* this is a privacy screen against casual access, not encryption. Someone with full access to the device storage could brute-force a 4-digit PIN. "Lupa PIN" signs in again with email and password.
- Consent: a consent screen is shown before registration, and the version is stored in `users` and logged in `consents`. **Tarik persetujuan** (withdraw) excludes the user from all research views and exports at once, and blocks the app until they consent again or delete the account. **Hapus akun dan semua data saya** deletes the user, and `ON DELETE CASCADE` removes their sessions, check-ins, consents, support events and reset tokens.
- Support safeguard: if the 3 most recent check-ins are all "badai", the home screen shows a gentle card. Its title, text and contact come from config. The app logs at most one `card_shown` / `contact_tapped` event per day into `support_events` (aggregate only in the dashboard). No diagnosis, no labels.

### Security summary
- `password_hash` (bcrypt/argon per PHP default), with rehash on login.
- CSRF uses a double-submit token (an HttpOnly cookie plus the `X-CSRF-Token` header), together with Origin and `Sec-Fetch-Site` checks. The dashboard forms use session CSRF tokens.
- Rate limiting:
  - login: 30 per IP and 8 per email per 15 minutes
  - registration: 10 per IP per hour
  - password reset: 5 per IP and 3 per email per hour
  - researcher login: 10 per IP and 5 per username per 15 minutes
  - sync: 120 per user per hour
- Every personal-data query uses the `user_id` from the session, never from input.
- Headers:
  - HSTS, `nosniff`, `Referrer-Policy: no-referrer`, `X-Frame-Options: DENY`, COOP and Permissions-Policy
  - **strict CSP:** `default-src 'self'`, no inline scripts or styles, `frame-ancestors 'none'`
  - the API sends `default-src 'none'`
- The researcher dashboard requires a TOTP code (RFC 6238, ±30 s, replay-protected). Sessions time out after 30 minutes idle, and the session ID is regenerated on login.
- CSV export contains exactly `handle,date,weather,checkmark`. Values are protected against spreadsheet formula injection.

## 6. Local development

```bash
# MariaDB running locally, then:
mysql -e "CREATE DATABASE kh_test CHARACTER SET utf8mb4"
mysql kh_test < app/sql/schema.sql
cp app/config.example.php app/config.php     # set app_url=http://localhost:8080, require_https=false,
php app/scripts/gen_keys.php                 # db credentials, mail.transport='log', and paste keys
php app/scripts/seed.php                     # 30 fake users; login: contoh07@contoh.invalid / sandi-contoh-123
php -S localhost:8080 -t public_html app/scripts/dev_router.php
```
`localhost` counts as a secure context, so the service worker, Secure cookies and WebCrypto all work over plain HTTP there. In `'log'` mail mode, reset emails are written to `app/storage/mail.log`.

## 7. Database tables

| Table | Purpose |
|---|---|
| `users` | handle, encrypted email + blind index, password hash, consent status/version, `is_seed` |
| `sessions` | long-lived login tokens (hashed) |
| `checkins` | `uuid`, `user_id`, `checkin_date`, `weather` (cerah/berawan/gerimis/badai), `rested`, `client_updated_at`; **UNIQUE (user_id, checkin_date)** |
| `consents` | consent given / withdrawn log with version |
| `support_events` | optional, at most 1 per user/day/kind |
| `password_resets` | hashed one-time tokens (1 hour) |
| `rate_limits` | hashed buckets |
| `researchers` | researcher logins + encrypted TOTP secret |
| views `research_participants`, `research_checkins`, `research_support_events` | what the dashboard reads |

## 8. Known limitations / TODO
- Placeholders: logo, PWA icons, consent text, support card text, WhatsApp link, all first-aid and learning content, the audio sample.
- No dark mode yet. The UI is a single high-contrast light theme.
- The dashboard shows small counts. If the ethics committee requires it, add a minimum cell size (for example, hide weeks with fewer than 5 participants).
- iOS Safari has no Background Sync. There, check-ins sync when the app is next opened while online.
- Lighthouse 12 no longer has a "PWA" category. Check installability in Chrome DevTools → Application → Manifest (see the checklist).

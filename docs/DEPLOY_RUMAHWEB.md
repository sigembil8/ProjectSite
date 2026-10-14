# Deploying Keluarga Hebat to Rumahweb cPanel (TEST site)

**Site:** https://keluarga.ujicobaicha.my.id   **cPanel user:** `ujib7518`
**This is a TEST site with fake data only.**

You only need a web browser. Everything happens in cPanel (File Manager, phpMyAdmin, MySQL® Databases, Cron Jobs) and in one setup page of the app.

---

## What goes where

You upload **one zip**: `keluarga-hebat-rumahweb.zip`. You extract it inside your home folder `/home/ujib7518`, and it fills two folders:

```
/home/ujib7518/
├── public_html/keluarga/          ← PUBLIC (anyone can open these files in a browser)
│   ├── .htaccess                  HTTPS redirect, security headers, noindex, gzip
│   ├── robots.txt                 tells search engines to stay away
│   ├── index.html                 the app
│   ├── sw.js                      service worker (offline)
│   ├── manifest.webmanifest       "install to home screen"
│   ├── kh-private-path.php        tells PHP where the private folder is (prints nothing)
│   ├── setup.php                  ONE-TIME setup page — DELETE after setup
│   ├── api/index.php              the app's data API
│   ├── peneliti/                  researcher dashboard (index.php, peneliti.css)
│   ├── assets/                    css, js, logo and icons
│   └── content/                   texts of the cards (JSON), sample audio and image
│
└── keluarga-private/              ← PRIVATE (outside the web root, never reachable from a browser)
    ├── config.example.php         filled-in template for THIS server
    ├── config.php                 ← YOU create it (step 5)
    ├── keys.php                   ← the setup page creates it (step 6)
    ├── bootstrap.php, api.php     app code
    ├── lib/                       app code
    ├── scripts/                   cron job, fake users, researcher login (terminal versions)
    ├── sql/schema.sql             database tables (you import it in phpMyAdmin)
    └── storage/                   logs and the setup lock file
```

**Exact paths of the secret files:**

| File | Exact path |
|---|---|
| Config (database password, mail password, setup token) | `/home/ujib7518/keluarga-private/config.php` |
| Encryption keys | `/home/ujib7518/keluarga-private/keys.php` |

The public PHP files find the private folder by themselves: `/home/ujib7518/public_html/keluarga/kh-private-path.php` looks two folders up from the web root, which gives `/home/ujib7518/keluarga-private`. No domain name is written anywhere in the code. The only place the address appears is `'app_url' => 'https://keluarga.ujicobaicha.my.id'` in `config.php`.

---

## What you need from the server (check before you start)

| Item | Minimum | Where to check in cPanel |
|---|---|---|
| PHP | **8.1** or newer (8.2 or 8.3 recommended) | **Select PHP Version** (or **MultiPHP Manager**) |
| MariaDB | **10.3** or newer (tested on 10.11). MySQL 5.7+ also works | phpMyAdmin start page → "Server version" |
| PHP extensions | **pdo_mysql** (in some cPanel versions it is called **nd_pdo_mysql**), **pdo**, **mysqlnd**, **sodium**, **mbstring**, **openssl** | **Select PHP Version** → **Extensions** tab |
| HTTPS certificate | valid for keluarga.ujicobaicha.my.id | **SSL/TLS Status** (AutoSSL) |

`json`, `session`, `filter`, `hash` and `ctype` are built into PHP and are always on. The setup page checks all of these for you and shows **OK**, **WARNING** or **PROBLEM** next to each.

---

## Step-by-step

### Step 1 — Choose the PHP version
1. Log in to cPanel.
2. Open **Select PHP Version**. If you don't see it, open **MultiPHP Manager**.
3. Choose **8.2** or **8.3** (8.1 is the minimum) and click **Apply** / **Set as current**.
4. If you are in **Select PHP Version**, open the **Extensions** tab and make sure these are ticked: `pdo`, `pdo_mysql` (or `nd_pdo_mysql`), `mysqlnd`, `sodium`, `mbstring`. Changes save automatically.

> In **MultiPHP Manager**, select the line `keluarga.ujicobaicha.my.id`, pick the version, and click **Apply**. The extensions there are normally already on.

### Step 2 — Create the database and database user
1. cPanel → **MySQL® Databases**.
2. **Create New Database:** type `keluarga` → **Create Database**. The full name becomes **`ujib7518_keluarga`**.
3. **Add New User:** username `kh` (full name **`ujib7518_kh`**). Click **Password Generator**, **copy the password somewhere safe**, then **Create User**.
4. **Add User To Database:** user `ujib7518_kh`, database `ujib7518_keluarga` → **Add** → tick **ALL PRIVILEGES** → **Make Changes**.

### Step 3 — Import schema.sql in phpMyAdmin
1. Download `schema.sql` to your laptop. It was sent together with the zip, and it is also inside the zip at `keluarga-private/sql/schema.sql`.
2. cPanel → **phpMyAdmin**.
3. On the left, click **`ujib7518_keluarga`**.
4. Top menu → **Import** → **Choose File** → select `schema.sql` → scroll down → **Import** (or **Go**).
5. You should see a green success message. On the left you should now see 8 tables and 3 views: `checkins, consents, password_resets, rate_limits, researchers, sessions, support_events, users, research_checkins, research_participants, research_support_events`.

> If phpMyAdmin shows an error about `CREATE VIEW`, the user is missing a privilege. Go back to step 2.4 and make sure **ALL PRIVILEGES** is ticked.

### Step 4 — Upload and unzip the files in File Manager
1. cPanel → **File Manager**. Top-right **Settings** → tick **Show Hidden Files (dotfiles)** → **Save**. (This lets you see `.htaccess`.)
2. On the left, click the **house icon** (your home folder, `/home/ujib7518`). Do **not** go into `public_html`.
3. **Upload** → choose `keluarga-hebat-rumahweb.zip` → wait for 100% → **Go Back to "/home/ujib7518"**.
4. Right-click the zip → **Extract** → the path must be **`/home/ujib7518`** → **Extract File(s)**.
5. Check the result:
   - `/home/ujib7518/public_html/keluarga/` now contains `index.html`, `setup.php`, `.htaccess`, and the folders `api`, `assets`, `content` and `peneliti`.
   - `/home/ujib7518/keluarga-private/` exists and contains `config.example.php`, `lib`, `scripts` and the other private files.
6. If `public_html/keluarga` contained cPanel's default files before (for example a default `index.php` or `default.html`), delete them. Leave `.well-known` and `cgi-bin` alone.

### Step 5 — Create the private config
The private folder `/home/ujib7518/keluarga-private` was created by the unzip in step 4. If it is missing, create it with **+ Folder** in your home folder and extract again.

1. Open `/home/ujib7518/keluarga-private`.
2. Right-click **`config.example.php`** → **Copy** → in the box, change the name at the end to **`/home/ujib7518/keluarga-private/config.php`** → **Copy File(s)**.
3. Right-click **`config.php`** → **Edit** → **Edit**.
4. Change the three lines that contain `>>> CHANGE <<<`:
   - **database password:** the password from step 2.3, between the quotes. Example: `'pass' => 'Xy7…your password…',`
   - **`setup_token`:** invent a temporary secret of at least 16 characters, for example `'setup_token' => 'kopi-pelangi-sepatu-awan-71',`
   - **mailbox password:** see below.
5. **Save Changes**.

**Mail settings (for password-reset emails):**
1. cPanel → **Email Accounts** → **Create** a mailbox `noreply@ujicobaicha.my.id` and note its password.
2. Next to the new mailbox, click **Connect Devices** and look at **"Secure SSL/TLS Settings"**. If the outgoing server or port there differs from `mail.ujicobaicha.my.id` and `465`, change `host` and `port` in `config.php` to match.
3. Put the mailbox password in `'password' => '…'` inside the `'mail'` block.

Everything else in `config.php` is already filled in for this server, including `app_url`, the database name and the database user.

### Step 6 — Generate the encryption key
1. In your browser, open **https://keluarga.ujicobaicha.my.id/setup.php**
2. Enter the **setup token** from step 5 → **Continue**.
3. Read **1. Server checks**. Everything should say **OK**, except "Encryption keys" (PROBLEM, until you do the next step) and "Researcher logins" (WARNING, until step 8 of this list).
4. Under **2. Encryption keys**, click **Create keys.php**.
5. Make a backup: in File Manager, right-click `/home/ujib7518/keluarga-private/keys.php` → **Download**. Keep the file somewhere private. It holds the keys that make the stored emails readable. Never edit, replace or delete it while users exist.

> 5 wrong tokens lock the page for 15 minutes. The page also logs you out after 30 minutes.

### Step 7 — Set file permissions
File Manager → right-click → **Change Permissions**:

| Item | Permission |
|---|---|
| `/home/ujib7518/keluarga-private/config.php` | **600** (only Owner Read + Write ticked) |
| `/home/ujib7518/keluarga-private/keys.php` | **600** (the setup page already sets this; check it) |
| all other files | 644 (the default) |
| all folders | 755 (the default) |

If the site shows an error after you set 600, use **640** instead. Then reload `setup.php`: the line "config.php permissions" shows the current value.

### Step 8 — Load test users, create the researcher login, test email, finish
Still on **setup.php**:
1. **3. Fake test users** → **Load fake test users**. This creates 30 fake users with 6 weeks of check-ins.
2. **4. Researcher login** → username `peneliti1` and a password of at least 14 characters → **Create researcher login**.
   - On your phone, open an authenticator app (Google Authenticator, Microsoft Authenticator, Aegis or 2FAS) → **add account** → **"Enter a setup key"** → type the key shown on the page → time-based → save.
   - **The key is shown only once.** If you lose it, create the login again with the same username; that resets it.
3. **5. Test email** → type your own email address → **Send test email**. You should receive it (check spam too). If it fails, the page shows the reason. Fix `host`, `port` or `password` in `config.php` and try again.
4. **6. Finish** → **Finish setup and lock this page**.

### Step 9 — Clean up (see the list at the bottom)
Delete `setup.php` and the zip, and empty `setup_token`.

### Step 10 — Open the site
- **App:** https://keluarga.ujicobaicha.my.id
  → "Saya sudah punya akun" → `contoh07@contoh.invalid` / `sandi-contoh-123`
- **Researcher dashboard:** https://keluarga.ujicobaicha.my.id/peneliti/
  → `peneliti1`, your password, and the 6-digit code from the authenticator app.

### Step 11 — Cron job (daily cleanup)
cPanel → **Cron Jobs** → Common Settings: **Once Per Day** → Command:
```
/usr/local/bin/php /home/ujib7518/keluarga-private/scripts/cron_cleanup.php >/dev/null 2>&1
```
→ **Add New Cron Job**. To check that it works, temporarily remove `>/dev/null 2>&1` and set your email in **Cron Email**. You should get a line like `sessions=0 resets=0 rate_limits=0`. If instead the email says the PHP version is too old, replace `/usr/local/bin/php` with the path for your PHP version, for example `/opt/alt/php82/usr/bin/php`.

---

## Which steps need a terminal?

| Task | Needs terminal? | Browser-only way |
|---|---|---|
| Choose PHP version, create database and user | No | cPanel screens |
| Import schema.sql | No | phpMyAdmin → Import |
| Upload / unzip | No | File Manager |
| Create config.php | No | File Manager → Copy + Edit |
| **Generate encryption key** | Terminal version: `php scripts/gen_keys.php --write` | **setup.php → Create keys.php** |
| **Load / remove fake users** | Terminal version: `php scripts/seed.php` / `--purge` | **setup.php → Load / Delete fake test users** |
| **Create researcher login** | Terminal version: `php scripts/create_researcher.php peneliti1` | **setup.php → Create researcher login** |
| Test email | — | setup.php → Send test email |
| Daily cleanup | Runs by itself | cPanel → Cron Jobs (no terminal) |

**So you never need a terminal.** If your cPanel has **Terminal** (cPanel → Advanced → Terminal; it usually appears when SSH is included in the plan, but I could not confirm this for your Rumahweb account), it works too. Use these commands:
```bash
cd ~/keluarga-private
php -v                                    # should show 8.1 or higher
php scripts/gen_keys.php --write          # creates keys.php (refuses if it exists)
php scripts/seed.php                      # 30 fake users
php scripts/seed.php --purge              # delete all fake users
php scripts/create_researcher.php peneliti1
```
If `php -v` shows an old version in the Terminal, use the full path, for example `/opt/alt/php82/usr/bin/php scripts/seed.php`.

---

## Fake test users (item 6)
- **Browser:** setup.php → **Load fake test users** (before you press Finish).
- **Terminal:** `php ~/keluarga-private/scripts/seed.php`
- Logins: `contoh01@contoh.invalid` … `contoh30@contoh.invalid`, all with password `sandi-contoh-123`.
  - `contoh07`: last 3 days are "badai", so the support card shows.
  - `contoh13` and `contoh26`: have withdrawn consent (consent screen only, not counted in the dashboard).
- **Need them again after Finish?** In File Manager, delete `/home/ujib7518/keluarga-private/storage/setup.lock`, upload `setup.php` again (it is in the zip), use it, then Finish and delete it again. Or use the Terminal.

---

## Keeping search engines out (item 8)
Already done, nothing to configure:
- `robots.txt` says `Disallow: /`
- every response carries the header `X-Robots-Tag: noindex, nofollow, noarchive` (from `.htaccess` and from PHP)
- the app page has `<meta name="robots" content="noindex, nofollow">`

**Do not turn on cPanel "Directory Privacy" for the whole site.** It adds a browser password prompt (HTTP Basic Auth), but Chrome downloads `manifest.webmanifest` *without* sending that password. The manifest then fails with "401 Unauthorized", so Android will not offer **"Install app" / "Add to Home screen"**. The installed app would also show a password prompt instead of opening offline.

*Optional:* you **can** turn on Directory Privacy for **`public_html/keluarga/peneliti` only**. That adds a second password in front of the researcher dashboard, and the dashboard has no install or offline parts, so nothing breaks.

---

## Go-live checklist for this server (item 9)

Use Chrome on your laptop. **DevTools** opens with F12 or right-click → Inspect; it is built into Chrome, so no installation is needed.

**HTTPS and redirect**
- [ ] Open `http://keluarga.ujicobaicha.my.id` (with **http**). The address bar changes to **https://** and shows the padlock.
- [ ] cPanel → **SSL/TLS Status**: the subdomain has a valid AutoSSL certificate, not self-signed.

**Security headers**
- [ ] DevTools → **Network** → reload → click the first request (the page) → **Headers** → Response Headers include: `content-security-policy`, `strict-transport-security`, `x-content-type-options: nosniff`, `x-frame-options: DENY`, `referrer-policy: no-referrer`, `x-robots-tag: noindex, nofollow, noarchive`.
- [ ] (Optional) https://securityheaders.com → enter the address → expect grade **A**.
- [ ] `https://keluarga.ujicobaicha.my.id/robots.txt` shows `Disallow: /`.
- [ ] `https://keluarga.ujicobaicha.my.id/.htaccess` shows **403 Forbidden**.
- [ ] `https://keluarga.ujicobaicha.my.id/kh-private-path.php` shows an **empty page**.

**gzip compression**
- [ ] DevTools → **Network** → reload → click `app.js` → Response Headers show `content-encoding: gzip` (or `br`). The "Size" column shows about **12 kB** for app.js instead of 44 kB.

**Password reset email (SMTP)**
- [ ] In the app: "Saya sudah punya akun" → "Lupa kata sandi?" → enter an email you registered yourself (fake `contoh…@contoh.invalid` addresses cannot receive mail). The email arrives, the link opens the app, the new password works, and the same link does not work a second time.

**Install to home screen (Android phone)**
- [ ] Open the site in **Chrome** on Android → menu **⋮** → **Install app** (or **Add to Home screen**) → the icon appears on the home screen.
- [ ] Open the app from the icon: it opens full-screen without the address bar.

**Offline mode**
- [ ] Log in in the installed app and wait a few seconds. Then turn on **Airplane mode**.
- [ ] Close the app completely and open it again: it opens and shows "Tidak ada internet. Catatan tetap tersimpan di HP."
- [ ] Do a check-in: "Tersimpan di HP. Akan dikirim saat ada internet."
- [ ] Turn Airplane mode off and reopen the app: the status changes to "Sudah terkirim".

**Researcher login with the authenticator app**
- [ ] `https://keluarga.ujicobaicha.my.id/peneliti/` → username + password + 6-digit code → the dashboard shows numbers.
- [ ] A wrong code is refused. Using the same code a second time (log out and in again within 30 seconds) is refused.
- [ ] **Unduh CSV** downloads a file with exactly the columns `handle,date,weather,checkmark`.

**Setup removed**
- [ ] `https://keluarga.ujicobaicha.my.id/setup.php` shows **404 Not Found**.

---

## Delete after setup (item 10)

| Delete | Where | Why |
|---|---|---|
| **`setup.php`** | `/home/ujib7518/public_html/keluarga/setup.php` | One-time page. It is locked after "Finish", but delete it anyway |
| **The uploaded zip** | `/home/ujib7518/keluarga-hebat-rumahweb.zip` | Not needed; contains a copy of setup.php |
| **`setup_token` value** | in `config.php`: change to `'setup_token' => '',` | Disables setup even if setup.php is uploaded again |
| `setup-attempts.json` (if present) | `/home/ujib7518/keluarga-private/storage/` | Leftover counter of wrong tokens |
| `schema.sql` on your laptop | your Downloads folder | Not secret, just tidy |

**Do NOT delete:** `kh-private-path.php`, `keys.php`, `config.php`, `storage/setup.lock`, or anything else in `keluarga-private`. The scripts there are not reachable from the web, and the cron job needs `scripts/cron_cleanup.php`.

**Before any real study:** use a **new database**, never this test one. Don't load fake users there, and replace every placeholder text (see README section 3.8).

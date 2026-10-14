#!/usr/bin/env python3
"""Build the cPanel upload zip for the Rumahweb TEST server.

    python3 deploy/build_zip.py

Creates deploy/keluarga-hebat-rumahweb.zip. Extract it INSIDE /home/ujib7518 and it creates:
    public_html/keluarga/   → public web root of keluarga.ujicobaicha.my.id
    keluarga-private/       → private folder (code, config, keys) outside the web root
Only whitelisted files are packed: never config.php, keys.php, logs or dev tools.
"""
import os, sys, time, zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'deploy', 'keluarga-hebat-rumahweb.zip')
PUBLIC_PREFIX = 'public_html/keluarga/'
PRIVATE_PREFIX = 'keluarga-private/'

PUBLIC_FILES = [
    '.htaccess', 'robots.txt', 'index.html', 'sw.js', 'manifest.webmanifest',
    'kh-private-path.php', 'setup.php',
    'api/index.php',
    'peneliti/index.php', 'peneliti/peneliti.css',
    'assets/css/app.css',
    'assets/js/core.js', 'assets/js/icons.js', 'assets/js/charts.js', 'assets/js/content.js', 'assets/js/app.js',
    'assets/img/logo.svg', 'assets/img/icon-192.png', 'assets/img/icon-512.png',
    'assets/img/icon-maskable-512.png', 'assets/img/apple-touch-icon.png',
    'content/persetujuan.json', 'content/pertolongan.json', 'content/belajar.json',
    'content/img/contoh-panduan.svg', 'content/audio/contoh-panduan.wav',
]
PRIVATE_FILES = [
    ('app/.htaccess', '.htaccess'),
    ('app/bootstrap.php', 'bootstrap.php'),
    ('app/api.php', 'api.php'),
    ('deploy/rumahweb/config.example.php', 'config.example.php'),
    ('app/sql/schema.sql', 'sql/schema.sql'),
    ('app/storage/.htaccess', 'storage/.htaccess'),
] + [(f'app/lib/{f}', f'lib/{f}') for f in
     ['db.php', 'http.php', 'crypto.php', 'validate.php', 'ratelimit.php', 'auth.php', 'mailer.php', 'totp.php', 'setup.php']] \
  + [(f'app/scripts/{f}', f'scripts/{f}') for f in
     ['cron_cleanup.php', 'seed.php', 'create_researcher.php', 'gen_keys.php']]

def add(z, src, arc, seen_dirs):
    path = os.path.join(ROOT, src)
    if not os.path.isfile(path):
        sys.exit(f'missing: {src}')
    parts = arc.split('/')[:-1]
    for i in range(1, len(parts) + 1):          # explicit folder entries (cPanel extracts them cleanly)
        d = '/'.join(parts[:i]) + '/'
        if d not in seen_dirs:
            info = zipfile.ZipInfo(d, time.localtime()[:6]); info.external_attr = (0o40755 << 16) | 0x10
            z.writestr(info, ''); seen_dirs.add(d)
    info = zipfile.ZipInfo(arc, time.localtime(os.path.getmtime(path))[:6])
    info.external_attr = 0o100644 << 16
    info.compress_type = zipfile.ZIP_DEFLATED
    with open(path, 'rb') as fh:
        z.writestr(info, fh.read())

with zipfile.ZipFile(OUT, 'w') as z:
    seen = set()
    for f in PUBLIC_FILES:
        add(z, 'public_html/' + f, PUBLIC_PREFIX + f, seen)
    for src, dst in PRIVATE_FILES:
        add(z, src, PRIVATE_PREFIX + dst, seen)
print(OUT, os.path.getsize(OUT), 'bytes')

<?php
/**
 * KELUARGA HEBAT — config for the TEST server keluarga.ujicobaicha.my.id (Rumahweb cPanel).
 *
 * HOW TO USE (File Manager):
 *   1. In /home/ujib7518/keluarga-private, right-click this file → Copy → name it  config.php
 *   2. Edit config.php: replace every value marked  >>> CHANGE <<<
 *   3. Do NOT put encryption keys here. setup.php creates keys.php next to this file.
 *
 * Full path of the real file: /home/ujib7518/keluarga-private/config.php
 */
return [
    // The ONE base URL of the app. No trailing slash. Used for password-reset links and the
    // same-origin security check. Everything else in the app uses relative paths.
    'app_url' => 'https://keluarga.ujicobaicha.my.id',

    // Refuse the API without HTTPS. Keep true on the server.
    'require_https' => true,

    'timezone' => 'Asia/Jakarta',
    'client_utc_offsets' => [7, 9],

    // Database created in cPanel → "MySQL® Databases". cPanel adds the "ujib7518_" prefix.
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=ujib7518_keluarga;charset=utf8mb4',
        'user' => 'ujib7518_kh',
        'pass' => '>>> CHANGE: the database user password <<<',
    ],
    'db_research' => null,

    // Not used when keys.php exists (setup.php creates it). Leave as is.
    'keys' => [
        'email_enc'   => '',
        'email_index' => '',
        'app_secret'  => '',
    ],

    // Temporary password for the one-time setup page https://keluarga.ujicobaicha.my.id/setup.php
    // Use at least 16 characters, e.g. four random words: 'kopi-pelangi-sepatu-awan-71'.
    // After setup: set it back to '' and delete setup.php.
    'setup_token' => '>>> CHANGE: at least 16 characters <<<',

    'session_days' => 365,

    // Leave null on Rumahweb unless you put the site behind Cloudflare
    // (then use 'HTTP_CF_CONNECTING_IP').
    'trusted_ip_header' => null,

    // Must match "version" in public_html/keluarga/content/persetujuan.json
    'consent_version' => '2026-10-draft-1',

    // Password-reset email. Create the mailbox first in cPanel → "Email Accounts", then check
    // the exact server name/port in that mailbox's "Connect Devices" page.
    'mail' => [
        'transport'  => 'smtp',
        'host'       => 'mail.ujicobaicha.my.id',
        'port'       => 465,
        'encryption' => 'ssl',
        'username'   => 'noreply@ujicobaicha.my.id',
        'password'   => '>>> CHANGE: the mailbox password <<<',
        'from_email' => 'noreply@ujicobaicha.my.id',
        'from_name'  => 'Keluarga Hebat (UJI COBA)',
        'timeout'    => 15,
    ],

    'researcher' => [
        'session_minutes' => 30,
        'require_totp'    => true,
    ],

    // Shown in the app (no secrets here). Placeholders for the test site.
    'public' => [
        'checkmark_question' => 'Hari ini saya sempat istirahat sebentar',
        'whatsapp_group_url' => 'https://chat.whatsapp.com/CONTOH_GANTI_TAUTAN_INI',
        'emergency_numbers' => [
            ['label' => 'Ambulans / Gawat Darurat', 'number' => '119'],
            ['label' => 'Panggilan Darurat Nasional', 'number' => '112'],
        ],
        'support_card' => [
            'title'         => 'Kamu tidak sendirian',
            'text'          => '[CONTOH — menunggu persetujuan komite etik] Beberapa hari ini terasa berat. Jika kamu ingin bicara dengan seseorang, tim kami siap mendengarkan.',
            'contact_label' => 'Hubungi pendamping',
            'contact_url'   => 'https://wa.me/620000000000',
        ],
        'content_base' => 'content/',
    ],
];

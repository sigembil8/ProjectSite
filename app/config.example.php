<?php
/**
 * KELUARGA HEBAT — contoh konfigurasi.
 *
 * Salin menjadi config.php (di folder yang sama), lalu isi nilainya.
 *   cp config.example.php config.php
 *   php scripts/gen_keys.php      # membuat kunci acak untuk bagian 'keys'
 *
 * config.php TIDAK boleh masuk ke Git dan tidak boleh berada di dalam public_html.
 */
return [
    // Alamat lengkap aplikasi, tanpa garis miring di akhir. Dipakai untuk tautan reset & cek Origin.
    'app_url' => 'https://keluargahebat.example.id',

    // Tolak permintaan API tanpa HTTPS. Hanya set false untuk uji lokal (php -S).
    'require_https' => true,

    // Zona waktu server (dashboard & "hari ini" di server).
    'timezone' => 'Asia/Jakarta',

    // Rentang zona waktu pengguna (jam dari UTC). Indonesia: WIB +7 s.d. WIT +9.
    // Dipakai untuk memeriksa bahwa check-in dibuat/diubah pada hari yang sama (sebelum tengah malam).
    'client_utc_offsets' => [7, 9],

    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=CPANELUSER_kh;charset=utf8mb4',
        'user' => 'CPANELUSER_khapp',
        'pass' => 'GANTI_DENGAN_SANDI_DB',
    ],

    // Opsional (disarankan): pengguna DB terpisah untuk dashboard peneliti yang hanya punya
    // SELECT pada view research_* serta SELECT/UPDATE pada researchers & rate_limits.
    // null = pakai 'db' di atas.
    'db_research' => null,

    // Kunci rahasia (base64, 32 byte). Buat dengan: php scripts/gen_keys.php
    // JANGAN diganti setelah ada pengguna: email & rahasia TOTP tidak bisa dibaca lagi.
    'keys' => [
        'email_enc'   => 'GANTI',  // enkripsi email (libsodium secretbox)
        'email_index' => 'GANTI',  // HMAC indeks buta untuk mencari email saat login
        'app_secret'  => 'GANTI',  // enkripsi rahasia TOTP peneliti, dll.
    ],

    // Lama HP tetap masuk (hari). Chrome membatasi cookie maksimal 400 hari.
    'session_days' => 365,

    // Jika situs di belakang Cloudflare/proksi, isi header IP asli, mis. 'HTTP_CF_CONNECTING_IP'.
    'trusted_ip_header' => null,

    // Versi teks persetujuan yang berlaku. Harus sama dengan "version" di public_html/content/persetujuan.json.
    // Jika diubah, semua pengguna diminta menyetujui ulang.
    'consent_version' => '2026-10-draft-1',

    // Email untuk reset kata sandi.
    'mail' => [
        'transport'  => 'smtp',          // 'smtp' atau 'log' (uji lokal: ditulis ke storage/mail.log)
        'host'       => 'mail.keluargahebat.example.id',
        'port'       => 465,
        'encryption' => 'ssl',           // 'ssl' (port 465) atau 'tls' (STARTTLS, port 587)
        'username'   => 'noreply@keluargahebat.example.id',
        'password'   => 'GANTI',
        'from_email' => 'noreply@keluargahebat.example.id',
        'from_name'  => 'Keluarga Hebat',
        'timeout'    => 15,
    ],

    // Dashboard peneliti.
    'researcher' => [
        'session_minutes' => 30,
        // Wajib kode TOTP (Google Authenticator, Aegis, dll.). Jangan dimatikan di produksi.
        'require_totp' => true,
    ],

    // ------------------------------------------------------------------
    // Nilai yang DIKIRIM ke aplikasi di HP (tidak boleh berisi rahasia).
    // ------------------------------------------------------------------
    'public' => [
        // Pertanyaan centang harian.
        'checkmark_question' => 'Hari ini saya sempat istirahat sebentar',

        // Tautan grup WhatsApp untuk kartu "Teman Curhat" (CONTOH — ganti dengan tautan asli).
        'whatsapp_group_url' => 'https://chat.whatsapp.com/CONTOH_GANTI_TAUTAN_INI',

        // Kotak nomor darurat di kartu "Anak Kejang/Kaku". PERIKSA KEMBALI sebelum rilis.
        'emergency_numbers' => [
            ['label' => 'Ambulans / Gawat Darurat', 'number' => '119'],
            ['label' => 'Panggilan Darurat Nasional', 'number' => '112'],
        ],

        // Kartu dukungan bila 3 catatan terakhir "badai".
        // CONTOH — teks dan kontak harus disetujui komite etik penelitian.
        'support_card' => [
            'title'         => 'Kamu tidak sendirian',
            'text'          => '[CONTOH — menunggu persetujuan komite etik] Beberapa hari ini terasa berat. Jika kamu ingin bicara dengan seseorang, tim kami siap mendengarkan.',
            'contact_label' => 'Hubungi pendamping',
            'contact_url'   => 'https://wa.me/620000000000',
        ],

        // Lokasi konten JSON. Nanti bisa diarahkan ke WordPress REST API (lihat README).
        'content_base' => 'content/',
    ],
];

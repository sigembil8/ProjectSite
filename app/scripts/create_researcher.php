<?php
declare(strict_types=1);
/*
 * Buat / atur ulang akun peneliti (dengan TOTP).
 *   php scripts/create_researcher.php NAMA_PENGGUNA
 * Sandi diminta lewat terminal (tidak terlihat). Rahasia TOTP dicetak sekali:
 * masukkan ke aplikasi authenticator (Google Authenticator, Aegis, 2FAS, dll.).
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../bootstrap.php';

$username = $argv[1] ?? '';
if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
    fwrite(STDERR, "Pakai: php scripts/create_researcher.php nama_pengguna (huruf kecil/angka)\n");
    exit(1);
}
$password = getenv('KH_RESEARCHER_PASSWORD') ?: null;
if ($password === null) {
    echo "Sandi baru (min. 14 karakter): ";
    system('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n";
}
if (mb_strlen($password) < 14) {
    fwrite(STDERR, "Sandi terlalu pendek (min. 14 karakter).\n");
    exit(1);
}
$secret = random_bytes(20);
$b32 = kh_base32_encode($secret);
kh_q(
    'INSERT INTO researchers (username, password_hash, totp_secret_enc, totp_last_step) VALUES (?, ?, ?, 0)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), totp_secret_enc = VALUES(totp_secret_enc), totp_last_step = 0',
    [$username, password_hash($password, PASSWORD_DEFAULT), kh_encrypt($secret, 'app_secret')]
);
$issuer = rawurlencode('Keluarga Hebat');
echo "Akun peneliti '$username' siap.\n\n";
echo "Rahasia TOTP (ketik manual di aplikasi authenticator):\n  $b32\n\n";
echo "Atau URI otpauth (bisa diubah menjadi QR secara offline):\n";
echo "  otpauth://totp/$issuer:" . rawurlencode($username) . "?secret=$b32&issuer=$issuer&digits=6&period=30\n";

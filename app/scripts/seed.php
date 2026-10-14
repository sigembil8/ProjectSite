<?php
declare(strict_types=1);
/*
 * Data contoh untuk uji coba (JANGAN dijalankan di database penelitian sungguhan).
 *
 *   php scripts/seed.php                 # 30 pengguna, 42 hari terakhir
 *   php scripts/seed.php --users=50 --days=60
 *   php scripts/seed.php --purge         # hapus semua data contoh (is_seed = 1)
 *
 * Semua pengguna contoh: email contohNN@contoh.invalid, sandi "sandi-contoh-123",
 * nama samaran diawali "Contoh". Polanya acak tapi bisa diulang (seed tetap).
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../bootstrap.php';

$opts = getopt('', ['users::', 'days::', 'purge']);
if (isset($opts['purge'])) {
    $n = kh_q('DELETE FROM users WHERE is_seed = 1')->rowCount();
    echo "Dihapus: $n pengguna contoh (beserta seluruh datanya).\n";
    exit(0);
}
$nUsers = max(1, (int) ($opts['users'] ?? 30));
$nDays = max(7, min(120, (int) ($opts['days'] ?? 42)));

mt_srand(20261014);
$names = ['Melati', 'Mawar', 'Kenanga', 'Anggrek', 'Cempaka', 'Dahlia', 'Kamboja', 'Teratai', 'Seruni', 'Flamboyan'];
$version = (string) kh_cfg('consent_version');
$hash = password_hash('sandi-contoh-123', PASSWORD_DEFAULT);
$db = kh_db();
$users = 0;
$checkins = 0;

for ($i = 1; $i <= $nUsers; $i++) {
    $email = sprintf('contoh%02d@contoh.invalid', $i);
    $bidx = kh_email_index($email);
    if (kh_q('SELECT 1 FROM users WHERE email_bidx = ?', [$bidx])->fetchColumn()) {
        continue; // sudah ada
    }
    $handle = sprintf('Contoh %s %02d', $names[$i % count($names)], $i);
    // Sebagian kecil menarik persetujuan → tidak tampil di dashboard.
    $status = ($i % 13 === 0) ? 'withdrawn' : 'given';
    $joinedDaysAgo = $i === 7 ? $nDays : mt_rand(0, $nDays);
    $joined = date('Y-m-d H:i:s', strtotime("-$joinedDaysAgo days 09:00"));

    $db->beginTransaction();
    kh_q(
        'INSERT INTO users (handle, email_bidx, email_enc, password_hash, consent_status, consent_version, is_seed, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$handle, $bidx, kh_encrypt($email, 'email_enc'), $hash, $status, $version, $joined]
    );
    $uid = (int) $db->lastInsertId();
    kh_q('INSERT INTO consents (user_id, version, action, created_at) VALUES (?, ?, \'given\', ?)', [$uid, $version, $joined]);
    if ($status === 'withdrawn') {
        kh_q('INSERT INTO consents (user_id, version, action) VALUES (?, ?, \'withdrawn\')', [$uid, $version]);
    }

    // Keaktifan berbeda per orang; sebagian berhenti setelah beberapa minggu (untuk uji retensi).
    $activity = mt_rand(35, 95) / 100;
    $stopAfter = mt_rand(0, 3) === 0 ? mt_rand(5, 20) : 999;
    $mood = mt_rand(0, 3); // kecenderungan cuaca
    $stmt = $db->prepare(
        'INSERT INTO checkins (uuid, user_id, checkin_date, weather, rested, client_updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    for ($d = $joinedDaysAgo, $k = 0; $d >= 0; $d--, $k++) {
        $forceStorm = ($i === 7 && $d <= 2);
        if (!$forceStorm && ($k > $stopAfter || mt_rand(1, 100) > $activity * 100)) {
            continue;
        }
        $date = date('Y-m-d', strtotime("-$d days"));
        $w = max(0, min(3, $mood + mt_rand(-1, 1) + (mt_rand(0, 9) === 0 ? 1 : 0)));
        // Pengguna ke-7 sengaja punya 3 "badai" terakhir untuk menguji kartu dukungan.
        if ($forceStorm) {
            $w = 3;
        }
        $ts = strtotime("$date 20:00:00") * 1000;
        $stmt->execute([kh_uuid4(), $uid, $date, KH_WEATHER[$w], mt_rand(0, 1), $ts, "$date 20:00:00"]);
        $checkins++;
    }
    $db->commit();
    $users++;
}
echo "Dibuat: $users pengguna contoh, $checkins check-in ($nDays hari).\n";
echo "Masuk dengan: contoh07@contoh.invalid / sandi-contoh-123 (3 catatan terakhir 'badai').\n";

function kh_uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

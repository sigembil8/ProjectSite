<?php
declare(strict_types=1);

/*
 * One-time setup helpers, shared by the CLI scripts (scripts/*.php) and the
 * browser setup page (public setup.php). Not loaded by the app itself.
 */

/** Create keys.php with three random 32-byte keys. Refuses to overwrite an existing file. */
function kh_keys_write(): string
{
    $file = KH_APP_DIR . '/keys.php';
    if (is_file($file)) {
        throw new RuntimeException('keys.php already exists. It must never be replaced once users exist.');
    }
    $lines = ["<?php", "// Encryption keys. Created " . date('c') . ". NEVER edit, replace or delete:", "// encrypted emails and researcher TOTP secrets cannot be read without these keys.", "// Keep a backup copy somewhere safe (e.g. a password manager).", "return ["];
    foreach (['email_enc', 'email_index', 'app_secret'] as $k) {
        $lines[] = "    '$k' => '" . base64_encode(random_bytes(32)) . "',";
    }
    $lines[] = "];";
    $tmp = $file . '.tmp';
    if (file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false || !rename($tmp, $file)) {
        throw new RuntimeException('Could not write keys.php. Is the private folder writable?');
    }
    @chmod($file, 0600);
    $GLOBALS['KH_CONFIG']['keys'] = require $file;
    return $file;
}

/** Remove all fake users (is_seed = 1) and, through ON DELETE CASCADE, all their data. */
function kh_seed_purge(): int
{
    return kh_q('DELETE FROM users WHERE is_seed = 1')->rowCount();
}

/**
 * Create fake users with check-ins. Email contohNN@contoh.invalid, password "sandi-contoh-123".
 * User 07 always has 3 "badai" days at the end (tests the support card).
 * Users 13 and 26 have withdrawn consent (they do not appear in the dashboard).
 * @return array{users:int, checkins:int}
 */
function kh_seed(int $nUsers = 30, int $nDays = 42): array
{
    $nUsers = max(1, min(200, $nUsers));
    $nDays = max(7, min(120, $nDays));
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
            continue; // already there
        }
        $handle = sprintf('Contoh %s %02d', $names[$i % count($names)], $i);
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

        $activity = mt_rand(35, 95) / 100;
        $stopAfter = mt_rand(0, 3) === 0 ? mt_rand(5, 20) : 999;
        $mood = mt_rand(0, 3);
        $stmt = $db->prepare(
            'INSERT INTO checkins (uuid, user_id, checkin_date, weather, rested, client_updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        for ($d = $joinedDaysAgo, $k = 0; $d >= 0; $d--, $k++) {
            $forceStorm = ($i === 7 && $d <= 2);
            if (!$forceStorm && ($k > $stopAfter || mt_rand(1, 100) > $activity * 100)) {
                continue;
            }
            $date = date('Y-m-d', strtotime("-$d days"));
            $w = $forceStorm ? 3 : max(0, min(3, $mood + mt_rand(-1, 1) + (mt_rand(0, 9) === 0 ? 1 : 0)));
            $ts = strtotime("$date 20:00:00") * 1000;
            $stmt->execute([kh_uuid4(), $uid, $date, KH_WEATHER[$w], mt_rand(0, 1), $ts, "$date 20:00:00"]);
            $checkins++;
        }
        $db->commit();
        $users++;
    }
    return ['users' => $users, 'checkins' => $checkins];
}

function kh_uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * Create or reset a researcher login. Returns the new TOTP secret (base32) and otpauth URI.
 * @return array{secret:string, uri:string}
 */
function kh_researcher_upsert(string $username, string $password): array
{
    if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
        throw new InvalidArgumentException('Username: 3-64 lowercase letters, numbers, dot, dash or underscore.');
    }
    if (mb_strlen($password) < 14) {
        throw new InvalidArgumentException('Password must be at least 14 characters.');
    }
    $secret = random_bytes(20);
    $b32 = kh_base32_encode($secret);
    kh_q(
        'INSERT INTO researchers (username, password_hash, totp_secret_enc, totp_last_step) VALUES (?, ?, ?, 0)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), totp_secret_enc = VALUES(totp_secret_enc), totp_last_step = 0',
        [$username, password_hash($password, PASSWORD_DEFAULT), kh_encrypt($secret, 'app_secret')]
    );
    $issuer = rawurlencode('Keluarga Hebat');
    return [
        'secret' => $b32,
        'uri'    => "otpauth://totp/$issuer:" . rawurlencode($username) . "?secret=$b32&issuer=$issuer&digits=6&period=30",
    ];
}

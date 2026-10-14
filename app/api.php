<?php
declare(strict_types=1);

/*
 * API JSON. Satu titik masuk: public_html/api/index.php?r=<rute>
 *
 *   GET  session          → token CSRF, pengguna (atau null), konfigurasi publik
 *   POST register         → {email, password, handle, consent:true, consent_version}
 *   POST login            → {email, password}
 *   POST logout
 *   POST password-forgot  → {email}
 *   POST password-reset   → {token, password}
 *   GET  checkins         → ?since=YYYY-MM-DD  (hanya baris milik pengguna)
 *   POST checkins-sync    → {items:[{uuid,date,weather,rested,updated_at}]}
 *   POST consent          → {action:'give'|'withdraw', version}
 *   POST support-event    → {kind:'card_shown'|'contact_tapped'}
 *   POST account-delete   → {password}
 *
 * Setiap rute yang menyentuh data pribadi memakai user_id dari sesi, tidak pernah dari input.
 */

function kh_api_dispatch(): never
{
    kh_security_headers('api');
    try {
        kh_require_https();
        $routes = [
            'GET session'          => 'kh_h_session',
            'POST register'        => 'kh_h_register',
            'POST login'           => 'kh_h_login',
            'POST logout'          => 'kh_h_logout',
            'POST password-forgot' => 'kh_h_password_forgot',
            'POST password-reset'  => 'kh_h_password_reset',
            'GET checkins'         => 'kh_h_checkins_list',
            'POST checkins-sync'   => 'kh_h_checkins_sync',
            'POST consent'         => 'kh_h_consent',
            'POST support-event'   => 'kh_h_support_event',
            'POST account-delete'  => 'kh_h_account_delete',
        ];
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $route = is_string($_GET['r'] ?? null) ? $_GET['r'] : '';
        $handler = $routes[$method . ' ' . $route] ?? null;
        if ($handler === null) {
            kh_error('not_found', 404);
        }
        if ($method === 'POST') {
            kh_csrf_check();
        }
        $handler();
    } catch (Throwable $e) {
        error_log('KH API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        kh_error('server_error', 500, 'Maaf, ada gangguan. Coba lagi nanti.');
    }
}

function kh_h_session(): never
{
    $user = kh_current_user();
    kh_json([
        'ok'           => true,
        'csrf'         => kh_csrf_token(),
        'user'         => $user ? kh_public_user($user) : null,
        'server_today' => date('Y-m-d'),
        'consent_version' => (string) kh_cfg('consent_version'),
        'config'       => kh_cfg('public', []),
    ]);
}

function kh_h_register(): never
{
    $ip = kh_client_ip();
    kh_rate_limit_or_fail('register-ip', $ip, 10, 3600);
    $b = kh_body();
    $email = $b['email'] ?? null;
    $handle = is_string($b['handle'] ?? null) ? kh_clean_handle($b['handle']) : null;
    $password = $b['password'] ?? null;

    $errors = [];
    if (!kh_valid_email($email)) {
        $errors['email'] = 'Email belum benar.';
    }
    if (!kh_valid_handle($handle)) {
        $errors['handle'] = 'Nama samaran 3–24 huruf atau angka.';
    }
    if (!kh_valid_password($password)) {
        $errors['password'] = 'Kata sandi minimal 8 karakter.';
    }
    if (($b['consent'] ?? false) !== true || ($b['consent_version'] ?? '') !== (string) kh_cfg('consent_version')) {
        $errors['consent'] = 'Persetujuan diperlukan.';
    }
    if ($errors) {
        kh_error('invalid', 422, 'Periksa isian.', ['fields' => $errors]);
    }

    $bidx = kh_email_index($email);
    $db = kh_db();
    if (kh_q('SELECT 1 FROM users WHERE email_bidx = ?', [$bidx])->fetchColumn()) {
        kh_error('email_taken', 409, 'Email ini sudah terdaftar. Silakan masuk.', ['fields' => ['email' => 'Email sudah terdaftar.']]);
    }
    if (kh_q('SELECT 1 FROM users WHERE handle = ?', [$handle])->fetchColumn()) {
        kh_error('handle_taken', 409, 'Nama samaran sudah dipakai.', ['fields' => ['handle' => 'Nama samaran sudah dipakai. Coba yang lain.']]);
    }

    $version = (string) kh_cfg('consent_version');
    $db->beginTransaction();
    try {
        kh_q(
            'INSERT INTO users (handle, email_bidx, email_enc, password_hash, consent_status, consent_version) VALUES (?, ?, ?, ?, \'given\', ?)',
            [$handle, $bidx, kh_encrypt(kh_email_normalize($email), 'email_enc'), password_hash($password, PASSWORD_DEFAULT), $version]
        );
        $uid = (int) $db->lastInsertId();
        kh_q('INSERT INTO consents (user_id, version, action) VALUES (?, ?, \'given\')', [$uid, $version]);
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->getCode() === '23000') { // balapan dengan pendaftaran lain
            kh_error('taken', 409, 'Email atau nama samaran sudah dipakai.');
        }
        throw $e;
    }
    kh_session_create($uid);
    kh_json(['ok' => true, 'user' => kh_public_user(kh_user_by_id($uid))]);
}

function kh_user_by_id(int $id): array
{
    $u = kh_q('SELECT id, handle, consent_status, consent_version FROM users WHERE id = ?', [$id])->fetch();
    $u['id'] = (int) $u['id'];
    return $u;
}

function kh_h_login(): never
{
    $ip = kh_client_ip();
    kh_rate_limit_or_fail('login-ip', $ip, 30, 900);
    $b = kh_body();
    $email = $b['email'] ?? '';
    $password = $b['password'] ?? '';
    if (!kh_valid_email($email) || !is_string($password) || $password === '' || strlen($password) > 200) {
        kh_error('invalid_login', 401, 'Email atau kata sandi belum cocok.');
    }
    $bidx = kh_email_index($email);
    kh_rate_limit_or_fail('login-email', $bidx, 8, 900);

    $u = kh_q('SELECT id, password_hash FROM users WHERE email_bidx = ?', [$bidx])->fetch();
    $ok = password_verify($password, $u['password_hash'] ?? kh_dummy_hash());
    if (!$u || !$ok) {
        kh_error('invalid_login', 401, 'Email atau kata sandi belum cocok.');
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        kh_q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    kh_rate_reset('login-email', $bidx);
    kh_session_create((int) $u['id']);
    kh_json(['ok' => true, 'user' => kh_public_user(kh_user_by_id((int) $u['id']))]);
}

function kh_h_logout(): never
{
    kh_session_destroy();
    kh_json(['ok' => true]);
}

function kh_h_password_forgot(): never
{
    $ip = kh_client_ip();
    kh_rate_limit_or_fail('forgot-ip', $ip, 5, 3600);
    $b = kh_body();
    $email = $b['email'] ?? '';
    // Jawaban selalu sama agar tidak bisa dipakai menebak email terdaftar.
    $reply = ['ok' => true, 'message' => 'Jika email terdaftar, tautan untuk membuat kata sandi baru sudah dikirim.'];
    if (!kh_valid_email($email)) {
        kh_json($reply);
    }
    $bidx = kh_email_index($email);
    if (!kh_rate_hit('forgot-email', $bidx, 3, 3600)) {
        kh_json($reply);
    }
    $uid = kh_q('SELECT id FROM users WHERE email_bidx = ?', [$bidx])->fetchColumn();
    if ($uid) {
        $token = kh_token(32);
        kh_q('DELETE FROM password_resets WHERE user_id = ?', [$uid]);
        kh_q(
            'INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
            [hash('sha256', $token), $uid]
        );
        $link = rtrim((string) kh_cfg('app_url'), '/') . '/?reset=' . $token;
        $body = "Halo,\n\nKami menerima permintaan untuk membuat kata sandi baru di aplikasi Keluarga Hebat.\n\n"
              . "Buka tautan ini (berlaku 1 jam):\n$link\n\n"
              . "Jika kamu tidak meminta ini, abaikan saja email ini. Kata sandimu tetap aman.\n\nSalam hangat,\nKeluarga Hebat";
        kh_mail_send(kh_email_normalize($email), 'Buat kata sandi baru — Keluarga Hebat', $body);
    }
    kh_json($reply);
}

function kh_h_password_reset(): never
{
    kh_rate_limit_or_fail('reset-ip', kh_client_ip(), 10, 3600);
    $b = kh_body();
    $token = $b['token'] ?? '';
    $password = $b['password'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        kh_error('bad_token', 400, 'Tautan tidak berlaku. Minta tautan baru.');
    }
    if (!kh_valid_password($password)) {
        kh_error('invalid', 422, 'Kata sandi minimal 8 karakter.', ['fields' => ['password' => 'Kata sandi minimal 8 karakter.']]);
    }
    $db = kh_db();
    $db->beginTransaction();
    $row = kh_q(
        'SELECT user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE',
        [hash('sha256', $token)]
    )->fetch();
    if (!$row) {
        $db->rollBack();
        kh_error('bad_token', 400, 'Tautan tidak berlaku. Minta tautan baru.');
    }
    $uid = (int) $row['user_id'];
    kh_q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $uid]);
    kh_q('UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?', [hash('sha256', $token)]);
    kh_q('DELETE FROM sessions WHERE user_id = ?', [$uid]); // keluarkan semua HP lain
    $db->commit();
    kh_session_create($uid);
    kh_json(['ok' => true, 'user' => kh_public_user(kh_user_by_id($uid))]);
}

function kh_h_checkins_list(): never
{
    $u = kh_require_user();
    $since = $_GET['since'] ?? '';
    $min = date('Y-m-d', strtotime('-180 days'));
    if (!kh_valid_date($since) || $since < $min) {
        $since = $min;
    }
    $rows = kh_q(
        'SELECT uuid, checkin_date AS date, weather, rested, client_updated_at AS updated_at
         FROM checkins WHERE user_id = ? AND checkin_date >= ? ORDER BY checkin_date',
        [$u['id'], $since]
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['rested'] = (int) $r['rested'];
        $r['updated_at'] = (int) $r['updated_at'];
    }
    kh_json(['ok' => true, 'items' => $rows]);
}

/**
 * Sinkronisasi idempoten. Setiap item punya uuid dari HP; mengirim ulang item yang sama
 * tidak membuat baris baru. Konflik pada tanggal yang sama: yang updated_at-nya lebih baru menang.
 */
function kh_h_checkins_sync(): never
{
    $u = kh_require_consented_user();
    kh_rate_limit_or_fail('sync-user', (string) $u['id'], 120, 3600);
    $items = kh_body()['items'] ?? null;
    if (!is_array($items) || count($items) > 62) {
        kh_error('invalid', 422, 'Data tidak valid.');
    }
    $nowMs = (int) (microtime(true) * 1000);
    $minDate = date('Y-m-d', strtotime('-31 days'));
    $maxDate = date('Y-m-d', strtotime('+1 day'));
    $db = kh_db();
    $results = [];

    foreach ($items as $it) {
        $uuid = $it['uuid'] ?? null;
        $date = $it['date'] ?? null;
        $weather = $it['weather'] ?? null;
        $rested = $it['rested'] ?? null;
        $ts = $it['updated_at'] ?? null;
        $res = ['uuid' => is_string($uuid) ? substr($uuid, 0, 36) : null, 'date' => is_string($date) ? substr($date, 0, 10) : null];

        if (!kh_valid_uuid($uuid) || !kh_valid_date($date) || !in_array($weather, KH_WEATHER, true)
            || !in_array($rested, [0, 1, true, false], true) || !is_int($ts)) {
            $results[] = $res + ['status' => 'rejected', 'reason' => 'invalid'];
            continue;
        }
        if ($date < $minDate || $date > $maxDate || $ts > $nowMs + 15 * 60 * 1000 || !kh_ms_within_local_day($ts, $date)) {
            // Hanya boleh dibuat/diubah pada harinya (sampai tengah malam).
            $results[] = $res + ['status' => 'rejected', 'reason' => 'out_of_window'];
            continue;
        }
        $rested = (int) (bool) $rested;

        $db->beginTransaction();
        try {
            $row = kh_q(
                'SELECT id, uuid, weather, rested, client_updated_at FROM checkins WHERE user_id = ? AND checkin_date = ? FOR UPDATE',
                [$u['id'], $date]
            )->fetch();
            if (!$row) {
                $owner = kh_q('SELECT user_id FROM checkins WHERE uuid = ?', [$uuid])->fetchColumn();
                if ($owner !== false) {
                    // uuid sudah dipakai untuk tanggal/pengguna lain → tolak, jangan sentuh baris itu.
                    $db->rollBack();
                    $results[] = $res + ['status' => 'rejected', 'reason' => 'uuid_conflict'];
                    continue;
                }
                kh_q(
                    'INSERT INTO checkins (uuid, user_id, checkin_date, weather, rested, client_updated_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$uuid, $u['id'], $date, $weather, $rested, $ts]
                );
                $canon = ['uuid' => $uuid, 'weather' => $weather, 'rested' => $rested, 'updated_at' => $ts];
                $status = 'saved';
            } elseif ($ts > (int) $row['client_updated_at']) {
                kh_q(
                    'UPDATE checkins SET weather = ?, rested = ?, client_updated_at = ? WHERE id = ?',
                    [$weather, $rested, $ts, $row['id']]
                );
                $canon = ['uuid' => $row['uuid'], 'weather' => $weather, 'rested' => $rested, 'updated_at' => $ts];
                $status = 'saved';
            } else {
                // Kiriman ulang (sama) atau versi lebih lama → kembalikan versi server.
                $canon = ['uuid' => $row['uuid'], 'weather' => $row['weather'], 'rested' => (int) $row['rested'], 'updated_at' => (int) $row['client_updated_at']];
                $status = ($ts === (int) $row['client_updated_at']) ? 'saved' : 'kept_newer';
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $results[] = $res + ['status' => $status, 'record' => ['date' => $date] + $canon];
    }
    kh_json(['ok' => true, 'results' => $results]);
}

function kh_h_consent(): never
{
    $u = kh_require_user();
    $b = kh_body();
    $action = $b['action'] ?? '';
    $current = (string) kh_cfg('consent_version');
    if ($action === 'give') {
        if (($b['version'] ?? '') !== $current) {
            kh_error('consent_version', 409, 'Teks persetujuan sudah diperbarui. Muat ulang aplikasi.');
        }
        kh_q('UPDATE users SET consent_status = \'given\', consent_version = ? WHERE id = ?', [$current, $u['id']]);
        kh_q('INSERT INTO consents (user_id, version, action) VALUES (?, ?, \'given\')', [$u['id'], $current]);
    } elseif ($action === 'withdraw') {
        kh_q('UPDATE users SET consent_status = \'withdrawn\' WHERE id = ?', [$u['id']]);
        kh_q('INSERT INTO consents (user_id, version, action) VALUES (?, ?, \'withdrawn\')', [$u['id'], $u['consent_version']]);
    } else {
        kh_error('invalid', 422);
    }
    kh_json(['ok' => true, 'user' => kh_public_user(kh_user_by_id($u['id']))]);
}

function kh_h_support_event(): never
{
    $u = kh_require_consented_user();
    $kind = kh_body()['kind'] ?? '';
    if (!in_array($kind, ['card_shown', 'contact_tapped'], true)) {
        kh_error('invalid', 422);
    }
    kh_q('INSERT IGNORE INTO support_events (user_id, event_date, kind) VALUES (?, CURDATE(), ?)', [$u['id'], $kind]);
    kh_json(['ok' => true]);
}

function kh_h_account_delete(): never
{
    $u = kh_require_user();
    kh_rate_limit_or_fail('delete-user', (string) $u['id'], 5, 900);
    $password = kh_body()['password'] ?? '';
    $hash = kh_q('SELECT password_hash FROM users WHERE id = ?', [$u['id']])->fetchColumn();
    if (!is_string($password) || !password_verify($password, (string) $hash)) {
        kh_error('invalid_password', 401, 'Kata sandi belum cocok.');
    }
    // ON DELETE CASCADE menghapus sesi, check-in, persetujuan, support_events, reset.
    kh_q('DELETE FROM users WHERE id = ?', [$u['id']]);
    kh_set_cookie(KH_SESSION_COOKIE, '', time() - 3600);
    kh_json(['ok' => true]);
}

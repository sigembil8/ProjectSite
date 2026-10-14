<?php
declare(strict_types=1);

/*
 * Sesi pengguna "tetap masuk".
 * Cookie kh_s = selector:validator. DB menyimpan sha256(validator) saja,
 * sehingga bocornya tabel sessions tidak memberi akses.
 */

const KH_SESSION_COOKIE = 'kh_s';

function kh_session_create(int $userId): void
{
    $days = (int) kh_cfg('session_days', 365);
    $selector = kh_token(12);
    $validator = kh_token(32);
    $expires = time() + $days * 86400;
    kh_q(
        'INSERT INTO sessions (selector, validator_hash, user_id, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))',
        [$selector, hash('sha256', $validator), $userId, $expires]
    );
    kh_set_cookie(KH_SESSION_COOKIE, $selector . ':' . $validator, $expires);
    $GLOBALS['KH_USER'] = null; // muat ulang bila diminta
}

/** Pengguna yang sedang masuk, atau null. Memperpanjang sesi secara bergulir (maks. sekali sehari). */
function kh_current_user(): ?array
{
    if (array_key_exists('KH_USER', $GLOBALS) && $GLOBALS['KH_USER'] !== null) {
        return $GLOBALS['KH_USER'] ?: null;
    }
    $GLOBALS['KH_USER'] = false;
    $raw = $_COOKIE[KH_SESSION_COOKIE] ?? '';
    if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
        return null;
    }
    $row = kh_q(
        'SELECT s.selector, s.validator_hash, s.user_id, UNIX_TIMESTAMP(s.last_used_at) AS last_used,
                u.handle, u.consent_status, u.consent_version
         FROM sessions s JOIN users u ON u.id = s.user_id
         WHERE s.selector = ? AND s.expires_at > NOW()',
        [$m[1]]
    )->fetch();
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $m[2]))) {
        return null;
    }
    if (time() - (int) $row['last_used'] > 86400) {
        $expires = time() + (int) kh_cfg('session_days', 365) * 86400;
        kh_q('UPDATE sessions SET last_used_at = NOW(), expires_at = FROM_UNIXTIME(?) WHERE selector = ?', [$expires, $m[1]]);
        kh_set_cookie(KH_SESSION_COOKIE, $raw, $expires);
    }
    $user = [
        'id'              => (int) $row['user_id'],
        'handle'          => $row['handle'],
        'consent_status'  => $row['consent_status'],
        'consent_version' => $row['consent_version'],
        'selector'        => $row['selector'],
    ];
    $GLOBALS['KH_USER'] = $user;
    return $user;
}

function kh_require_user(): array
{
    return kh_current_user() ?? kh_error('auth_required', 401, 'Silakan masuk lagi.');
}

/** Wajib masuk DAN persetujuan aktif pada versi terbaru. */
function kh_require_consented_user(): array
{
    $u = kh_require_user();
    if (kh_consent_required($u)) {
        kh_error('consent_required', 409, 'Persetujuan diperlukan.');
    }
    return $u;
}

function kh_consent_required(array $u): bool
{
    return $u['consent_status'] !== 'given' || $u['consent_version'] !== (string) kh_cfg('consent_version');
}

/** Data pengguna yang aman dikirim ke aplikasi (tanpa id, tanpa email). */
function kh_public_user(array $u): array
{
    return [
        'handle'  => $u['handle'],
        'consent' => [
            'status'   => $u['consent_status'],
            'version'  => $u['consent_version'],
            'required' => kh_consent_required($u),
        ],
    ];
}

function kh_session_destroy(): void
{
    $raw = $_COOKIE[KH_SESSION_COOKIE] ?? '';
    if (is_string($raw) && preg_match('/^([a-f0-9]{24}):/', $raw, $m)) {
        kh_q('DELETE FROM sessions WHERE selector = ?', [$m[1]]);
    }
    kh_set_cookie(KH_SESSION_COOKIE, '', time() - 3600);
    $GLOBALS['KH_USER'] = false;
}

/** Hash tiruan agar waktu respons login sama walau email tidak terdaftar. */
function kh_dummy_hash(): string
{
    static $h = null;
    return $h ??= password_hash('kh-dummy-password', PASSWORD_DEFAULT);
}

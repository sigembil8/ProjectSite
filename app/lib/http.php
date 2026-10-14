<?php
declare(strict_types=1);

/** Header keamanan. $kind: 'api' (JSON) atau 'html' (dashboard peneliti). */
function kh_security_headers(string $kind = 'api'): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Frame-Options: DENY');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    if (kh_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    if ($kind === 'api') {
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    } else {
        header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    }
    header('Cache-Control: no-store');
}

function kh_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // Banyak host cPanel memakai proksi; percayai hanya bila dikonfigurasi.
    if (kh_cfg('trusted_ip_header') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function kh_require_https(): void
{
    if (kh_cfg('require_https', true) && !kh_is_https()) {
        kh_error('https_required', 403, 'Gunakan HTTPS.');
    }
}

function kh_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

/** Kirim galat JSON. $code stabil untuk dibaca aplikasi, $message untuk manusia. */
function kh_error(string $code, int $status, string $message = '', array $extra = []): never
{
    kh_json(['ok' => false, 'error' => $code, 'message' => $message] + $extra, $status);
}

/** Baca badan JSON (maks. 64 KB). */
function kh_body(): array
{
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($type, 'application/json') !== 0) {
        kh_error('bad_content_type', 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) {
        kh_error('too_large', 413);
    }
    if ($raw === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        kh_error('bad_json', 400);
    }
    return is_array($data) ? $data : kh_error('bad_json', 400);
}

function kh_client_ip(): string
{
    $hdr = kh_cfg('trusted_ip_header');
    if ($hdr && !empty($_SERVER[$hdr])) {
        $ip = trim(explode(',', (string) $_SERVER[$hdr])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Cookie aman: Secure, SameSite=Strict, HttpOnly. */
function kh_set_cookie(string $name, string $value, int $expires, string $path = '/'): void
{
    setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => $path,
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/** Origin aplikasi dari config app_url, mis. https://contoh.id */
function kh_app_origin(): string
{
    $p = parse_url((string) kh_cfg('app_url'));
    $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    if (!empty($p['port'])) {
        $origin .= ':' . $p['port'];
    }
    return $origin;
}

/**
 * Perlindungan CSRF (double-submit): token acak di cookie HttpOnly `kh_csrf`
 * harus sama dengan header X-CSRF-Token. Ditambah cek Origin & Sec-Fetch-Site.
 */
function kh_csrf_token(): string
{
    $t = $_COOKIE['kh_csrf'] ?? '';
    if (!is_string($t) || !preg_match('/^[a-f0-9]{64}$/', $t)) {
        $t = bin2hex(random_bytes(32));
        $_COOKIE['kh_csrf'] = $t;
    }
    // Perbarui masa berlaku setiap kali (maks. 400 hari di Chrome).
    kh_set_cookie('kh_csrf', $t, time() + 400 * 86400);
    return $t;
}

function kh_csrf_check(): void
{
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($site !== '' && $site !== 'same-origin' && $site !== 'none') {
        kh_error('csrf', 403, 'Permintaan lintas situs ditolak.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== kh_app_origin()) {
        kh_error('csrf', 403, 'Origin tidak dikenal.');
    }
    $cookie = $_COOKIE['kh_csrf'] ?? '';
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($cookie) || $cookie === '' || !is_string($header) || !hash_equals($cookie, $header)) {
        kh_error('csrf', 403, 'Token keamanan tidak cocok. Muat ulang aplikasi.');
    }
}

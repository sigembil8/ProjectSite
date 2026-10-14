<?php
declare(strict_types=1);

/**
 * Dimuat oleh semua titik masuk (API, dashboard peneliti, skrip CLI).
 */

define('KH_APP_DIR', __DIR__);

(function (): void {
    $file = getenv('KH_CONFIG') ?: __DIR__ . '/config.php';
    if (!is_file($file)) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "config.php belum ada. Salin config.example.php menjadi config.php.\n";
        exit(1);
    }
    $GLOBALS['KH_CONFIG'] = require $file;
    // Encryption keys live in their own file next to config.php (created by setup.php or
    // scripts/gen_keys.php --write). Keys in config.php are only used if keys.php is absent.
    $keys = __DIR__ . '/keys.php';
    if (is_file($keys)) {
        $GLOBALS['KH_CONFIG']['keys'] = require $keys;
    }
})();

/** Ambil nilai konfigurasi dengan jalur bertitik, mis. kh_cfg('mail.host'). */
function kh_cfg(string $path, mixed $default = null): mixed
{
    $node = $GLOBALS['KH_CONFIG'];
    foreach (explode('.', $path) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

date_default_timezone_set((string) kh_cfg('timezone', 'Asia/Jakarta'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!ini_get('error_log')) {
    ini_set('error_log', KH_APP_DIR . '/storage/php-error.log');
}

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/http.php';
require __DIR__ . '/lib/crypto.php';
require __DIR__ . '/lib/validate.php';
require __DIR__ . '/lib/ratelimit.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/mailer.php';
require __DIR__ . '/lib/totp.php';

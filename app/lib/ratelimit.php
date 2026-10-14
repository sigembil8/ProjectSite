<?php
declare(strict_types=1);

/**
 * Pembatas sederhana jendela-tetap. Mengembalikan true bila masih diizinkan.
 * Kunci di-hash sehingga IP/email tidak tersimpan terbuka.
 */
function kh_rate_hit(string $name, string $key, int $max, int $windowSec, ?PDO $pdo = null): bool
{
    $bucket = hash('sha256', $name . '|' . $key);
    $now = time();
    $windowStart = $now - ($now % $windowSec);
    kh_q(
        'INSERT INTO rate_limits (bucket, hits, window_start) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE
           hits = IF(window_start < VALUES(window_start), 1, hits + 1),
           window_start = GREATEST(window_start, VALUES(window_start))',
        [$bucket, $windowStart],
        $pdo
    );
    $hits = (int) kh_q('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket], $pdo)->fetchColumn();
    return $hits <= $max;
}

/** Hentikan permintaan dengan 429 bila batas terlampaui. */
function kh_rate_limit_or_fail(string $name, string $key, int $max, int $windowSec): void
{
    if (!kh_rate_hit($name, $key, $max, $windowSec)) {
        header('Retry-After: ' . $windowSec);
        kh_error('rate_limited', 429, 'Terlalu banyak percobaan. Coba lagi nanti.');
    }
}

/** Kosongkan penghitung (mis. setelah login berhasil). */
function kh_rate_reset(string $name, string $key, ?PDO $pdo = null): void
{
    kh_q('DELETE FROM rate_limits WHERE bucket = ?', [hash('sha256', $name . '|' . $key)], $pdo);
}

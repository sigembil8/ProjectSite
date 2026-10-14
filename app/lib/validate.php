<?php
declare(strict_types=1);

const KH_WEATHER = ['cerah', 'berawan', 'gerimis', 'badai'];

function kh_valid_email(mixed $v): bool
{
    return is_string($v) && strlen($v) <= 254 && filter_var(trim($v), FILTER_VALIDATE_EMAIL) !== false;
}

/** Nama samaran: 3–24 huruf/angka/spasi/titik/garis bawah/tanda hubung, diawali huruf/angka. */
function kh_valid_handle(mixed $v): bool
{
    return is_string($v) && preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\-]{2,23}$/u', $v) === 1;
}

function kh_clean_handle(string $v): string
{
    return preg_replace('/\s+/u', ' ', trim($v)) ?? '';
}

function kh_valid_password(mixed $v): bool
{
    return is_string($v) && mb_strlen($v) >= 8 && strlen($v) <= 200;
}

function kh_valid_uuid(mixed $v): bool
{
    return is_string($v) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $v) === 1;
}

/** Tanggal Y-m-d yang benar-benar ada. */
function kh_valid_date(mixed $v): bool
{
    if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y);
}

/**
 * Apakah stempel waktu (epoch ms) jatuh pada tanggal lokal $date di salah satu zona waktu pengguna?
 * Dipakai untuk aturan "bisa diubah sampai tengah malam" saat sinkronisasi offline.
 */
function kh_ms_within_local_day(int $ms, string $date): bool
{
    [$minH, $maxH] = (array) kh_cfg('client_utc_offsets', [7, 9]);
    $dayStartUtc = (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone('UTC')))->getTimestamp();
    $from = ($dayStartUtc - (int) $maxH * 3600) * 1000;          // tengah malam di zona paling timur
    $to   = ($dayStartUtc + 86400 - (int) $minH * 3600) * 1000;  // tengah malam berikutnya di zona paling barat
    return $ms >= $from && $ms < $to;
}

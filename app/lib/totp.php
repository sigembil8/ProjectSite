<?php
declare(strict_types=1);

/* TOTP (RFC 6238, SHA-1, 6 digit, 30 detik) untuk login peneliti. */

function kh_base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function kh_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '');
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function kh_totp_code(string $secretBin, int $step): string
{
    $hash = hash_hmac('sha1', pack('J', $step), $secretBin, true);
    $offset = ord($hash[19]) & 0x0f;
    $num = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16)
         | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
    return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Periksa kode dengan toleransi ±1 langkah. Mengembalikan langkah yang cocok (untuk
 * mencegah pemakaian ulang) atau null.
 */
function kh_totp_verify(string $secretBin, string $code, int $lastStep): ?int
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return null;
    }
    $now = intdiv(time(), 30);
    for ($s = $now - 1; $s <= $now + 1; $s++) {
        if ($s > $lastStep && hash_equals(kh_totp_code($secretBin, $s), $code)) {
            return $s;
        }
    }
    return null;
}

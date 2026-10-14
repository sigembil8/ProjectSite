<?php
declare(strict_types=1);

/** Ambil kunci 32 byte dari config 'keys'. */
function kh_key(string $name): string
{
    $raw = base64_decode((string) kh_cfg('keys.' . $name, ''), true);
    if ($raw === false || strlen($raw) !== 32) {
        throw new RuntimeException("Kunci '$name' di config.php belum benar. Jalankan scripts/gen_keys.php.");
    }
    return $raw;
}

function kh_email_normalize(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

/** Indeks buta: memungkinkan mencari email tanpa menyimpannya terbuka. */
function kh_email_index(string $email): string
{
    return hash_hmac('sha256', kh_email_normalize($email), kh_key('email_index'));
}

function kh_encrypt(string $plain, string $keyName): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($plain, $nonce, kh_key($keyName));
}

function kh_decrypt(string $blob, string $keyName): ?string
{
    if (strlen($blob) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        return null;
    }
    $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, kh_key($keyName));
    return $plain === false ? null : $plain;
}

/** Token acak heksadesimal. */
function kh_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

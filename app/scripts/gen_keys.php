<?php
// Cetak kunci acak untuk bagian 'keys' di config.php.  Pakai: php scripts/gen_keys.php
if (PHP_SAPI !== 'cli') { exit; }
foreach (['email_enc', 'email_index', 'app_secret'] as $k) {
    printf("        '%s' => '%s',\n", $k, base64_encode(random_bytes(32)));
}

<?php
declare(strict_types=1);
/*
 * Create keys.php (the encryption keys) in the private folder.
 *   php scripts/gen_keys.php --write
 * Refuses to overwrite an existing keys.php. Browser alternative: setup.php.
 * Without --write it only prints new keys (for the 'keys' section of config.php).
 */
if (PHP_SAPI !== 'cli') { exit; }
if (in_array('--write', $argv, true)) {
    require __DIR__ . '/../bootstrap.php';
    require __DIR__ . '/../lib/setup.php';
    try {
        echo 'Created ' . kh_keys_write() . "\n";
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}
foreach (['email_enc', 'email_index', 'app_secret'] as $k) {
    printf("        '%s' => '%s',\n", $k, base64_encode(random_bytes(32)));
}

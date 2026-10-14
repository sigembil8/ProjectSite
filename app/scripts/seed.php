<?php
declare(strict_types=1);
/*
 * Fake test data (NEVER run on a real research database).
 *
 *   php scripts/seed.php                 # 30 users, last 42 days
 *   php scripts/seed.php --users=50 --days=60
 *   php scripts/seed.php --purge         # delete all fake users (is_seed = 1) and their data
 *
 * Fake users: contohNN@contoh.invalid, password "sandi-contoh-123", handle starts with "Contoh".
 * Browser alternative: the one-time setup page (setup.php).
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../lib/setup.php';

$opts = getopt('', ['users::', 'days::', 'purge']);
if (isset($opts['purge'])) {
    echo 'Deleted: ' . kh_seed_purge() . " fake users (and all their data).\n";
    exit(0);
}
$r = kh_seed((int) ($opts['users'] ?? 30), (int) ($opts['days'] ?? 42));
echo "Created: {$r['users']} fake users, {$r['checkins']} check-ins.\n";
echo "Log in with: contoh07@contoh.invalid / sandi-contoh-123 (last 3 days 'badai').\n";

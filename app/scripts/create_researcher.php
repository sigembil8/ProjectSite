<?php
declare(strict_types=1);
/*
 * Create / reset a researcher login (with TOTP two-factor).
 *   php scripts/create_researcher.php USERNAME
 * Asks for the password (hidden). Prints the TOTP secret once: add it to an
 * authenticator app (Google Authenticator, Aegis, 2FAS, ...).
 * Browser alternative: the one-time setup page (setup.php).
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../lib/setup.php';

$username = $argv[1] ?? '';
$password = getenv('KH_RESEARCHER_PASSWORD') ?: null;
if ($password === null) {
    echo "New password (min. 14 characters): ";
    system('stty -echo 2>/dev/null');
    $password = trim((string) fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n";
}
try {
    $r = kh_researcher_upsert($username, $password);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\nUsage: php scripts/create_researcher.php username\n");
    exit(1);
}
echo "Researcher '$username' is ready.\n\n";
echo "TOTP secret (type it into your authenticator app as a 'setup key'):\n  {$r['secret']}\n\n";
echo "otpauth URI:\n  {$r['uri']}\n";

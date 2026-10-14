<?php
declare(strict_types=1);

/*
 * Tells the public PHP files (api/, peneliti/, setup.php) where the PRIVATE folder is.
 * The private folder holds the PHP code, config.php and keys.php, outside the web root.
 *
 * Search order:
 *   1. environment variable KH_APP_DIR (if your host lets you set one)
 *   2. <home folder>/keluarga-private   e.g. /home/ujib7518/keluarga-private
 *      (this file lives in /home/ujib7518/public_html/keluarga → two levels up is /home/ujib7518)
 *   3. ../app                           (repository layout, for local development)
 *
 * Opening this file in a browser prints nothing.
 */
return (static function (): string {
    $candidates = [
        getenv('KH_APP_DIR') ?: '',
        dirname(__DIR__, 2) . '/keluarga-private',
        dirname(__DIR__) . '/app',
    ];
    foreach ($candidates as $dir) {
        if ($dir !== '' && is_file($dir . '/bootstrap.php')) {
            return $dir;
        }
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Private folder not found. Expected: " . dirname(__DIR__, 2) . "/keluarga-private/bootstrap.php\n";
    exit;
})();

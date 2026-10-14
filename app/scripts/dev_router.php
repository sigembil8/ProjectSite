<?php
/*
 * HANYA untuk uji lokal (bukan untuk cPanel):
 *   php -S localhost:8080 -t public_html app/scripts/dev_router.php
 * Meniru header keamanan dari public_html/.htaccess agar CSP ikut teruji.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/api/') || str_starts_with($path, '/peneliti/')) {
    return false; // PHP menangani sendiri
}
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; connect-src 'self'; font-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'; manifest-src 'self'; worker-src 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$file = __DIR__ . '/../../public_html' . $path;
if ($path === '/' || !is_file($file)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/../../public_html/index.html');
    return true;
}
if (str_ends_with($path, '.webmanifest')) {
    header('Content-Type: application/manifest+json');
    readfile($file);
    return true;
}
return false;

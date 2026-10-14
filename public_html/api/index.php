<?php
declare(strict_types=1);

// Folder app/ sebaiknya di luar public_html (mis. /home/USER/app). Ubah lewat env KH_APP_DIR bila berbeda.
$appDir = getenv('KH_APP_DIR') ?: __DIR__ . '/../../app';
require $appDir . '/bootstrap.php';
require $appDir . '/api.php';

kh_api_dispatch();

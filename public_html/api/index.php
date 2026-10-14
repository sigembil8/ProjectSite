<?php
declare(strict_types=1);

// Private folder (code + config.php + keys.php), found by ../kh-private-path.php
$appDir = require __DIR__ . '/../kh-private-path.php';
require $appDir . '/bootstrap.php';
require $appDir . '/api.php';

kh_api_dispatch();

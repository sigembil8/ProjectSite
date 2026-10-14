<?php
declare(strict_types=1);
/*
 * Pembersihan harian. Contoh cron cPanel (setiap hari 03:15):
 *   15 3 * * * /usr/local/bin/php /home/USER/app/scripts/cron_cleanup.php >/dev/null 2>&1
 */
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__ . '/../bootstrap.php';

$n1 = kh_q('DELETE FROM sessions WHERE expires_at < NOW()')->rowCount();
$n2 = kh_q('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR used_at IS NOT NULL')->rowCount();
$n3 = kh_q('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400])->rowCount();
echo date('c') . " sessions=$n1 resets=$n2 rate_limits=$n3\n";

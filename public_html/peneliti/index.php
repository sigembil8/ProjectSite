<?php
declare(strict_types=1);

/*
 * Dashboard peneliti (peran terpisah dari pengguna aplikasi).
 * - Hanya agregat + ekspor CSV (handle, tanggal, kode cuaca, centang).
 * - Data dibaca HANYA dari view research_* (tanpa email, hanya peserta dengan persetujuan aktif).
 * - Login: nama pengguna + sandi + kode TOTP, dibatasi percobaan, sesi 30 menit.
 */

$appDir = getenv('KH_APP_DIR') ?: __DIR__ . '/../../app';
require $appDir . '/bootstrap.php';

kh_security_headers('html');
kh_require_https();

session_name('kh_r');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/peneliti/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

$pdo = kh_db_research();
$idleLimit = (int) kh_cfg('researcher.session_minutes', 30) * 60;

if (!empty($_SESSION['rid']) && time() - (int) ($_SESSION['seen'] ?? 0) > $idleLimit) {
    $_SESSION = [];
    session_regenerate_id(true);
    $flash = 'Sesi berakhir karena tidak aktif. Silakan masuk lagi.';
}
$_SESSION['csrf'] ??= kh_token(32);

function r_csrf_ok(): bool
{
    return is_string($_POST['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

/* ---------------- Keluar ---------------- */
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST' && r_csrf_ok()) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

/* ---------------- Masuk ---------------- */
$loginError = null;
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? strtolower(trim($_POST['username'])) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $code = is_string($_POST['code'] ?? null) ? preg_replace('/\D/', '', $_POST['code']) : '';
    $ip = kh_client_ip();

    if (!r_csrf_ok()) {
        $loginError = 'Formulir kedaluwarsa. Coba lagi.';
    } elseif (!kh_rate_hit('r-login-ip', $ip, 10, 900, $pdo) || !kh_rate_hit('r-login-user', $username, 5, 900, $pdo)) {
        $loginError = 'Terlalu banyak percobaan. Tunggu 15 menit.';
    } else {
        $r = kh_q('SELECT id, password_hash, totp_secret_enc, totp_last_step FROM researchers WHERE username = ?', [$username], $pdo)->fetch();
        $okPw = password_verify($password, $r['password_hash'] ?? kh_dummy_hash());
        $step = null;
        if ($r && $okPw) {
            if ($r['totp_secret_enc'] !== null) {
                $secret = kh_decrypt($r['totp_secret_enc'], 'app_secret');
                $step = $secret !== null ? kh_totp_verify($secret, $code, (int) $r['totp_last_step']) : null;
            } elseif (!kh_cfg('researcher.require_totp', true)) {
                $step = 0; // TOTP dimatikan di config (tidak disarankan)
            }
        }
        if (!$r || !$okPw || $step === null) {
            $loginError = 'Nama pengguna, sandi, atau kode belum cocok.';
        } else {
            if ($step > 0) {
                kh_q('UPDATE researchers SET totp_last_step = ?, last_login_at = NOW() WHERE id = ?', [$step, $r['id']], $pdo);
            } else {
                kh_q('UPDATE researchers SET last_login_at = NOW() WHERE id = ?', [$r['id']], $pdo);
            }
            kh_rate_reset('r-login-user', $username, $pdo);
            session_regenerate_id(true);
            $_SESSION['rid'] = (int) $r['id'];
            $_SESSION['rname'] = $username;
            $_SESSION['seen'] = time();
            $_SESSION['csrf'] = kh_token(32);
            header('Location: ./');
            exit;
        }
    }
}

if (empty($_SESSION['rid'])) {
    r_page_login($loginError ?? ($flash ?? null));
    exit;
}
$_SESSION['seen'] = time();

/* ---------------- Ekspor CSV ---------------- */
if ($action === 'export' && $_SERVER['REQUEST_METHOD'] === 'POST' && r_csrf_ok()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="keluarga-hebat-checkins-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['handle', 'date', 'weather', 'checkmark']);
    $stmt = $pdo->prepare('SELECT handle, checkin_date, weather, rested FROM research_checkins ORDER BY handle, checkin_date');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        // Cegah injeksi rumus spreadsheet.
        if (preg_match('/^[=+\-@\t\r]/', (string) $row[0])) {
            $row[0] = "'" . $row[0];
        }
        fputcsv($out, [$row[0], $row[1], $row[2], (int) $row[3]]);
    }
    fclose($out);
    exit;
}

/* ---------------- Agregat ---------------- */
$weeksBack = 12;
$today = date('Y-m-d');
$thisMonday = date('Y-m-d', strtotime('monday this week'));
$fromMonday = date('Y-m-d', strtotime("$thisMonday -" . ($weeksBack - 1) . ' weeks'));
$WEATHER = ['cerah' => 'Cerah', 'berawan' => 'Berawan', 'gerimis' => 'Gerimis', 'badai' => 'Badai'];

$participants = (int) kh_q('SELECT COUNT(*) FROM research_participants', [], $pdo)->fetchColumn();
$totalCheckins = (int) kh_q('SELECT COUNT(*) FROM research_checkins', [], $pdo)->fetchColumn();

$weeks = [];
for ($i = 0; $i < $weeksBack; $i++) {
    $wk = date('Y-m-d', strtotime("$fromMonday +$i weeks"));
    $weeks[$wk] = ['n' => 0, 'people' => 0, 'w' => array_fill_keys(array_keys($WEATHER), 0)];
}
$rows = kh_q(
    'SELECT DATE_SUB(checkin_date, INTERVAL WEEKDAY(checkin_date) DAY) AS wk, weather, COUNT(*) AS n
     FROM research_checkins WHERE checkin_date >= ? AND checkin_date <= ? GROUP BY wk, weather',
    [$fromMonday, $today], $pdo
)->fetchAll();
foreach ($rows as $r) {
    if (isset($weeks[$r['wk']])) {
        $weeks[$r['wk']]['w'][$r['weather']] = (int) $r['n'];
        $weeks[$r['wk']]['n'] += (int) $r['n'];
    }
}
$rows = kh_q(
    'SELECT DATE_SUB(checkin_date, INTERVAL WEEKDAY(checkin_date) DAY) AS wk, COUNT(DISTINCT pid) AS p
     FROM research_checkins WHERE checkin_date >= ? AND checkin_date <= ? GROUP BY wk',
    [$fromMonday, $today], $pdo
)->fetchAll();
foreach ($rows as $r) {
    if (isset($weeks[$r['wk']])) {
        $weeks[$r['wk']]['people'] = (int) $r['p'];
    }
}

// Streak: hari berturut-turut dengan check-in.
$byPid = [];
$stmt = $pdo->prepare('SELECT pid, checkin_date FROM research_checkins ORDER BY pid, checkin_date');
$stmt->execute();
while ($r = $stmt->fetch()) {
    $byPid[(int) $r['pid']][] = $r['checkin_date'];
}
$curSum = $longSum = 0;
$yesterday = date('Y-m-d', strtotime('-1 day'));
foreach ($byPid as $dates) {
    $longest = $run = 0;
    $prev = null;
    foreach ($dates as $d) {
        $run = ($prev !== null && $d === date('Y-m-d', strtotime("$prev +1 day"))) ? $run + 1 : 1;
        $longest = max($longest, $run);
        $prev = $d;
    }
    $last = end($dates);
    $longSum += $longest;
    $curSum += ($last === $today || $last === $yesterday) ? $run : 0;
}
$withData = count($byPid);
$avgCurrent = $participants ? $curSum / $participants : 0;
$avgLongest = $withData ? $longSum / $withData : 0;

// Retensi: dari peserta yang sudah bergabung cukup lama, berapa persen check-in pada minggu ke-k.
$retention = [];
$joined = kh_q('SELECT pid, joined_on FROM research_participants', [], $pdo)->fetchAll();
foreach ([1, 2, 4, 8] as $k) {
    $eligible = $kept = 0;
    foreach ($joined as $p) {
        $start = date('Y-m-d', strtotime($p['joined_on'] . ' +' . (7 * $k) . ' days'));
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        if ($end > $today) {
            continue;
        }
        $eligible++;
        foreach ($byPid[(int) $p['pid']] ?? [] as $d) {
            if ($d >= $start && $d <= $end) {
                $kept++;
                break;
            }
        }
    }
    $retention[$k] = ['eligible' => $eligible, 'kept' => $kept];
}

$support = kh_q(
    'SELECT DATE_SUB(event_date, INTERVAL WEEKDAY(event_date) DAY) AS wk, kind, COUNT(*) AS n
     FROM research_support_events WHERE event_date >= ? GROUP BY wk, kind ORDER BY wk',
    [$fromMonday], $pdo
)->fetchAll();

r_page_dashboard(compact('participants', 'totalCheckins', 'weeks', 'avgCurrent', 'avgLongest', 'withData', 'retention', 'support', 'WEATHER'));
exit;

/* ======================================================================= */

function r_head(string $title): void
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">',
         '<meta name="viewport" content="width=device-width, initial-scale=1">',
         '<meta name="robots" content="noindex, nofollow">',
         '<title>', e($title), '</title><link rel="stylesheet" href="peneliti.css"></head><body>';
}

function r_page_login(?string $error): void
{
    r_head('Masuk peneliti · Keluarga Hebat');
    echo '<main class="login"><h1>Dashboard Peneliti</h1><p>Keluarga Hebat</p>';
    if ($error) {
        echo '<p class="error" role="alert">', e($error), '</p>';
    }
    echo '<form method="post" action="./" autocomplete="off">',
         '<input type="hidden" name="action" value="login">',
         '<input type="hidden" name="csrf" value="', e($_SESSION['csrf']), '">',
         '<label for="u">Nama pengguna</label><input id="u" name="username" required autocomplete="username">',
         '<label for="p">Sandi</label><input id="p" name="password" type="password" required autocomplete="current-password">',
         '<label for="c">Kode 6 angka dari aplikasi authenticator</label>',
         '<input id="c" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" autocomplete="one-time-code" required>',
         '<button type="submit">Masuk</button></form>',
         '<p class="small">Akun dibuat lewat <code>php scripts/create_researcher.php</code>. Email peserta tidak pernah ditampilkan di sini.</p>',
         '</main></body></html>';
}

/** Batang check-in per minggu (satu seri, tanpa legenda). */
function r_svg_bars(array $weeks): string
{
    $W = 720; $H = 220; $pl = 40; $pb = 28; $pt = 12;
    $max = max(1, max(array_column($weeks, 'n')));
    $step = ($W - $pl) / count($weeks);
    $bw = max(6, $step - 8);
    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Check-in per minggu, 12 minggu terakhir. Angka lengkap ada di tabel.">';
    foreach ([0, 0.5, 1] as $f) {
        $y = $H - $pb - ($H - $pb - $pt) * $f;
        $svg .= '<line x1="' . $pl . '" x2="' . $W . '" y1="' . $y . '" y2="' . $y . '" class="grid"/>'
              . '<text x="' . ($pl - 6) . '" y="' . ($y + 4) . '" class="axis" text-anchor="end">' . round($max * $f) . '</text>';
    }
    $i = 0;
    foreach ($weeks as $wk => $w) {
        $h = ($H - $pb - $pt) * $w['n'] / $max;
        $x = $pl + $i * $step + ($step - $bw) / 2;
        $y = $H - $pb - $h;
        $svg .= '<g class="mark"><title>Minggu ' . e(date('j M', strtotime($wk))) . ': ' . $w['n'] . ' check-in, ' . $w['people'] . ' peserta</title>'
              . '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . max(0, $h) . '" rx="3" class="bar"/>'
              . '<rect x="' . ($x - 3) . '" y="' . $pt . '" width="' . ($bw + 6) . '" height="' . ($H - $pb - $pt) . '" class="hit"/></g>';
        if ($i % 2 === 0 || $i === count($weeks) - 1) {
            $svg .= '<text x="' . ($x + $bw / 2) . '" y="' . ($H - 8) . '" class="axis" text-anchor="middle">' . e(date('j/n', strtotime($wk))) . '</text>';
        }
        $i++;
    }
    return $svg . '</svg>';
}

/** Batang bertumpuk 100%: proporsi tiap cuaca per minggu. */
function r_svg_stacked(array $weeks, array $WEATHER): string
{
    $W = 720; $H = 220; $pl = 40; $pb = 28; $pt = 12;
    $step = ($W - $pl) / count($weeks);
    $bw = max(6, $step - 8);
    $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Proporsi cuaca hati per minggu. Angka lengkap ada di tabel.">';
    foreach ([0, 0.5, 1] as $f) {
        $y = $H - $pb - ($H - $pb - $pt) * $f;
        $svg .= '<text x="' . ($pl - 6) . '" y="' . ($y + 4) . '" class="axis" text-anchor="end">' . ($f * 100) . '%</text>';
    }
    $i = 0;
    foreach ($weeks as $wk => $w) {
        $x = $pl + $i * $step + ($step - $bw) / 2;
        $y = $H - $pb;
        foreach (array_keys($WEATHER) as $k) {
            if (!$w['n'] || !$w['w'][$k]) {
                continue;
            }
            $h = ($H - $pb - $pt) * $w['w'][$k] / $w['n'];
            $y -= $h;
            $pct = round(100 * $w['w'][$k] / $w['n']);
            // Jarak 2px antar segmen (warna permukaan).
            $svg .= '<rect x="' . $x . '" y="' . ($y + 1) . '" width="' . $bw . '" height="' . max(0, $h - 2) . '" class="seg s-' . $k . '">'
                  . '<title>Minggu ' . e(date('j M', strtotime($wk))) . ' · ' . $WEATHER[$k] . ': ' . $w['w'][$k] . ' (' . $pct . '%)</title></rect>';
        }
        if ($i % 2 === 0 || $i === count($weeks) - 1) {
            $svg .= '<text x="' . ($x + $bw / 2) . '" y="' . ($H - 8) . '" class="axis" text-anchor="middle">' . e(date('j/n', strtotime($wk))) . '</text>';
        }
        $i++;
    }
    return $svg . '</svg>';
}

function r_page_dashboard(array $d): void
{
    extract($d);
    r_head('Dashboard Peneliti · Keluarga Hebat');
    $csrf = e($_SESSION['csrf']);
    echo '<header><div><h1>Dashboard Peneliti</h1><p>Keluarga Hebat · hanya data agregat</p></div>',
         '<form method="post" action="./"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf" value="', $csrf, '">',
         '<span class="who">', e($_SESSION['rname']), '</span> <button type="submit" class="ghost">Keluar</button></form></header><main>';

    echo '<section class="tiles">',
         '<div class="tile"><span class="num">', $participants, '</span><span>Peserta (persetujuan aktif)</span></div>',
         '<div class="tile"><span class="num">', $totalCheckins, '</span><span>Total check-in</span></div>',
         '<div class="tile"><span class="num">', number_format($avgCurrent, 1, ',', '.'), '</span><span>Rata-rata streak saat ini (hari, semua peserta)</span></div>',
         '<div class="tile"><span class="num">', number_format($avgLongest, 1, ',', '.'), '</span><span>Rata-rata streak terpanjang (hari, ', $withData, ' peserta yang pernah check-in)</span></div>',
         '</section>';

    echo '<section class="panel"><h2>Check-in per minggu</h2>', r_svg_bars($weeks), '</section>';

    echo '<section class="panel"><h2>Proporsi cuaca hati per minggu</h2><ul class="legend">';
    foreach ($WEATHER as $k => $label) {
        echo '<li><span class="sw s-', $k, '" aria-hidden="true"></span>', e($label), '</li>';
    }
    echo '</ul>', r_svg_stacked($weeks, $WEATHER), '</section>';

    echo '<section class="panel"><h2>Tabel mingguan</h2><div class="scroll"><table><thead><tr><th scope="col">Minggu mulai</th>',
         '<th scope="col">Check-in</th><th scope="col">Peserta aktif</th>';
    foreach ($WEATHER as $label) {
        echo '<th scope="col">', e($label), '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach (array_reverse($weeks, true) as $wk => $w) {
        echo '<tr><th scope="row">', e(date('d-m-Y', strtotime($wk))), '</th><td>', $w['n'], '</td><td>', $w['people'], '</td>';
        foreach (array_keys($WEATHER) as $k) {
            $pct = $w['n'] ? round(100 * $w['w'][$k] / $w['n']) : 0;
            echo '<td>', $w['w'][$k], ' <span class="muted">(', $pct, '%)</span></td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></section>';

    echo '<section class="panel"><h2>Retensi</h2><p class="muted">Persentase peserta yang melakukan minimal satu check-in pada minggu ke-k setelah bergabung. Hanya peserta yang sudah bergabung cukup lama yang dihitung.</p>',
         '<table><thead><tr><th scope="col">Minggu ke-</th><th scope="col">Peserta yang dihitung</th><th scope="col">Masih check-in</th><th scope="col">Retensi</th></tr></thead><tbody>';
    foreach ($retention as $k => $r) {
        $pct = $r['eligible'] ? round(100 * $r['kept'] / $r['eligible']) . '%' : '–';
        echo '<tr><th scope="row">', $k, '</th><td>', $r['eligible'], '</td><td>', $r['kept'], '</td><td>', $pct, '</td></tr>';
    }
    echo '</tbody></table></section>';

    echo '<section class="panel"><h2>Kartu dukungan (opsional)</h2>';
    if (!$support) {
        echo '<p class="muted">Belum ada catatan.</p>';
    } else {
        echo '<table><thead><tr><th scope="col">Minggu mulai</th><th scope="col">Jenis</th><th scope="col">Jumlah</th></tr></thead><tbody>';
        foreach ($support as $s) {
            $kind = $s['kind'] === 'card_shown' ? 'Kartu tampil' : 'Kontak diketuk';
            echo '<tr><th scope="row">', e(date('d-m-Y', strtotime($s['wk']))), '</th><td>', $kind, '</td><td>', (int) $s['n'], '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</section>';

    echo '<section class="panel"><h2>Ekspor data</h2><p>CSV berisi kolom: <code>handle, date, weather, checkmark</code>. Tidak ada kolom lain.</p>',
         '<form method="post" action="./"><input type="hidden" name="action" value="export"><input type="hidden" name="csrf" value="', $csrf, '">',
         '<button type="submit">Unduh CSV</button></form></section>';

    echo '<p class="muted small">Hanya peserta dengan persetujuan aktif yang dihitung. Email tidak tersimpan terbuka dan tidak pernah tampil di dashboard ini. Sesi berakhir setelah ',
         (int) kh_cfg('researcher.session_minutes', 30), ' menit tidak aktif.</p></main></body></html>';
}

<?php
declare(strict_types=1);

/*
 * ONE-TIME SETUP PAGE — for hosting without a terminal.
 *
 *   https://<your site>/setup.php
 *
 * - Protected by 'setup_token' in the PRIVATE config.php (min. 16 characters).
 * - Wrong tokens are rate-limited (5 tries per 15 minutes per IP).
 * - "Finish setup" writes storage/setup.lock in the private folder; after that this
 *   page does nothing, even if you forget to delete it.
 * - DELETE THIS FILE from the server when setup is done.
 *
 * What it does: checks the server, creates keys.php, loads/removes fake test users,
 * creates a researcher login (TOTP), sends a test email.
 */

$appDir = require __DIR__ . '/kh-private-path.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

function s_e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function s_page(string $title, string $body): never
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">',
         '<meta name="viewport" content="width=device-width, initial-scale=1">',
         '<meta name="robots" content="noindex, nofollow">',
         '<title>', s_e($title), '</title><link rel="icon" href="assets/img/logo.svg"><link rel="stylesheet" href="peneliti/peneliti.css"></head><body>',
         '<header><div><h1>', s_e($title), '</h1><p>Keluarga Hebat · one-time setup</p></div></header><main>',
         $body, '</main></body></html>';
    exit;
}

/* ---------- 1. config.php must exist ---------- */
if (!is_file($appDir . '/config.php')) {
    s_page('Setup: config.php missing', '<section class="panel"><p class="status bad"><strong>PROBLEM:</strong> config.php was not found.</p>'
        . '<p>In File Manager, open <code>' . s_e($appDir) . '</code>, copy <code>config.example.php</code> to <code>config.php</code>, '
        . 'and fill in the values marked <code>&gt;&gt;&gt; CHANGE &lt;&lt;&lt;</code>. Then reload this page.</p></section>');
}

require $appDir . '/bootstrap.php';
require $appDir . '/lib/setup.php';

$lockFile = KH_APP_DIR . '/storage/setup.lock';
if (is_file($lockFile)) {
    s_page('Setup is locked', '<section class="panel"><p class="status ok"><strong>Setup was finished</strong> on ' . s_e(trim((string) file_get_contents($lockFile))) . '.</p>'
        . '<p>This page no longer does anything. <strong>Delete <code>setup.php</code></strong> from <code>' . s_e(__DIR__) . '</code> in File Manager.</p>'
        . '<p class="muted">To run setup again (test site only): delete <code>' . s_e($lockFile) . '</code>.</p></section>');
}

$token = (string) kh_cfg('setup_token', '');
if (strlen($token) < 16 || str_contains($token, 'CHANGE')) {
    s_page('Setup is disabled', '<section class="panel"><p class="status bad"><strong>Setup is disabled.</strong></p>'
        . '<p>To use it, set <code>\'setup_token\'</code> in <code>' . s_e(KH_APP_DIR) . '/config.php</code> to a secret of at least 16 characters, then reload.</p></section>');
}

if (kh_cfg('require_https', true) && !kh_is_https()) {
    s_page('Use HTTPS', '<section class="panel"><p class="status bad">Open this page with <strong>https://</strong>.</p></section>');
}

/* ---------- 2. Session + token login (rate-limited with a file, works before the DB exists) ---------- */
session_name('kh_setup');
session_set_cookie_params(['lifetime' => 0, 'path' => $_SERVER['SCRIPT_NAME'] ?? '/setup.php', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function s_attempts(bool $record): int
{
    $file = KH_APP_DIR . '/storage/setup-attempts.json';
    $key = hash('sha256', kh_client_ip());
    $data = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    $now = time();
    $data = array_filter($data, fn($v) => $now - $v[1] < 900);
    if ($record) {
        $data[$key] = [($data[$key][0] ?? 0) + 1, $data[$key][1] ?? $now];
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    return $data[$key][0] ?? 0;
}

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$csrfOk = $post && is_string($_POST['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
$action = $csrfOk ? (string) ($_POST['action'] ?? '') : '';

if ($action === 'login') {
    if (s_attempts(false) >= 5) {
        $loginMsg = 'Too many wrong tries. Wait 15 minutes.';
    } elseif (hash_equals($token, (string) ($_POST['token'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['ok'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    } else {
        s_attempts(true);
        $loginMsg = 'Wrong setup token.';
    }
}
if (!empty($_SESSION['ok']) && time() - (int) $_SESSION['ok'] > 1800) {
    $_SESSION = [];
    $loginMsg = 'Session expired. Enter the token again.';
}
if (empty($_SESSION['ok'])) {
    s_page('Setup: sign in', '<section class="panel login">'
        . (isset($loginMsg) ? '<p class="error" role="alert">' . s_e($loginMsg) . '</p>' : '')
        . '<form method="post"><input type="hidden" name="csrf" value="' . s_e($_SESSION['csrf']) . '"><input type="hidden" name="action" value="login">'
        . '<label for="t">Setup token (the <code>setup_token</code> value in config.php)</label>'
        . '<input id="t" name="token" type="password" required autocomplete="off"><button type="submit">Continue</button></form></section>');
}
$_SESSION['ok'] = time();

/* ---------- 3. Actions ---------- */
$msg = [];
$out = '';
try {
    switch ($action) {
        case 'keys':
            $msg[] = ['ok', 'Created ' . kh_keys_write() . '. Download a copy of this file to a safe place (a password manager or an encrypted USB stick).'];
            break;
        case 'seed':
            $r = kh_seed(30, 42);
            $msg[] = ['ok', "Created {$r['users']} fake users and {$r['checkins']} check-ins. Log in to the app with contoh07@contoh.invalid / sandi-contoh-123."];
            break;
        case 'purge':
            $msg[] = ['ok', 'Deleted ' . kh_seed_purge() . ' fake users and all their data.'];
            break;
        case 'researcher':
            $r = kh_researcher_upsert(strtolower(trim((string) ($_POST['username'] ?? ''))), (string) ($_POST['password'] ?? ''));
            $out = '<section class="panel"><h2>Researcher login created</h2>'
                . '<p><strong>Shown only once.</strong> Open your authenticator app (Google Authenticator, Aegis, 2FAS, Microsoft Authenticator) → add account → <em>enter a setup key</em>:</p>'
                . '<p>Account name: <code>Keluarga Hebat</code><br>Key: <code class="big">' . s_e(chunk_split($r['secret'], 4, ' ')) . '</code><br>Type: time-based</p>'
                . '<p class="muted small">otpauth URI: <code>' . s_e($r['uri']) . '</code></p>'
                . '<p>Then sign in at <a href="peneliti/">peneliti/</a> with the username, the password and the 6-digit code.</p></section>';
            break;
        case 'mail':
            $to = trim((string) ($_POST['to'] ?? ''));
            if (!kh_valid_email($to)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $ok = kh_mail_send($to, 'Keluarga Hebat — test email', "This is a test email from the setup page.\nSMTP works.\n\n" . date('c'));
            $msg[] = $ok ? ['ok', "Test email sent to $to (check the spam folder too)."]
                         : ['bad', 'Sending failed: ' . ($GLOBALS['KH_MAIL_ERROR'] ?? 'unknown error') . '. Check the mail settings in config.php.'];
            break;
        case 'finish':
            file_put_contents($lockFile, date('c') . "\n", LOCK_EX);
            $_SESSION = [];
            session_destroy();
            s_page('Setup finished', '<section class="panel"><p class="status ok"><strong>Setup is finished and locked.</strong></p>'
                . '<p>Now, in File Manager:</p><ol><li>Delete <code>' . s_e(__FILE__) . '</code></li>'
                . '<li>In <code>' . s_e(KH_APP_DIR) . '/config.php</code>, set <code>\'setup_token\' =&gt; \'\'</code></li></ol>'
                . '<p><a href="./">Open the app</a> · <a href="peneliti/">Researcher dashboard</a></p></section>');
    }
} catch (Throwable $e) {
    $msg[] = ['bad', $e->getMessage()];
}

/* ---------- 4. Checks ---------- */
$checks = [];
$add = function (string $state, string $label, string $detail = '') use (&$checks): void {
    $checks[] = [$state, $label, $detail];
};

$add(PHP_VERSION_ID >= 80100 ? 'ok' : 'bad', 'PHP version', PHP_VERSION . ' (need 8.1 or newer)');
foreach (['pdo_mysql', 'sodium', 'mbstring', 'openssl', 'session'] as $ext) {
    $add(extension_loaded($ext) ? 'ok' : 'bad', "PHP extension: $ext", extension_loaded($ext) ? 'enabled' : 'MISSING — enable it in cPanel → Select PHP Version → Extensions');
}
$add(kh_is_https() ? 'ok' : 'bad', 'HTTPS', kh_is_https() ? 'this page uses HTTPS' : 'not HTTPS');

$appUrl = (string) kh_cfg('app_url');
$u = parse_url($appUrl);
$hostOk = ($u['scheme'] ?? '') === 'https' && ($u['host'] ?? '') === ($_SERVER['HTTP_HOST'] ?? '') && empty($u['path']);
$add($hostOk ? 'ok' : 'bad', 'app_url in config.php', $appUrl . ($hostOk ? '' : ' — must be https://' . ($_SERVER['HTTP_HOST'] ?? '') . ' (no path, no trailing slash)'));

$priv = realpath(KH_APP_DIR) ?: KH_APP_DIR;
$docroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__)) ?: __DIR__;
$outside = !str_starts_with($priv . '/', rtrim($docroot, '/') . '/');
$add($outside ? 'ok' : 'bad', 'Private folder is outside the public web root', $priv);

$perm = fn(string $f) => is_file($f) ? substr(sprintf('%o', fileperms($f)), -3) : '—';
$cfgPerm = $perm(KH_APP_DIR . '/config.php');
$add(in_array($cfgPerm, ['600', '640', '400', '440'], true) ? 'ok' : 'warn', 'config.php permissions', $cfgPerm . ' (recommended 600)');

$keysFile = KH_APP_DIR . '/keys.php';
if (is_file($keysFile)) {
    try {
        foreach (['email_enc', 'email_index', 'app_secret'] as $k) {
            kh_key($k);
        }
        $add('ok', 'Encryption keys (keys.php)', 'present and valid · permissions ' . $perm($keysFile) . ' (recommended 600)');
    } catch (Throwable $e) {
        $add('bad', 'Encryption keys (keys.php)', $e->getMessage());
    }
} else {
    $add('bad', 'Encryption keys (keys.php)', 'not created yet — use the button below');
}
$add(is_writable(KH_APP_DIR . '/storage') ? 'ok' : 'bad', 'storage/ folder is writable', KH_APP_DIR . '/storage');

$consentFile = __DIR__ . '/content/persetujuan.json';
$cv = is_file($consentFile) ? (json_decode((string) file_get_contents($consentFile), true)['version'] ?? '') : '';
$add($cv === (string) kh_cfg('consent_version') ? 'ok' : 'bad', 'consent_version matches content/persetujuan.json', 'config: ' . kh_cfg('consent_version') . ' · json: ' . $cv);

$dbOk = false;
$tablesOk = false;
try {
    $ver = (string) kh_db()->query('SELECT VERSION()')->fetchColumn();
    $dbOk = true;
    $isMaria = stripos($ver, 'mariadb') !== false;
    $num = preg_match('/^\d+\.\d+(\.\d+)?/', $ver, $mm) ? $mm[0] : '0';
    $add(version_compare($num, $isMaria ? '10.3' : '5.7', '>=') ? 'ok' : 'warn', 'Database connection', $ver . ($isMaria ? ' (need MariaDB 10.3+, tested on 10.11)' : ' (need MySQL 5.7+)'));
    $have = kh_q('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    $need = ['users', 'sessions', 'checkins', 'consents', 'support_events', 'password_resets', 'rate_limits', 'researchers',
             'research_participants', 'research_checkins', 'research_support_events'];
    $missing = array_diff($need, $have);
    $tablesOk = !$missing;
    $add($tablesOk ? 'ok' : 'bad', 'Tables and views (schema.sql imported)', $tablesOk ? count($need) . ' found' : 'missing: ' . implode(', ', $missing) . ' — import schema.sql in phpMyAdmin');
    if ($tablesOk) {
        $add('ok', 'Fake test users', (string) kh_q('SELECT COUNT(*) FROM users WHERE is_seed = 1')->fetchColumn());
        $nR = (int) kh_q('SELECT COUNT(*) FROM researchers')->fetchColumn();
        $add($nR ? 'ok' : 'warn', 'Researcher logins', (string) $nR);
    }
} catch (Throwable $e) {
    $add('bad', 'Database connection', 'failed: ' . $e->getMessage() . ' — check db name, user and password in config.php');
}
$add(kh_cfg('mail.transport') === 'smtp' ? 'ok' : 'warn', 'Email transport', (string) kh_cfg('mail.transport') . ' · ' . kh_cfg('mail.host') . ':' . kh_cfg('mail.port') . ' ' . kh_cfg('mail.encryption'));

/* ---------- 5. Page ---------- */
$csrf = s_e($_SESSION['csrf']);
$form = fn(string $action, string $label, string $fields = '', string $cls = '') =>
    '<form method="post"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="' . $action . '">'
    . $fields . '<button type="submit"' . ($cls ? ' class="' . $cls . '"' : '') . '>' . s_e($label) . '</button></form>';
$keysReady = is_file($keysFile);

$html = '';
foreach ($msg as [$state, $text]) {
    $html .= '<p class="status ' . $state . '" role="alert"><strong>' . ($state === 'ok' ? 'DONE: ' : 'PROBLEM: ') . '</strong>' . s_e($text) . '</p>';
}
$html .= $out;
$html .= '<section class="panel"><h2>1. Server checks</h2><table><tbody>';
foreach ($checks as [$state, $label, $detail]) {
    $word = ['ok' => 'OK', 'warn' => 'WARNING', 'bad' => 'PROBLEM'][$state];
    $html .= '<tr><td><span class="status ' . $state . '">' . $word . '</span></td><th scope="row">' . s_e($label) . '</th><td class="wrap">' . s_e($detail) . '</td></tr>';
}
$html .= '</tbody></table><p><a href="setup.php">Re-check</a></p></section>';

$html .= '<section class="panel"><h2>2. Encryption keys</h2>';
$html .= $keysReady
    ? '<p class="status ok">keys.php exists. It is never replaced.</p>'
    : '<p>Creates <code>' . s_e($keysFile) . '</code> with three random keys.</p>' . $form('keys', 'Create keys.php');
$html .= '</section>';

$ready = $keysReady && $dbOk && $tablesOk;
$html .= '<section class="panel"><h2>3. Fake test users</h2>';
$html .= $ready
    ? '<p>30 fake users with 6 weeks of check-ins. All emails are <code>contoh01@contoh.invalid</code> … <code>contoh30@contoh.invalid</code>, password <code>sandi-contoh-123</code>.</p>'
      . $form('seed', 'Load fake test users') . '<p></p>' . $form('purge', 'Delete all fake test users', '', 'ghost')
    : '<p class="muted">Available after keys.php is created and schema.sql is imported.</p>';
$html .= '</section>';

$html .= '<section class="panel"><h2>4. Researcher login</h2>';
$html .= $ready
    ? $form('researcher', 'Create researcher login',
        '<label for="ru">Username (lowercase, e.g. peneliti1)</label><input id="ru" name="username" required pattern="[a-z0-9._\-]{3,64}" autocomplete="off">'
        . '<label for="rp">Password (at least 14 characters)</label><input id="rp" name="password" type="password" required minlength="14" autocomplete="new-password">')
      . '<p class="muted small">Using an existing username resets its password and its authenticator key.</p>'
    : '<p class="muted">Available after keys.php is created and schema.sql is imported.</p>';
$html .= '</section>';

$html .= '<section class="panel"><h2>5. Test email (SMTP)</h2>'
    . $form('mail', 'Send test email', '<label for="mt">Send to</label><input id="mt" name="to" type="email" required>') . '</section>';

$html .= '<section class="panel"><h2>6. Finish</h2><p>Locks this page permanently. Afterwards delete <code>setup.php</code> and clear <code>setup_token</code> in config.php.</p>'
    . $form('finish', 'Finish setup and lock this page') . '</section>';

s_page('Setup', $html);

<?php
declare(strict_types=1);

/**
 * Pengirim email teks polos lewat SMTP (SSL/465 atau STARTTLS/587, AUTH LOGIN).
 * Tanpa pustaka luar agar mudah dipasang di cPanel. transport 'log' untuk uji lokal.
 */
function kh_mail_send(string $to, string $subject, string $body): bool
{
    $cfg = (array) kh_cfg('mail', []);
    $fromEmail = (string) ($cfg['from_email'] ?? '');
    $fromName = (string) ($cfg['from_name'] ?? 'Keluarga Hebat');

    // Cegah injeksi header.
    if (preg_match('/[\r\n]/', $to . $subject . $fromEmail . $fromName) || !kh_valid_email($to)) {
        return false;
    }

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <$fromEmail>",
        "To: <$to>",
        'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
        'Message-ID: <' . kh_token(16) . '@' . (parse_url((string) kh_cfg('app_url'), PHP_URL_HOST) ?: 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));

    if (($cfg['transport'] ?? 'smtp') === 'log') {
        return (bool) file_put_contents(KH_APP_DIR . '/storage/mail.log', "----\n" . $headers[3] . "\n" . $body . "\n", FILE_APPEND | LOCK_EX);
    }

    try {
        kh_smtp_send($cfg, $fromEmail, $to, $message);
        return true;
    } catch (Throwable $e) {
        $GLOBALS['KH_MAIL_ERROR'] = $e->getMessage(); // shown on the setup page's test-email check
        error_log('KH mail error: ' . $e->getMessage());
        return false;
    }
}

function kh_smtp_send(array $cfg, string $from, string $to, string $message): void
{
    $host = (string) $cfg['host'];
    $port = (int) ($cfg['port'] ?? 465);
    $enc = (string) ($cfg['encryption'] ?? 'ssl');
    $timeout = (int) ($cfg['timeout'] ?? 15);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("SMTP connect: $errstr ($errno)");
    }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (string $c, array $okCodes) use ($fp, $read): string {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $okCodes, true)) {
            throw new RuntimeException('SMTP: ' . trim($resp));
        }
        return $resp;
    };

    $ehloHost = parse_url((string) kh_cfg('app_url'), PHP_URL_HOST) ?: 'localhost';
    $cmd('', [220]);
    $cmd("EHLO $ehloHost", [250]);
    if ($enc === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('SMTP: STARTTLS gagal');
        }
        $cmd("EHLO $ehloHost", [250]);
    }
    if (!empty($cfg['username'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string) $cfg['username']), [334]);
        $cmd(base64_encode((string) $cfg['password']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    // Dot-stuffing sesuai RFC 5321.
    $cmd(preg_replace('/^\./m', '..', $message) . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}

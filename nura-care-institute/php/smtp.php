<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free SMTP sender for Nura Care Institute.
 *
 * Credentials live in php/smtp.key (INI, one setting per line), which is blocked
 * from the web by .htaccess and kept out of version control. When the file is
 * absent or incomplete, SMTP disables itself and nci_mail() falls back to PHP
 * mail() so notifications keep working either way.
 *
 * Works with any provider that speaks SMTP AUTH LOGIN over implicit TLS (port
 * 465, secure = ssl) or STARTTLS (port 587, secure = tls): Gmail app passwords,
 * a Hostinger mailbox, Brevo, and similar. See php/smtp.key.example.
 */

require_once __DIR__ . '/config.php';

/** Parsed SMTP settings, or null when not configured. */
function nci_smtp_config(): ?array
{
    $file = __DIR__ . '/smtp.key';
    if (!is_readable($file)) {
        return null;
    }
    $raw = trim((string) file_get_contents($file));
    if ($raw === '') {
        return null;
    }
    $ini = @parse_ini_string($raw, false, INI_SCANNER_RAW);
    if (!is_array($ini)) {
        return null;
    }
    $host = trim((string) ($ini['host'] ?? ''));
    $user = trim((string) ($ini['user'] ?? ''));
    // App passwords are often shown in spaced groups; the real secret has none.
    $pass = preg_replace('/\s+/', '', (string) ($ini['pass'] ?? '')) ?? '';
    if ($host === '' || $user === '' || $pass === '') {
        return null;
    }
    $secure = strtolower(trim((string) ($ini['secure'] ?? 'ssl')));
    if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
        $secure = 'ssl';
    }
    $port = (int) ($ini['port'] ?? ($secure === 'tls' ? 587 : 465));
    return [
        'host' => $host,
        'port' => $port,
        'secure' => $secure,
        'user' => $user,
        'pass' => $pass,
        'fromEmail' => trim((string) ($ini['from_email'] ?? $user)),
        'fromName' => trim((string) ($ini['from_name'] ?? 'Nura Care Institute')),
    ];
}

/** True when SMTP credentials are present. */
function nci_smtp_enabled(): bool
{
    return nci_smtp_config() !== null;
}

/**
 * Deliver one plain-text UTF-8 message over SMTP.
 * Returns [bool ok, string error]. Never throws; times out rather than hanging.
 */
function nci_smtp_send(array $cfg, string $to, string $encodedSubject, string $body, string $replyTo = ''): array
{
    $eol = "\r\n";
    $transport = $cfg['secure'] === 'ssl' ? 'ssl://' : 'tcp://';
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'SNI_enabled' => true,
    ]]);
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client(
        $transport . $cfg['host'] . ':' . $cfg['port'],
        $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx
    );
    if (!$fp) {
        return [false, 'connect failed: ' . ($errstr !== '' ? $errstr : ('errno ' . $errno))];
    }
    stream_set_timeout($fp, 12);

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Multi-line replies keep a hyphen at position 3 until the last line.
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $c) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        return $read();
    };
    $code = static fn(string $r): int => (int) substr($r, 0, 3);
    $fail = static function (string $stage, string $resp) use ($fp): array {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return [false, $stage . ': ' . trim($resp)];
    };

    if ($code($banner = $read()) !== 220) {
        return $fail('greeting', $banner);
    }
    if ($code($r = $cmd('EHLO nuracareinstitute.com')) !== 250) {
        return $fail('ehlo', $r);
    }
    if ($cfg['secure'] === 'tls') {
        if ($code($r = $cmd('STARTTLS')) !== 220) {
            return $fail('starttls', $r);
        }
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
            @fclose($fp);
            return [false, 'starttls: TLS handshake failed'];
        }
        if ($code($r = $cmd('EHLO nuracareinstitute.com')) !== 250) {
            return $fail('ehlo-tls', $r);
        }
    }
    if ($code($r = $cmd('AUTH LOGIN')) !== 334) {
        return $fail('auth', $r);
    }
    if ($code($r = $cmd(base64_encode($cfg['user']))) !== 334) {
        return $fail('username', $r);
    }
    if ($code($r = $cmd(base64_encode($cfg['pass']))) !== 235) {
        return $fail('password rejected', $r);
    }
    if ($code($r = $cmd('MAIL FROM:<' . $cfg['fromEmail'] . '>')) !== 250) {
        return $fail('mail from', $r);
    }
    $rcpt = $code($r = $cmd('RCPT TO:<' . $to . '>'));
    if ($rcpt !== 250 && $rcpt !== 251) {
        return $fail('rcpt to', $r);
    }
    if ($code($r = $cmd('DATA')) !== 354) {
        return $fail('data', $r);
    }

    $headers =
        'Date: ' . date('r') . $eol
        . 'From: ' . $cfg['fromName'] . ' <' . $cfg['fromEmail'] . '>' . $eol
        . 'To: <' . $to . '>' . $eol
        . ($replyTo !== '' ? 'Reply-To: ' . $replyTo . $eol : '')
        . 'Subject: ' . $encodedSubject . $eol
        . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@nuracareinstitute.com>' . $eol
        . 'MIME-Version: 1.0' . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: 8bit' . $eol;
    $normBody = preg_replace('/\r\n|\r|\n/', $eol, $body) ?? $body;
    // Dot-stuffing: a line that is just "." would end DATA prematurely.
    $normBody = preg_replace('/^\./m', '..', $normBody) ?? $normBody;
    fwrite($fp, $headers . $eol . $normBody . $eol . '.' . $eol);
    if ($code($r = $read()) !== 250) {
        return $fail('message body', $r);
    }
    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return [true, ''];
}

/**
 * Send a plain-text notification. Uses SMTP when configured, otherwise PHP
 * mail(); on an SMTP error it logs the reason and falls back to mail() so a
 * message is never silently dropped. Returns whether delivery was accepted.
 */
function nci_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $logFile = NCI_DATA_DIR . '/mail.log';

    $cfg = nci_smtp_config();
    if ($cfg !== null) {
        [$ok, $err] = nci_smtp_send($cfg, $to, $encodedSubject, $body, $replyTo);
        if ($ok) {
            return true;
        }
        @file_put_contents($logFile,
            gmdate('c') . " SMTP to {$to} failed ({$err}); trying mail()\n", FILE_APPEND | LOCK_EX);
    }

    $headers = [
        'From: ' . NCI_MAIL_FROM,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        @file_put_contents($logFile,
            gmdate('c') . " mail() to {$to} failed\n", FILE_APPEND | LOCK_EX);
    }
    return $ok;
}

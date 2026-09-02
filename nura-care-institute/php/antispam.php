<?php
declare(strict_types=1);

/**
 * Shared anti-spam helpers for the Nura Care Institute enrollment form.
 *
 * The HMAC secret is generated on first use and stored in php/secret.key
 * (blocked from web access by .htaccess), so the secret never lives in
 * source control or in the deployed zip. The hardcoded fallback is only
 * used if the web server cannot write files at all.
 */

const ANTISPAM_FALLBACK_SECRET = 'b11ab81489318415afcd28cacf57d038405dd24e5ce86fe10f8042f34e2b69e0';

function antispam_secret(): string
{
    $file = __DIR__ . '/secret.key';
    if (is_readable($file)) {
        $existing = trim((string) file_get_contents($file));
        if ($existing !== '') {
            return $existing;
        }
    }
    $new = bin2hex(random_bytes(32));
    if (@file_put_contents($file, $new, LOCK_EX) !== false) {
        @chmod($file, 0600);
        return $new;
    }
    return ANTISPAM_FALLBACK_SECRET;
}

/**
 * File-based per-IP rate limit: at most $max POST attempts per $window seconds.
 */
function antispam_too_many_requests(int $max = 5, int $window = 3600): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '') {
        return false;
    }
    $dir = sys_get_temp_dir() . '/nura-enroll-rate';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    $file = $dir . '/' . hash('sha256', $ip);
    $now = time();
    $times = [];
    if (is_readable($file)) {
        foreach (explode(',', (string) file_get_contents($file)) as $t) {
            $t = (int) $t;
            if ($t > 0 && $now - $t < $window) {
                $times[] = $t;
            }
        }
    }
    if (count($times) >= $max) {
        return true;
    }
    $times[] = $now;
    @file_put_contents($file, implode(',', $times), LOCK_EX);
    return false;
}

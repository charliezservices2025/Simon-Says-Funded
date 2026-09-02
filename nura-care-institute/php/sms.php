<?php
declare(strict_types=1);

/**
 * Text message delivery via Textbelt (https://textbelt.com).
 *
 * The API key lives in php/sms.key, which is blocked from the web by
 * .htaccess and excluded from version control. An empty or missing key
 * file disables SMS silently; email notifications always still send.
 * Every attempt is logged to data/sms.log so delivery can be audited
 * from the Site Status page.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/store.php';

/**
 * Normalize a user-typed US phone number to E.164 (+1XXXXXXXXXX).
 * Returns '' when the input cannot be a valid US number.
 */
function sms_normalize_us(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $digits = substr($digits, 1);
    }
    // 10 digits, and neither area code nor exchange may start with 0 or 1.
    if (strlen($digits) !== 10 || $digits[0] === '0' || $digits[0] === '1'
        || $digits[3] === '0' || $digits[3] === '1') {
        return '';
    }
    return '+1' . $digits;
}

/**
 * Send one text message. Returns true when Textbelt accepts it.
 * Never throws: SMS is a notification layer, not a point of failure.
 */
function sms_send(string $to, string $message): bool
{
    if (!NCI_SMS['enabled'] || NCI_SMS['key'] === '' || $to === '' || !function_exists('curl_init')) {
        return false;
    }

    $ch = curl_init('https://textbelt.com/text');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_POSTFIELDS => http_build_query([
            'phone' => $to,
            'message' => $message,
            'key' => NCI_SMS['key'],
        ]),
    ]);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $res = is_string($raw) ? json_decode($raw, true) : null;
    $ok = is_array($res) && ($res['success'] ?? false) === true;

    $detail = $ok
        ? 'sent, quota remaining ' . ($res['quotaRemaining'] ?? '?')
        : 'FAILED ' . ($res['error'] ?? ($curlErr !== '' ? $curlErr : 'no response'));
    @file_put_contents(store_path('sms.log'),
        gmdate('c') . ' to ' . $to . ': ' . $detail . "\n", FILE_APPEND | LOCK_EX);

    return $ok;
}

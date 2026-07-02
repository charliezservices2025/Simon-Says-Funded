<?php
declare(strict_types=1);

/**
 * Cookie-free, first-party page view counter. Records only the path and a
 * device size class (mobile / tablet / desktop). No IP addresses, no
 * identifiers, no third parties.
 */

require __DIR__ . '/store.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo '{"ok":false}';
    exit;
}

// Skip obvious bots and lab tools so the numbers reflect people.
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua === '' || preg_match('~bot|crawl|spider|slurp|lighthouse|headless|pingdom|monitor|preview|scan~', $ua)) {
    echo '{"ok":true}';
    exit;
}

$raw = json_decode((string) file_get_contents('php://input'), true);
$path = is_string($raw['p'] ?? null) ? $raw['p'] : '';
$device = is_string($raw['d'] ?? null) ? $raw['d'] : '';

if (!in_array($device, ['m', 't', 'd'], true)) {
    $device = 'd';
}
// Sanitize path: site-relative, short, no query strings.
if ($path === '' || $path[0] !== '/' || strlen($path) > 64 || str_contains($path, '..')) {
    $path = '(other)';
}
$path = strtok($path, '?') ?: '(other)';
if (str_starts_with($path, '/admin') || str_starts_with($path, '/php')) {
    echo '{"ok":true}';
    exit;
}

analytics_record($path, $device);
echo '{"ok":true}';

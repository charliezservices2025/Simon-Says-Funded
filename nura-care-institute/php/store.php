<?php
declare(strict_types=1);

/**
 * Flat-file JSON storage with file locking. Chosen over a database on
 * purpose: zero configuration on shared hosting, trivially backed up by
 * copying the data directory, and more than fast enough at this scale.
 */

require_once __DIR__ . '/config.php';

function store_path(string $name): string
{
    if (!is_dir(NCI_DATA_DIR)) {
        @mkdir(NCI_DATA_DIR, 0755, true);
    }
    return NCI_DATA_DIR . '/' . $name;
}

function store_read(string $name, $default)
{
    $path = store_path($name);
    if (!is_readable($path)) {
        return $default;
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : $default;
}

/**
 * Atomic read-modify-write under an exclusive lock.
 * $fn receives the current value and returns the new value.
 */
function store_update(string $name, $default, callable $fn): bool
{
    $path = store_path($name);
    $fh = fopen($path, 'c+');
    if (!$fh) {
        return false;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return false;
    }
    $raw = stream_get_contents($fh);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        $data = $default;
    }
    $data = $fn($data);
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

/* ---------------- Leads (CRM) ---------------- */

const LEAD_STATUSES = [
    'new' => 'New',
    'booked' => 'Booked',
    'pending' => 'Pending payment',
    'paid' => 'Paid via Zelle',
    'cancelled' => 'Cancelled',
];

function leads_add(array $lead): int
{
    $id = 0;
    store_update('leads.json', ['seq' => 0, 'leads' => []], function ($d) use ($lead, &$id) {
        $d['seq'] = (int) ($d['seq'] ?? 0) + 1;
        $id = $d['seq'];
        $lead['id'] = $id;
        $lead['status'] = 'new';
        $lead['note'] = '';
        $lead['ts'] = time();
        $lead['updated'] = time();
        array_unshift($d['leads'], $lead);
        return $d;
    });
    return $id;
}

function leads_all(): array
{
    $d = store_read('leads.json', ['seq' => 0, 'leads' => []]);
    return $d['leads'] ?? [];
}

function leads_set_status(int $id, string $status, string $note): bool
{
    if (!isset(LEAD_STATUSES[$status])) {
        return false;
    }
    $found = false;
    store_update('leads.json', ['seq' => 0, 'leads' => []], function ($d) use ($id, $status, $note, &$found) {
        foreach ($d['leads'] as &$l) {
            if ((int) $l['id'] === $id) {
                $l['status'] = $status;
                $l['note'] = mb_substr($note, 0, 500);
                $l['updated'] = time();
                $found = true;
                break;
            }
        }
        return $d;
    });
    return $found;
}

/* ---------------- Analytics ---------------- */

function analytics_record(string $path, string $device): void
{
    $month = gmdate('Y-m');
    $day = gmdate('Y-m-d');
    store_update("analytics-$month.json", ['days' => [], 'pages' => []], function ($d) use ($day, $path, $device) {
        if (!isset($d['days'][$day])) {
            $d['days'][$day] = ['m' => 0, 't' => 0, 'd' => 0];
        }
        $d['days'][$day][$device] = ($d['days'][$day][$device] ?? 0) + 1;
        if (count($d['pages']) > 300 && !isset($d['pages'][$path])) {
            $path = '(other)';
        }
        $d['pages'][$path] = ($d['pages'][$path] ?? 0) + 1;
        return $d;
    });
}

/** Merge day rows for the last $n days (UTC), oldest first. */
function analytics_days(int $n): array
{
    $out = [];
    $months = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $day = gmdate('Y-m-d', time() - $i * 86400);
        $month = substr($day, 0, 7);
        if (!isset($months[$month])) {
            $months[$month] = store_read("analytics-$month.json", ['days' => [], 'pages' => []]);
        }
        $row = $months[$month]['days'][$day] ?? ['m' => 0, 't' => 0, 'd' => 0];
        $out[$day] = ['m' => (int) ($row['m'] ?? 0), 't' => (int) ($row['t'] ?? 0), 'd' => (int) ($row['d'] ?? 0)];
    }
    return $out;
}

/** Page totals for the current and previous month combined. */
function analytics_pages(): array
{
    $pages = [];
    foreach ([gmdate('Y-m'), gmdate('Y-m', strtotime('first day of last month'))] as $month) {
        $d = store_read("analytics-$month.json", ['days' => [], 'pages' => []]);
        foreach (($d['pages'] ?? []) as $p => $n) {
            $pages[$p] = ($pages[$p] ?? 0) + (int) $n;
        }
    }
    arsort($pages);
    return $pages;
}

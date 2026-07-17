<?php
declare(strict_types=1);

/**
 * Nura Care Institute admin dashboard.
 * Views: overview, traffic, leads (CRM), status. Session auth with two
 * super admin accounts, CSRF-protected writes, rate-limited login.
 */

require __DIR__ . '/../php/store.php';
require __DIR__ . '/../php/antispam.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('ncisess');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Email the admissions inbox when a student record is added. The record is
 * already stored, so a delivery hiccup is logged, never lost, and never blocks
 * the redirect back to the roster.
 */
function notify_student_added(int $id, array $s): void
{
    $name = trim((string) ($s['name'] ?? ''));
    $statusKey = (string) ($s['status'] ?? 'enrolled');
    $body = implode("\n", [
        'A new student was added to the Nura Care Institute roster.',
        '',
        "Student #{$id}: " . ($name !== '' ? $name : '(no name)'),
        'Course: ' . (trim((string) ($s['course'] ?? '')) ?: '(not set)'),
        'Status: ' . (STUDENT_STATUSES[$statusKey] ?? 'Enrolled'),
        'Mobile: ' . (trim((string) ($s['mobile'] ?? '')) ?: '(not set)'),
        'Email: ' . (trim((string) ($s['email'] ?? '')) ?: '(not set)'),
        'Start date: ' . (trim((string) ($s['start'] ?? '')) ?: '(not set)'),
        'Duration: ' . (trim((string) ($s['duration'] ?? '')) ?: '(not set)'),
        '',
        'Manage students: ' . NCI_ADMIN_URL . '?view=students',
    ]) . "\n";
    $subject = '=?UTF-8?B?' . base64_encode('New student added: ' . ($name !== '' ? $name : 'record #' . $id)) . '?=';
    $headers = implode("\r\n", [
        'From: ' . NCI_MAIL_FROM,
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8',
    ]);
    foreach (NCI_NOTIFY_EMAILS as $to) {
        if (!@mail($to, $subject, $body, $headers)) {
            @file_put_contents(store_path('mail.log'),
                gmdate('c') . " student #{$id} mail to {$to} failed\n", FILE_APPEND | LOCK_EX);
        }
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['csrf']);
}

/* ---------- login throttle: 6 attempts / 15 min per IP ---------- */
function login_throttled(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
    $d = store_read('loginlimit.json', []);
    $now = time();
    $times = array_values(array_filter($d[$ip] ?? [], fn($t) => $now - (int) $t < 900));
    return count($times) >= 6;
}

function login_note_failure(): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
    store_update('loginlimit.json', [], function ($d) use ($ip) {
        $now = time();
        $d[$ip] = array_values(array_filter($d[$ip] ?? [], fn($t) => $now - (int) $t < 900));
        $d[$ip][] = $now;
        return $d;
    });
}

/* ---------- auth actions ---------- */
$loginError = '';
if (($_POST['action'] ?? '') === 'login') {
    if (login_throttled()) {
        $loginError = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $u = strtolower(trim((string) ($_POST['username'] ?? '')));
        $p = (string) ($_POST['password'] ?? '');
        $acct = NCI_ADMINS[$u] ?? null;
        if ($acct && password_verify($p, $acct['hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $u;
            $_SESSION['name'] = $acct['name'];
            $_SESSION['login_at'] = time();
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        login_note_failure();
        $loginError = 'Incorrect username or password.';
    }
}

if (($_GET['action'] ?? '') === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$user = $_SESSION['user'] ?? null;
// 12-hour session cap
if ($user && time() - (int) ($_SESSION['login_at'] ?? 0) > 43200) {
    $_SESSION = [];
    session_destroy();
    $user = null;
}

/* ---------- flash + Post/Redirect/Get ----------
   Every state-changing POST redirects afterward so a browser refresh can
   never resubmit it (no duplicate students, no double uploads). The
   message survives the redirect in the session. */
$flash = '';
$flashErr = false;
if (isset($_SESSION['flash'])) {
    [$flash, $flashErr] = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

/** Query params worth preserving across a redirect (filters, current view). */
function keep_params(string $view): array
{
    $out = ['view' => $view];
    foreach (['status', 'course', 'q'] as $k) {
        $v = (string) ($_GET[$k] ?? '');
        if ($v !== '' && $v !== 'all') {
            $out[$k] = $v;
        }
    }
    return $out;
}

function flash_redirect(string $msg, bool $isError, array $params): never
{
    $_SESSION['flash'] = [$msg, $isError];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params), true, 303);
    exit;
}

/* A POST bigger than the server's post_max_size arrives with $_POST and
   $_FILES both empty; without this check it would fail with no message. */
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash_redirect('That file is larger than the server accepts (post_max_size). Try a smaller file.', true, ['view' => 'forms']);
}

/* ---------- lead status update ---------- */
if ($user && ($_POST['action'] ?? '') === 'lead_status') {
    if (!csrf_ok()) {
        flash_redirect('Security check failed. Please try again.', true, keep_params('leads'));
    }
    $ok = leads_set_status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''), trim((string) ($_POST['note'] ?? '')));
    flash_redirect($ok ? 'Lead #' . (int) $_POST['id'] . ' updated.' : 'Could not update that lead.', !$ok, keep_params('leads'));
}

/* ---------- students (instructor CRM) ---------- */
$addOld = [];      // re-fills the add form when validation fails, so nothing typed is lost
if ($user && ($_POST['action'] ?? '') === 'student_add') {
    if (!csrf_ok()) {
        $flash = 'Security check failed. Your typed details are still below; press Add student again.';
        $flashErr = true;
        $addOld = $_POST;
    } elseif (trim((string) ($_POST['name'] ?? '')) === '') {
        $flash = 'A student needs at least a name. Your other details are still below.';
        $flashErr = true;
        $addOld = $_POST;
    } else {
        $newId = students_add($_POST);
        if ($newId) {
            notify_student_added($newId, $_POST);
            flash_redirect('Student #' . $newId . ' added. A confirmation email was sent to the admissions inbox.', false, keep_params('students'));
        }
        $flash = 'Could not save the student.';
        $flashErr = true;
        $addOld = $_POST;
    }
}

if ($user && ($_POST['action'] ?? '') === 'student_update') {
    if (!csrf_ok()) {
        flash_redirect('Security check failed. Please try again.', true, keep_params('students'));
    }
    if (trim((string) ($_POST['name'] ?? '')) === '') {
        flash_redirect('A student needs at least a name; the change was not saved.', true, keep_params('students'));
    }
    $ok = students_update((int) ($_POST['id'] ?? 0), $_POST);
    flash_redirect($ok ? 'Student #' . (int) $_POST['id'] . ' updated.' : 'Could not update that student.', !$ok, keep_params('students'));
}

if ($user && ($_POST['action'] ?? '') === 'student_delete') {
    if (!csrf_ok()) {
        flash_redirect('Security check failed. Please try again.', true, keep_params('students'));
    }
    $ok = students_delete((int) ($_POST['id'] ?? 0));
    flash_redirect($ok ? 'Student removed.' : 'Could not remove that student.', !$ok, keep_params('students'));
}

/* ---------- instructor forms library ---------- */
const FORM_EXTENSIONS = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
];

function forms_dir(): string
{
    $dir = NCI_DATA_DIR . '/forms';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

if ($user && ($_POST['action'] ?? '') === 'form_upload') {
    if (!csrf_ok()) {
        flash_redirect('Security check failed. Please try again.', true, ['view' => 'forms']);
    }
    $f = $_FILES['file'] ?? null;
    $ext = $f ? strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)) : '';
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash_redirect('Upload failed. Choose a file and try again; very large files may exceed the server limit.', true, ['view' => 'forms']);
    }
    if ((int) $f['size'] > 15 * 1024 * 1024) {
        flash_redirect('That file is over the 15 MB limit.', true, ['view' => 'forms']);
    }
    if (!isset(FORM_EXTENSIONS[$ext])) {
        flash_redirect('Allowed file types: PDF, Word, Excel, PNG, JPG.', true, ['view' => 'forms']);
    }
    $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo((string) $f['name'], PATHINFO_FILENAME)), '-'));
    $slug = $slug !== '' ? $slug : 'form';
    // Claim the target name atomically (fopen 'x' fails if it exists), so two
    // simultaneous uploads of the same filename can never overwrite each other.
    $name = $slug . '.' . $ext;
    $i = 2;
    while (!($fh = @fopen(forms_dir() . '/' . $name, 'x'))) {
        if ($i > 500) {
            flash_redirect('Could not store the file. Check that data/ is writable.', true, ['view' => 'forms']);
        }
        $name = $slug . '-' . $i++ . '.' . $ext;
    }
    fclose($fh);
    if (move_uploaded_file($f['tmp_name'], forms_dir() . '/' . $name)) {
        flash_redirect('Uploaded ' . $name . '.', false, ['view' => 'forms']);
    }
    @unlink(forms_dir() . '/' . $name);
    flash_redirect('Could not store the file. Check that data/ is writable.', true, ['view' => 'forms']);
}

if ($user && ($_POST['action'] ?? '') === 'form_delete') {
    if (!csrf_ok()) {
        flash_redirect('Security check failed. Please try again.', true, ['view' => 'forms']);
    }
    $name = basename((string) ($_POST['f'] ?? ''));
    $path = forms_dir() . '/' . $name;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $ok = ($name !== '' && isset(FORM_EXTENSIONS[$ext]) && is_file($path) && @unlink($path));
    flash_redirect($ok ? 'Deleted ' . $name . '.' : 'Could not delete that file.', !$ok, ['view' => 'forms']);
}

/* ---------- file download (auth required; data/ is never web readable) ---------- */
if ($user && ($_GET['action'] ?? '') === 'form_dl') {
    $name = basename((string) ($_GET['f'] ?? ''));
    $path = forms_dir() . '/' . $name;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($name === '' || !isset(FORM_EXTENSIONS[$ext]) || !is_file($path)) {
        http_response_code(404);
        exit('File not found.');
    }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . FORM_EXTENSIONS[$ext]);
    $inline = in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
    header('Content-Length: ' . (string) filesize($path));
    readfile($path);
    exit;
}

/* ---------- CSV export of students ---------- */
function csv_cell(string $v): string
{
    // Plain numbers and phone numbers ("+1 916...", "(916) 555-0134") are
    // safe as-is; everything else starting with a formula trigger gets the
    // classic apostrophe guard against spreadsheet formula injection.
    if (preg_match('/^[+\-]?[\d\s().\-\/]+$/', $v)) {
        return $v;
    }
    return preg_match('/^[=+\-@\t]/', $v) ? "'" . $v : $v;
}

/** One shared filter so the list, the CSV export, and the roster always agree. */
function students_filtered(array $students, string $course, string $q): array
{
    return array_values(array_filter($students, function ($s) use ($course, $q) {
        if ($course !== 'all' && $course !== '' && ($s['course'] ?? '') !== $course) {
            return false;
        }
        if ($q !== '') {
            $hay = mb_strtolower(implode(' ', [$s['name'] ?? '', $s['email'] ?? '', $s['mobile'] ?? '', $s['address'] ?? '', $s['notes'] ?? '']));
            return str_contains($hay, mb_strtolower($q));
        }
        return true;
    }));
}

/** Local (Pacific) date so printed rosters and file names match the classroom clock. */
function local_date(string $format): string
{
    return (new DateTime('now', new DateTimeZone('America/Los_Angeles')))->format($format);
}

if ($user && ($_GET['action'] ?? '') === 'students_csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="nci-students-' . local_date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Address', 'Email', 'Mobile', 'Course', 'Start date', 'Duration', 'Status', 'Notes', 'Added (UTC)', 'Updated (UTC)']);
    $exportRows = students_filtered(students_all(), (string) ($_GET['course'] ?? 'all'), trim((string) ($_GET['q'] ?? '')));
    foreach ($exportRows as $s) {
        fputcsv($out, [
            (int) $s['id'],
            csv_cell((string) $s['name']), csv_cell((string) $s['address']),
            csv_cell((string) $s['email']), csv_cell((string) $s['mobile']),
            csv_cell((string) $s['course']), csv_cell((string) $s['start']),
            csv_cell((string) $s['duration']),
            STUDENT_STATUSES[$s['status']] ?? (string) $s['status'],
            csv_cell((string) $s['notes']),
            gmdate('Y-m-d H:i', (int) $s['ts']),
            gmdate('Y-m-d H:i', (int) $s['updated']),
        ]);
    }
    exit;
}

$view = $_GET['view'] ?? 'overview';
if (!in_array($view, ['overview', 'traffic', 'leads', 'students', 'forms', 'status'], true)) {
    $view = 'overview';
}

/* ================= chart helpers (validated palette) ================= */
const CHART_COLORS = ['d' => '#3f5c9e', 'm' => '#df5430', 't' => '#5e8ad4'];
const CHART_LABELS = ['d' => 'Desktop', 'm' => 'Mobile', 't' => 'Tablet'];

/**
 * Multi-series SVG line chart: one y axis, recessive grid, 2px lines,
 * hover markers with native tooltips, direct end labels, legend rendered
 * by the caller in HTML so text wears text tokens.
 */
function svg_lines(array $days, int $w = 720, int $h = 220): string
{
    $pad = ['l' => 34, 'r' => 46, 't' => 12, 'b' => 24];
    $keys = array_keys($days);
    $n = max(1, count($keys));
    $max = 1;
    foreach ($days as $row) {
        $max = max($max, $row['m'], $row['t'], $row['d']);
    }
    $max = (int) ceil($max * 1.15);
    $iw = $w - $pad['l'] - $pad['r'];
    $ih = $h - $pad['t'] - $pad['b'];
    $x = fn(int $i): float => $pad['l'] + ($n === 1 ? $iw / 2 : $i * $iw / ($n - 1));
    $y = fn(int $v): float => $pad['t'] + $ih - ($v / $max) * $ih;

    // Band bounds: each day owns the plot strip nearest to it, so a pointer
    // anywhere over the chart resolves to exactly one day.
    $band = function (int $i) use ($x, $n, $pad, $w): array {
        $left = $i === 0 ? (float) $pad['l'] : ($x($i - 1) + $x($i)) / 2;
        $right = $i === $n - 1 ? (float) ($w - $pad['r']) : ($x($i) + $x($i + 1)) / 2;
        return [$left, $right];
    };

    $s = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="cx-svg" role="img" aria-label="Daily page views by device" preserveAspectRatio="xMidYMid meet">';
    // grid: 3 recessive lines + y labels
    for ($g = 0; $g <= 3; $g++) {
        $gy = $pad['t'] + $ih - $g * $ih / 3;
        $gv = (int) round($max * $g / 3);
        $s .= '<line x1="' . $pad['l'] . '" y1="' . $gy . '" x2="' . ($w - $pad['r']) . '" y2="' . $gy . '" stroke="#e4e7ec" stroke-width="1"/>';
        $s .= '<text x="' . ($pad['l'] - 6) . '" y="' . ($gy + 4) . '" text-anchor="end" class="cx-tick">' . $gv . '</text>';
    }
    // hover crosshair (moved and shown by JS)
    $s .= '<line class="cx-cross" x1="0" x2="0" y1="' . $pad['t'] . '" y2="' . ($pad['t'] + $ih) . '" visibility="hidden"/>';
    // x labels: first, middle, last
    foreach ([0, intdiv($n - 1, 2), $n - 1] as $i) {
        $s .= '<text x="' . $x($i) . '" y="' . ($h - 6) . '" text-anchor="middle" class="cx-tick">' . h(substr($keys[$i], 5)) . '</text>';
    }
    foreach (['d', 'm', 't'] as $k) {
        $pts = [];
        $i = 0;
        foreach ($days as $row) {
            $pts[] = round($x($i), 1) . ',' . round($y($row[$k]), 1);
            $i++;
        }
        $s .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . CHART_COLORS[$k] . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
        $i = 0;
        foreach ($days as $row) {
            $s .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($row[$k]), 1) . '" r="2.5" fill="' . CHART_COLORS[$k] . '"/>';
            $i++;
        }
        $last = end($days);
        $s .= '<text x="' . ($w - $pad['r'] + 5) . '" y="' . (round($y($last[$k]), 1) + 4) . '" class="cx-end" fill="' . CHART_COLORS[$k] . '">' . $last[$k] . '</text>';
    }
    // transparent hover bands on top, one per day, carrying that day's counts
    $i = 0;
    foreach ($days as $day => $row) {
        [$bl, $br] = $band($i);
        $tot = (int) $row['d'] + (int) $row['m'] + (int) $row['t'];
        $s .= '<rect class="cx-band" x="' . round($bl, 1) . '" y="' . $pad['t'] . '" width="' . round($br - $bl, 1)
            . '" height="' . $ih . '" fill="transparent"'
            . ' tabindex="0" role="img"'
            . ' aria-label="' . h($day) . ': ' . $row['d'] . ' desktop, ' . $row['m'] . ' mobile, ' . $row['t'] . ' tablet, ' . $tot . ' total views"'
            . ' data-cx="' . round($x($i), 1) . '" data-label="' . h($day) . '"'
            . ' data-d="' . (int) $row['d'] . '" data-m="' . (int) $row['m'] . '" data-t="' . (int) $row['t'] . '"></rect>';
        $i++;
    }
    return $s . '</svg>';
}

function fmt_ago(int $ts): string
{
    if ($ts <= 0) return 'never';
    $d = time() - $ts;
    if ($d < 60) return 'just now';
    if ($d < 3600) return intdiv($d, 60) . ' min ago';
    if ($d < 86400) return intdiv($d, 3600) . ' hr ago';
    return intdiv($d, 86400) . ' days ago';
}

/** Public indexable URLs from sitemap.xml as root-relative paths. */
function site_pages(): array
{
    $file = __DIR__ . '/../sitemap.xml';
    if (!is_readable($file)) {
        return [];
    }
    $xml = @simplexml_load_file($file);
    if ($xml === false) {
        return [];
    }
    $paths = [];
    foreach ($xml->url as $u) {
        $p = parse_url((string) $u->loc, PHP_URL_PATH);
        if (is_string($p) && $p !== '') {
            $paths[$p] = true;
        }
    }
    return array_keys($paths);
}

/** A friendly label for a URL path. */
function page_label(string $path): string
{
    if ($path === '/') {
        return 'Home';
    }
    $slug = trim($path, '/');
    $slug = str_replace(['/', '-'], [' / ', ' '], $slug);
    return ucwords($slug);
}

/* ================= data for views ================= */
$leads = $user ? leads_all() : [];
$days14 = $user ? analytics_days(14) : [];
$days30 = $user ? analytics_days(30) : [];
$pages = $user ? analytics_pages() : [];

/* Coverage: every published page mapped to its view count, lowest first.
   Zero-view pages are the ones that need promotion, like Search Console. */
$coverage = [];
if ($user) {
    foreach (site_pages() as $p) {
        $coverage[$p] = (int) ($pages[$p] ?? 0);
    }
    asort($coverage);
}
$notVisited = count(array_filter($coverage, fn($v) => $v === 0));

$statusFilter = $_GET['status'] ?? 'all';
$counts = ['all' => count($leads)];
foreach (LEAD_STATUSES as $k => $label) {
    $counts[$k] = count(array_filter($leads, fn($l) => ($l['status'] ?? 'new') === $k));
}
$shownLeads = $statusFilter === 'all' ? $leads
    : array_values(array_filter($leads, fn($l) => ($l['status'] ?? 'new') === $statusFilter));

/* students list + filters */
$students = $user ? students_all() : [];
$sCourse = (string) ($_GET['course'] ?? 'all');
$sQuery = trim((string) ($_GET['q'] ?? ''));
$shownStudents = students_filtered($students, $sCourse, $sQuery);
$activeStudents = count(array_filter($students, fn($s) => in_array($s['status'] ?? '', ['enrolled', 'in-progress'], true)));

/* printable class roster: minimal standalone page, then stop */
if ($user && $view === 'students' && isset($_GET['print'])) {
    $rosterTitle = $sCourse === 'all' ? 'All courses' : $sCourse;
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Class Roster | Nura Care Institute</title>
<style>
  body { font: 14px/1.5 "Public Sans", system-ui, sans-serif; color: #1b1e22; margin: 32px; }
  h1 { font-size: 20px; margin: 0 0 2px; }
  .sub { color: #555; margin: 0 0 18px; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #9aa0aa; padding: 8px 10px; text-align: left; font-size: 13px; }
  th { background: #f0f2f5; }
  td.sig { width: 160px; }
  .toolbar { margin-bottom: 16px; }
  .toolbar a, .toolbar button { font: 600 14px "Public Sans", system-ui, sans-serif; margin-right: 10px; padding: 8px 14px; border: 1px solid #46587a; border-radius: 8px; background: #46587a; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
  .toolbar a { background: #fff; color: #46587a; }
  @media print { .toolbar { display: none; } body { margin: 0; } }
</style>
</head>
<body>
<div class="toolbar">
  <a href="?view=students<?= $sCourse !== 'all' ? '&amp;course=' . h(urlencode($sCourse)) : '' ?>">Back to dashboard</a>
  <button onclick="window.print()">Print this roster</button>
</div>
<h1>Nura Care Institute &middot; Class Roster</h1>
<p class="sub"><?= h($rosterTitle) ?> &middot; <?= count($shownStudents) ?> student(s) &middot; printed <?= h(local_date('M j, Y')) ?></p>
<table>
  <thead><tr><th>#</th><th>Name</th><th>Mobile</th><th>Email</th><th>Course</th><th>Start</th><th>Duration</th><th class="sig">Signature</th></tr></thead>
  <tbody>
    <?php $n = 0; foreach ($shownStudents as $s): $n++; ?>
      <tr>
        <td><?= $n ?></td><td><?= h($s['name']) ?></td><td><?= h($s['mobile']) ?></td>
        <td><?= h($s['email']) ?></td><td><?= h($s['course']) ?></td>
        <td><?= h($s['start']) ?></td><td><?= h($s['duration']) ?></td><td class="sig"></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$shownStudents): ?><tr><td colspan="8">No students match this filter yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</body>
</html><?php
    exit;
}

function sum_days(array $days): array
{
    $t = ['m' => 0, 't' => 0, 'd' => 0];
    foreach ($days as $r) {
        $t['m'] += $r['m']; $t['t'] += $r['t']; $t['d'] += $r['d'];
    }
    $t['all'] = $t['m'] + $t['t'] + $t['d'];
    return $t;
}
$today = $user ? sum_days(array_slice($days14, -1, 1, true)) : ['all' => 0];
$week = $user ? sum_days(array_slice($days14, -7, 7, true)) : ['all' => 0, 'm' => 0, 't' => 0, 'd' => 0];
$mobileShare = ($week['all'] ?? 0) > 0 ? round(100 * $week['m'] / $week['all']) : 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#1b1e22">
<title>Admin | Nura Care Institute</title>
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/favicon-180.png">
<link rel="preload" href="/assets/fonts/roboto-slab-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/public-sans-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="<?= $user ? 'is-app' : 'is-login' ?>">

<?php if (!$user): ?>
  <main class="login-wrap">
    <form method="post" class="login-card" autocomplete="off">
      <img src="/assets/icon-mark-96.webp" alt="" width="56" height="50">
      <h1>Nura Care Institute</h1>
      <p class="login-sub">Admin sign in</p>
      <?php if ($loginError): ?><p class="login-err" role="alert"><?= h($loginError) ?></p><?php endif; ?>
      <input type="hidden" name="action" value="login">
      <label for="u">Username</label>
      <input id="u" name="username" type="text" autocapitalize="none" autocomplete="username" required>
      <label for="p">Password</label>
      <input id="p" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Sign in</button>
      <p class="login-foot"><a href="/">Back to website</a></p>
    </form>
  </main>

<?php else: ?>
  <div class="shell">
    <header class="appbar">
      <button class="menu-btn" id="menuBtn" aria-expanded="false" aria-controls="sideNav" aria-label="Open menu"><span></span></button>
      <a class="appbar-brand" href="/admin/">
        <img src="/assets/icon-mark-96.webp" alt="" width="30" height="27">
        <span>NCI&nbsp;Admin</span>
      </a>
      <span class="appbar-user"><?= h($_SESSION['name']) ?></span>
    </header>

    <nav class="sidenav" id="sideNav" aria-label="Admin">
      <a class="sidenav-brand" href="/admin/">
        <img src="/assets/icon-mark-96.webp" alt="" width="34" height="30">
        <span>NCI Admin</span>
      </a>
      <a href="?view=overview" <?= $view === 'overview' ? 'aria-current="page"' : '' ?>>Overview</a>
      <a href="?view=traffic" <?= $view === 'traffic' ? 'aria-current="page"' : '' ?>>Traffic</a>
      <a href="?view=leads" <?= $view === 'leads' ? 'aria-current="page"' : '' ?>>Leads &amp; Bookings
        <?php if ($counts['new']): ?><span class="pill"><?= $counts['new'] ?></span><?php endif; ?></a>
      <a href="?view=students" <?= $view === 'students' ? 'aria-current="page"' : '' ?>>Students
        <?php if ($activeStudents): ?><span class="pill pill-quiet"><?= $activeStudents ?></span><?php endif; ?></a>
      <a href="?view=forms" <?= $view === 'forms' ? 'aria-current="page"' : '' ?>>Instructor Forms</a>
      <a href="?view=status" <?= $view === 'status' ? 'aria-current="page"' : '' ?>>Site Status</a>
      <div class="sidenav-foot">
        <a href="/" target="_blank" rel="noopener">Open website</a>
        <a href="?action=logout">Sign out</a>
      </div>
    </nav>
    <div class="scrim" id="navScrim" hidden></div>

    <main class="content">
      <?php if ($flash): ?><p class="flash <?= $flashErr ? 'flash-err' : '' ?>" role="<?= $flashErr ? 'alert' : 'status' ?>"><?= h($flash) ?></p><?php endif; ?>

      <?php if ($view === 'overview'): ?>
        <h1 class="page-title">Overview</h1>
        <p class="page-sub">Signed in as <?= h($_SESSION['name']) ?>. All times UTC.</p>
        <div class="tiles">
          <div class="tile"><span class="tile-n"><?= $today['all'] ?></span><span class="tile-l">Views today</span></div>
          <div class="tile"><span class="tile-n"><?= $week['all'] ?></span><span class="tile-l">Views, 7 days</span></div>
          <div class="tile"><span class="tile-n"><?= $mobileShare ?>%</span><span class="tile-l">Mobile share, 7 days</span></div>
          <div class="tile tile-accent"><span class="tile-n"><?= $counts['new'] ?></span><span class="tile-l">New leads</span></div>
          <div class="tile"><span class="tile-n"><?= $counts['pending'] ?></span><span class="tile-l">Pending payment</span></div>
          <div class="tile"><span class="tile-n"><?= $counts['paid'] ?></span><span class="tile-l">Paid via Zelle</span></div>
        </div>

        <section class="card">
          <div class="card-head">
            <h2>Last 14 days</h2>
            <div class="legend">
              <?php foreach (['d', 'm', 't'] as $k): ?>
                <span class="legend-item"><span class="swatch" style="background:<?= CHART_COLORS[$k] ?>"></span><?= CHART_LABELS[$k] ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="chart-wrap"><?= svg_lines($days14) ?><div class="cx-tip" role="status" aria-live="polite" hidden></div></div>
          <p class="chart-hint">Hover or tap a day to see desktop, mobile, and tablet views.</p>
        </section>

        <div class="col-2">
          <section class="card">
            <div class="card-head"><h2>Most visited pages</h2><a class="card-link" href="?view=traffic">Traffic</a></div>
            <?php if (!$pages): ?>
              <p class="empty">No page views recorded yet.</p>
            <?php else: ?>
              <ul class="mini-pages">
                <?php foreach (array_slice($pages, 0, 5, true) as $p => $nv): ?>
                  <li><span class="mp-name"><?= h(page_label($p)) ?></span><span class="mp-n"><?= $nv ?></span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
          <section class="card">
            <div class="card-head"><h2>Pages to improve</h2><a class="card-link" href="?view=traffic">Details</a></div>
            <?php if (!$coverage): ?>
              <p class="empty">Add a sitemap.xml to see coverage.</p>
            <?php else: ?>
              <ul class="mini-pages">
                <?php foreach (array_slice($coverage, 0, 5, true) as $p => $nv): ?>
                  <li><span class="mp-name"><?= h(page_label($p)) ?></span><span class="mp-n <?= $nv === 0 ? 'mp-zero' : '' ?>"><?= $nv ?></span></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        </div>

        <section class="card">
          <div class="card-head"><h2>Latest leads</h2><a class="card-link" href="?view=leads">All leads</a></div>
          <?php if (!$leads): ?>
            <p class="empty">No booking requests yet. New enrollment form submissions appear here instantly.</p>
          <?php else: ?>
            <ul class="mini-leads">
              <?php foreach (array_slice($leads, 0, 5) as $l): ?>
                <li>
                  <span class="ml-name"><?= h($l['name']) ?></span>
                  <span class="ml-course"><?= h($l['course']) ?></span>
                  <span class="status s-<?= h($l['status']) ?>"><?= h(LEAD_STATUSES[$l['status']] ?? $l['status']) ?></span>
                  <span class="ml-time"><?= fmt_ago((int) $l['ts']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

      <?php elseif ($view === 'traffic'): ?>
        <h1 class="page-title">Traffic</h1>
        <p class="page-sub">First-party, cookie-free counts. Bots and lab tools are filtered out.</p>

        <div class="tiles tiles-3">
          <?php $t30 = sum_days($days30); ?>
          <div class="tile"><span class="tile-n"><?= $t30['d'] ?></span><span class="tile-l">Desktop, 30 days</span></div>
          <div class="tile"><span class="tile-n"><?= $t30['m'] ?></span><span class="tile-l">Mobile, 30 days</span></div>
          <div class="tile"><span class="tile-n"><?= $t30['t'] ?></span><span class="tile-l">Tablet, 30 days</span></div>
        </div>

        <section class="card">
          <div class="card-head">
            <h2>Last 30 days</h2>
            <div class="legend">
              <?php foreach (['d', 'm', 't'] as $k): ?>
                <span class="legend-item"><span class="swatch" style="background:<?= CHART_COLORS[$k] ?>"></span><?= CHART_LABELS[$k] ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="chart-wrap"><?= svg_lines($days30, 860, 240) ?><div class="cx-tip" role="status" aria-live="polite" hidden></div></div>
          <p class="chart-hint">Hover or tap a day to see desktop, mobile, and tablet views.</p>
        </section>

        <section class="card">
          <div class="card-head"><h2>Top pages</h2><span class="card-note">current + previous month</span></div>
          <?php if (!$pages): ?>
            <p class="empty">No page views recorded yet.</p>
          <?php else: ?>
            <table class="pages-table">
              <thead><tr><th scope="col">Page</th><th scope="col" class="num">Views</th><th scope="col" class="barcol"><span class="sr-only">Share</span></th></tr></thead>
              <tbody>
              <?php $maxP = max($pages); foreach (array_slice($pages, 0, 12, true) as $p => $nv): ?>
                <tr>
                  <td class="pathcell"><?= h($p) ?></td>
                  <td class="num"><?= $nv ?></td>
                  <td class="barcol"><span class="bar" style="width:<?= round(100 * $nv / $maxP) ?>%"></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section class="card">
          <div class="card-head">
            <h2>Pages to improve</h2>
            <span class="card-note"><?= $notVisited ?> of <?= count($coverage) ?> pages had no views</span>
          </div>
          <p class="card-lead">Your published pages with the fewest visits, lowest first. A page showing 0 has not been visited in this window, so it is the best candidate for internal links, a Google Business post, or a share. This is the same idea as the low-traffic report in Search Console.</p>
          <?php if (!$coverage): ?>
            <p class="empty">Add a sitemap.xml to see page coverage here.</p>
          <?php else: ?>
            <table class="pages-table">
              <thead><tr><th scope="col">Page</th><th scope="col" class="num">Views</th><th scope="col">Status</th></tr></thead>
              <tbody>
              <?php foreach (array_slice($coverage, 0, 12, true) as $p => $nv): ?>
                <tr>
                  <td class="pathcell"><a href="<?= h($p) ?>" target="_blank" rel="noopener"><?= h(page_label($p)) ?></a><span class="pathsub"><?= h($p) ?></span></td>
                  <td class="num"><?= $nv ?></td>
                  <td><?php if ($nv === 0): ?><span class="tag tag-warn">Not visited</span><?php elseif ($nv < 5): ?><span class="tag tag-soft">Low traffic</span><?php else: ?><span class="tag tag-ok">Getting views</span><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

      <?php elseif ($view === 'leads'): ?>
        <h1 class="page-title">Leads &amp; Bookings</h1>
        <p class="page-sub">Every enrollment form submission, newest first. Update the status as you work each one.</p>

        <div class="chips" role="navigation" aria-label="Filter by status">
          <a class="chip <?= $statusFilter === 'all' ? 'on' : '' ?>" href="?view=leads">All (<?= $counts['all'] ?>)</a>
          <?php foreach (LEAD_STATUSES as $k => $label): ?>
            <a class="chip <?= $statusFilter === $k ? 'on' : '' ?>" href="?view=leads&amp;status=<?= $k ?>"><?= h($label) ?> (<?= $counts[$k] ?>)</a>
          <?php endforeach; ?>
        </div>

        <?php if (!$shownLeads): ?>
          <section class="card"><p class="empty">Nothing here yet.</p></section>
        <?php endif; ?>

        <?php foreach ($shownLeads as $l): ?>
          <section class="card lead">
            <div class="lead-top">
              <span class="lead-id">#<?= (int) $l['id'] ?></span>
              <h2 class="lead-name"><?= h($l['name']) ?></h2>
              <span class="status s-<?= h($l['status']) ?>"><?= h(LEAD_STATUSES[$l['status']] ?? $l['status']) ?></span>
            </div>
            <dl class="lead-grid">
              <div><dt>Program</dt><dd><?= h($l['course']) ?></dd></div>
              <div><dt>Phone</dt><dd><a href="tel:<?= h(preg_replace('/[^0-9+]/', '', $l['phone'])) ?>"><?= h($l['phone']) ?></a></dd></div>
              <div><dt>Email</dt><dd><a href="mailto:<?= h($l['email']) ?>"><?= h($l['email']) ?></a></dd></div>
              <div><dt>Preferred start</dt><dd><?= h($l['preferred'] ?: 'not given') ?></dd></div>
              <div><dt>Received</dt><dd><?= h(gmdate('M j, Y H:i', (int) $l['ts'])) ?> UTC (<?= fmt_ago((int) $l['ts']) ?>)</dd></div>
              <div><dt>Updated</dt><dd><?= fmt_ago((int) $l['updated']) ?></dd></div>
            </dl>
            <?php if (!empty($l['message'])): ?>
              <p class="lead-msg"><?= nl2br(h($l['message'])) ?></p>
            <?php endif; ?>
            <form method="post" class="lead-form">
              <input type="hidden" name="action" value="lead_status">
              <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
              <label>Status
                <select name="status">
                  <?php foreach (LEAD_STATUSES as $k => $label): ?>
                    <option value="<?= $k ?>" <?= ($l['status'] === $k) ? 'selected' : '' ?>><?= h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="grow">Note
                <input type="text" name="note" maxlength="500" value="<?= h($l['note'] ?? '') ?>" placeholder="Internal note, e.g. Zelle received 7/2">
              </label>
              <button type="submit">Save</button>
            </form>
            <p class="lead-copy"><a href="?view=students&amp;prefill_name=<?= h(urlencode($l['name'])) ?>&amp;prefill_mobile=<?= h(urlencode($l['phone'])) ?>&amp;prefill_email=<?= h(urlencode($l['email'])) ?>&amp;prefill_course=<?= h(urlencode($l['course'])) ?>#add">Copy to Students &rarr;</a></p>
          </section>
        <?php endforeach; ?>

      <?php elseif ($view === 'students'): ?>
        <h1 class="page-title">Students</h1>
        <p class="page-sub">Your typed student records: no more handwriting. Add each student once, then filter, export, or print a class roster.</p>

        <?php
        // Field values survive a failed submit ($addOld) and arrive from a
        // lead via the Copy to Students link (prefill_* query params).
        $addVal = fn(string $k): string => h(trim((string) ($addOld[$k] ?? $_GET['prefill_' . $k] ?? '')));
        $addOpen = $addOld || isset($_GET['prefill_name']);

        // Map a lead's course wording onto a course option; keywords are
        // checked in order and the CNA entry deliberately avoids the bare
        // "cna" token so continuing-education leads do not land on it.
        $courseKeywords = [
            'Certified Nursing Assistant (CNA)' => ['certified nursing assistant'],
            'Home Health Aide (HHA)' => ['home health aide', 'hha'],
            'BLS / CPR Certification' => ['basic life support', 'bls'],
            'ACLS Certification' => ['advanced cardiovascular', 'acls'],
            'Restorative Nursing Assistant (RNA)' => ['restorative', 'rna'],
            'CNA Continuing Education (6 CEUs)' => ['continuing education', 'ceu'],
        ];
        $selCourse = trim((string) ($addOld['course'] ?? ''));
        $leadCourse = trim((string) ($_GET['prefill_course'] ?? ''));
        if ($selCourse === '' && $leadCourse !== '') {
            $lc = mb_strtolower($leadCourse);
            foreach ($courseKeywords as $opt => $keys) {
                foreach ($keys as $kw) {
                    if (str_contains($lc, $kw)) { $selCourse = $opt; break 2; }
                }
            }
            // No match (e.g. "Not sure yet"): keep the lead's wording verbatim
            // instead of silently defaulting to the first course.
            if ($selCourse === '') {
                $selCourse = $leadCourse;
            }
        }
        $selStatus = (string) ($addOld['status'] ?? 'enrolled');
        ?>
        <details class="card add-card" id="add" <?= $addOpen ? 'open' : '' ?>>
          <summary>+ Add a student</summary>
          <form method="post" class="form-grid">
            <input type="hidden" name="action" value="student_add">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <label>Full name *<input type="text" name="name" required maxlength="120" value="<?= $addVal('name') ?>"></label>
            <label>Mobile phone<input type="tel" name="mobile" maxlength="40" value="<?= $addVal('mobile') ?>"></label>
            <label>Email<input type="email" name="email" maxlength="120" value="<?= $addVal('email') ?>"></label>
            <label>Address<input type="text" name="address" maxlength="200" placeholder="Street, city, ZIP" value="<?= $addVal('address') ?>"></label>
            <label>Course
              <select name="course">
                <?php foreach (STUDENT_COURSES as $c): ?>
                  <option value="<?= h($c) ?>" <?= $selCourse === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
                <?php if ($selCourse !== '' && !in_array($selCourse, STUDENT_COURSES, true)): ?>
                  <option value="<?= h($selCourse) ?>" selected><?= h($selCourse) ?></option>
                <?php endif; ?>
              </select>
            </label>
            <label>Status
              <select name="status">
                <?php foreach (STUDENT_STATUSES as $k => $label): ?>
                  <option value="<?= $k ?>" <?= $selStatus === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Start date<input type="date" name="start" value="<?= $addVal('start') ?>"></label>
            <label>Duration<input type="text" name="duration" maxlength="80" placeholder="e.g. 6 weeks, or Jul 8 to Aug 15" value="<?= $addVal('duration') ?>"></label>
            <label class="span2">Notes<input type="text" name="notes" maxlength="500" placeholder="Anything worth remembering" value="<?= $addVal('notes') ?>"></label>
            <div class="span2 form-actions"><button type="submit">Add student</button></div>
          </form>
        </details>

        <form method="get" class="toolbar" action="">
          <input type="hidden" name="view" value="students">
          <input type="search" name="q" value="<?= h($sQuery) ?>" placeholder="Search name, phone, email">
          <select name="course">
            <option value="all">All courses</option>
            <?php foreach (STUDENT_COURSES as $c): ?>
              <option value="<?= h($c) ?>" <?= $sCourse === $c ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Filter</button>
          <span class="toolbar-spacer"></span>
          <a class="btn-ghost" href="?view=students&amp;print=1<?= $sCourse !== 'all' ? '&amp;course=' . h(urlencode($sCourse)) : '' ?><?= $sQuery !== '' ? '&amp;q=' . h(urlencode($sQuery)) : '' ?>" target="_blank">Print roster</a>
          <a class="btn-ghost" href="?action=students_csv<?= $sCourse !== 'all' ? '&amp;course=' . h(urlencode($sCourse)) : '' ?><?= $sQuery !== '' ? '&amp;q=' . h(urlencode($sQuery)) : '' ?>">Export CSV</a>
        </form>

        <?php if (!$shownStudents): ?>
          <section class="card"><p class="empty"><?= $students ? 'No students match this filter.' : 'No students yet. Add your first student above, or copy one over from Leads &amp; Bookings.' ?></p></section>
        <?php endif; ?>

        <?php foreach ($shownStudents as $s): ?>
          <section class="card lead">
            <div class="lead-top">
              <span class="lead-id">#<?= (int) $s['id'] ?></span>
              <h2 class="lead-name"><?= h($s['name']) ?></h2>
              <span class="status st-<?= h($s['status']) ?>"><?= h(STUDENT_STATUSES[$s['status']] ?? $s['status']) ?></span>
            </div>
            <dl class="lead-grid">
              <div><dt>Course</dt><dd><?= h($s['course'] ?: 'not set') ?></dd></div>
              <div><dt>Mobile</dt><dd><?= $s['mobile'] ? '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $s['mobile'])) . '">' . h($s['mobile']) . '</a>' : 'not set' ?></dd></div>
              <div><dt>Email</dt><dd><?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : 'not set' ?></dd></div>
              <div><dt>Address</dt><dd><?= h($s['address'] ?: 'not set') ?></dd></div>
              <div><dt>Start</dt><dd><?= h($s['start'] ?: 'not set') ?></dd></div>
              <div><dt>Duration</dt><dd><?= h($s['duration'] ?: 'not set') ?></dd></div>
            </dl>
            <?php if (!empty($s['notes'])): ?><p class="lead-msg"><?= nl2br(h($s['notes'])) ?></p><?php endif; ?>
            <details class="edit-details">
              <summary>Edit</summary>
              <form method="post" class="form-grid">
                <input type="hidden" name="action" value="student_update">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <label>Full name *<input type="text" name="name" required maxlength="120" value="<?= h($s['name']) ?>"></label>
                <label>Mobile phone<input type="tel" name="mobile" maxlength="40" value="<?= h($s['mobile']) ?>"></label>
                <label>Email<input type="email" name="email" maxlength="120" value="<?= h($s['email']) ?>"></label>
                <label>Address<input type="text" name="address" maxlength="200" value="<?= h($s['address']) ?>"></label>
                <label>Course
                  <select name="course">
                    <?php foreach (STUDENT_COURSES as $c): ?>
                      <option value="<?= h($c) ?>" <?= $s['course'] === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                    <?php endforeach; ?>
                    <?php if ($s['course'] !== '' && !in_array($s['course'], STUDENT_COURSES, true)): ?>
                      <option value="<?= h($s['course']) ?>" selected><?= h($s['course']) ?></option>
                    <?php endif; ?>
                  </select>
                </label>
                <label>Status
                  <select name="status">
                    <?php foreach (STUDENT_STATUSES as $k => $label): ?>
                      <option value="<?= $k ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>Start date<input type="date" name="start" value="<?= h($s['start']) ?>"></label>
                <label>Duration<input type="text" name="duration" maxlength="80" value="<?= h($s['duration']) ?>"></label>
                <label class="span2">Notes<input type="text" name="notes" maxlength="500" value="<?= h($s['notes']) ?>"></label>
                <div class="span2 form-actions">
                  <button type="submit">Save changes</button>
                </div>
              </form>
              <form method="post" class="delete-form" onsubmit="return confirm('Remove <?= h(addslashes($s['name'])) ?> from students? This cannot be undone.');">
                <input type="hidden" name="action" value="student_delete">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button type="submit" class="btn-danger">Remove student</button>
              </form>
            </details>
          </section>
        <?php endforeach; ?>

      <?php elseif ($view === 'forms'): ?>
        <h1 class="page-title">Instructor Forms</h1>
        <p class="page-sub">Your private form library, behind this sign in. Upload every form you use once, then open or print them from any device.</p>

        <section class="card">
          <div class="card-head"><h2>Upload a form</h2><span class="card-note">PDF, Word, Excel, PNG, JPG &middot; up to 15 MB</span></div>
          <form method="post" enctype="multipart/form-data" class="upload-form">
            <input type="hidden" name="action" value="form_upload">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" required>
            <button type="submit">Upload</button>
          </form>
        </section>

        <?php
        $formFiles = [];
        foreach (glob(forms_dir() . '/*') ?: [] as $fp) {
            $fext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));
            if (is_file($fp) && isset(FORM_EXTENSIONS[$fext])) {
                $formFiles[] = ['name' => basename($fp), 'size' => (int) filesize($fp), 'mtime' => (int) filemtime($fp)];
            }
        }
        usort($formFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        ?>
        <section class="card">
          <div class="card-head"><h2>Your forms</h2><span class="card-note"><?= count($formFiles) ?> file(s)</span></div>
          <?php if (!$formFiles): ?>
            <p class="empty">Nothing uploaded yet. Add your sign in sheets, skills checklists, state forms, and handouts above; they stay private to this dashboard.</p>
          <?php else: ?>
            <ul class="files">
              <?php foreach ($formFiles as $ff): ?>
                <li class="file-row">
                  <a class="file-name" href="?action=form_dl&amp;f=<?= h(urlencode($ff['name'])) ?>" target="_blank"><?= h($ff['name']) ?></a>
                  <span class="file-meta"><?= $ff['size'] >= 1048576 ? round($ff['size'] / 1048576, 1) . ' MB' : max(1, (int) round($ff['size'] / 1024)) . ' KB' ?> &middot; <?= h(gmdate('M j, Y', $ff['mtime'])) ?></span>
                  <form method="post" class="delete-form-inline" onsubmit="return confirm('Delete <?= h(addslashes($ff['name'])) ?>?');">
                    <input type="hidden" name="action" value="form_delete">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="f" value="<?= h($ff['name']) ?>">
                    <button type="submit" class="btn-danger">Delete</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="card">
          <div class="card-head"><h2>Official state forms</h2></div>
          <ul class="quick">
            <li><a href="https://www.cdph.ca.gov/CDPH%20Document%20Library/ControlledForms/cdph278D.pdf" target="_blank" rel="noopener">CDPH 278D: CNA in-service record (fillable PDF, type before printing)</a></li>
            <li><a href="https://www.cdph.ca.gov/Programs/CHCQ/LCP/Pages/CNA.aspx" target="_blank" rel="noopener">CDPH CNA program page (all state forms)</a></li>
          </ul>
        </section>

      <?php else: /* status */ ?>
        <h1 class="page-title">Site Status</h1>
        <p class="page-sub">Health of the pieces this site depends on. If you can read this page, the web server itself is up.</p>

        <?php
        $lastVisitTs = 0;
        foreach (array_reverse($days30, true) as $d => $r) {
            if ($r['m'] + $r['t'] + $r['d'] > 0) { $lastVisitTs = strtotime($d . ' 12:00 UTC'); break; }
        }
        $lastLeadTs = $leads ? (int) $leads[0]['ts'] : 0;
        $free = @disk_free_space(__DIR__); $total = @disk_total_space(__DIR__);
        $freePct = ($free && $total) ? round(100 * $free / $total) : null;
        $mailLog = @file_exists(store_path('mail.log')) ? trim((string) @file_get_contents(store_path('mail.log'))) : '';
        $mailFails = $mailLog === '' ? 0 : substr_count($mailLog, "\n") + 1;
        $smsLog = @file_exists(store_path('sms.log')) ? trim((string) @file_get_contents(store_path('sms.log'))) : '';
        $smsLines = $smsLog === '' ? [] : explode("\n", $smsLog);
        $smsFails = count(array_filter($smsLines, fn($l) => str_contains($l, 'FAILED')));
        $smsLast = $smsLines ? substr((string) end($smsLines), 0, 90) : '';
        $checks = [
            ['Web server', 'ok', 'Serving pages (you are reading one).'],
            ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'warn', 'Version ' . PHP_VERSION],
            ['Data storage', is_writable(NCI_DATA_DIR) ? 'ok' : 'err', is_writable(NCI_DATA_DIR) ? 'Leads and analytics are being saved.' : 'data/ directory is NOT writable.'],
            ['Email function', !function_exists('mail') ? 'err' : ($mailFails ? 'warn' : 'ok'), function_exists('mail') ? ($mailFails ? $mailFails . ' delivery failures logged; check data/mail.log.' : 'Available; no delivery failures logged.') : 'mail() missing.'],
            ['Text message alerts', NCI_SMS['enabled'] ? ($smsFails ? 'warn' : 'ok') : 'warn', NCI_SMS['enabled'] ? (count(NCI_SMS['numbers']) . ' number(s) on alert list' . ($smsFails ? '; ' . $smsFails . ' failures logged, check data/sms.log.' : ($smsLast !== '' ? '; last: ' . $smsLast : '; none sent yet.'))) : 'Disabled: add the Textbelt key to php/sms.key.'],
            ['HTTPS', $secure ? 'ok' : 'warn', $secure ? 'Connection is encrypted.' : 'Not detected on this request.'],
            ['Disk space', ($freePct === null || $freePct > 10) ? 'ok' : 'warn', $freePct === null ? 'Not reported by host.' : $freePct . '% free.'],
            ['Latest visitor', 'info', $lastVisitTs ? fmt_ago($lastVisitTs) : 'No visits recorded yet.'],
            ['Latest booking request', 'info', $lastLeadTs ? fmt_ago($lastLeadTs) : 'None yet.'],
        ];
        ?>
        <section class="card">
          <ul class="checks">
            <?php foreach ($checks as [$name, $state, $detail]): ?>
              <li class="check check-<?= $state ?>">
                <span class="check-dot" aria-hidden="true"></span>
                <span class="check-name"><?= h($name) ?></span>
                <span class="check-detail"><?= h($detail) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <section class="card">
          <div class="card-head"><h2>Quick links</h2></div>
          <ul class="quick">
            <li><a href="/" target="_blank" rel="noopener">Open the live website</a></li>
            <li><a href="https://pagespeed.web.dev/analysis?url=https%3A%2F%2Fnuracareinstitute.com%2F" target="_blank" rel="noopener">Run PageSpeed Insights</a></li>
            <li><a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a></li>
            <li><a href="/sitemap.xml" target="_blank" rel="noopener">View sitemap.xml</a></li>
          </ul>
        </section>
      <?php endif; ?>

      <footer class="content-foot">Nura Care Institute admin. Do not share this address or your sign in details.</footer>
    </main>
  </div>
  <script src="/admin/app.js" defer></script>
<?php endif; ?>
</body>
</html>

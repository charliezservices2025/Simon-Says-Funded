<?php
declare(strict_types=1);

/**
 * Enrollment form handler for Nura Care Institute.
 *
 * Anti-spam layers, in order:
 *   1. Honeypot fields (silent success so bots learn nothing)
 *   2. Per-IP rate limiting
 *   3. Same-origin check when the browser sends Origin/Referer
 *   4. Signed human-verification challenge (HMAC, min/max fill time)
 *   5. Spam-content filtering (links, oversized messages)
 * Then validates and sanitizes the submission and emails the admissions inbox.
 */

require __DIR__ . '/antispam.php';

const RECIPIENT_EMAIL = 'education@nuracareinstitute.com';
const SITE_NAME = 'Nura Care Institute';

// Progressive enhancement: browsers without JS post here directly and expect
// a redirect; the fetch()-based form in main.js sends Accept: application/json.
$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(bool $ok, string $message, int $status = 200, ?string $code = null): void
{
    global $wantsJson;

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        $payload = ['ok' => $ok, 'message' => $message];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        echo json_encode($payload);
        exit;
    }

    $target = $ok ? '/thank-you/' : '/enroll/?error=1';
    header('Location: ' . $target, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'This endpoint only accepts form submissions.', 405);
}

// Layer 1: honeypots. If a hidden field is filled, silently report success
// so bots move on without learning anything.
if (!empty($_POST['website']) || !empty($_POST['company'])) {
    respond(true, 'Thank you.');
}

// Layer 2: per-IP rate limit (5 attempts per hour).
if (antispam_too_many_requests()) {
    respond(false, 'Too many requests from this connection. Please wait a while and try again, or call us at (916) 544-1256.', 429);
}

// Layer 3: same-origin check, applied only when the browser sent the header.
$originHeader = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($originHeader !== '') {
    $originHost = strtolower((string) (parse_url($originHeader, PHP_URL_HOST) ?? ''));
    $allowedHosts = ['nuracareinstitute.com', 'www.nuracareinstitute.com', 'localhost', '127.0.0.1'];
    if (!in_array($originHost, $allowedHosts, true)) {
        respond(false, 'Your request could not be verified. Please call us at (916) 544-1256.', 403);
    }
}

function clean_line(string $value): string
{
    $value = trim($value);
    // Strip header-injection attempts (newlines / carriage returns).
    return preg_replace('/[\r\n]+/', ' ', $value) ?? '';
}

// Layer 4: signed human-verification challenge issued by challenge.php.
$challengeTs = clean_line((string) ($_POST['challenge_ts'] ?? ''));
$challengeNonce = clean_line((string) ($_POST['challenge_nonce'] ?? ''));
$challengeSig = clean_line((string) ($_POST['challenge_sig'] ?? ''));
$humanAnswer = clean_line((string) ($_POST['human_answer'] ?? ''));

if ($challengeTs === '' || !ctype_digit($challengeTs) || $challengeNonce === '' || $challengeSig === ''
    || $humanAnswer === '' || !preg_match('/^\d{1,3}$/', $humanAnswer)) {
    respond(false, 'Please answer the spam-prevention question before submitting.', 422, 'challenge');
}

$expectedSig = hash_hmac('sha256', $challengeTs . '|' . $challengeNonce . '|' . (int) $humanAnswer, antispam_secret());
if (!hash_equals($expectedSig, $challengeSig)) {
    respond(false, 'The spam-prevention answer was not correct. Please answer the new question and submit again.', 422, 'challenge');
}

$challengeAge = time() - (int) $challengeTs;
if ($challengeAge < 5) {
    respond(false, 'That was submitted very quickly. Please take a moment to review your details and submit again.', 422, 'challenge');
}
if ($challengeAge > 7200) {
    respond(false, 'This page was open for a while, so the spam check expired. Please answer the new question and submit again.', 422, 'challenge');
}

$name = clean_line($_POST['name'] ?? '');
$phone = clean_line($_POST['phone'] ?? '');
$email = clean_line($_POST['email'] ?? '');
$course = clean_line($_POST['course'] ?? '');
$preferredStart = clean_line($_POST['preferred_start'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));

// Layer 5: spam-content filtering. Real enrollment questions do not need links.
$linkScan = $name . ' ' . $message;
if (preg_match('~https?://|www\.|\[url|<a\s~i', $linkScan)) {
    respond(false, 'Links are not allowed in this form. Please describe your question in plain text, or call us at (916) 544-1256.', 422);
}
$messageLen = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
if ($messageLen > 2000) {
    respond(false, 'Please keep your message under 2000 characters, or call us at (916) 544-1256.', 422);
}

$errors = [];

if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'a valid name';
}
if ($phone === '' || mb_strlen($phone) > 40) {
    $errors[] = 'a valid phone number';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'a valid email address';
}
if ($course === '') {
    $errors[] = 'a program selection';
}

if ($errors) {
    respond(false, 'Please provide ' . implode(', ', $errors) . '.', 422);
}

$messageBody = $message !== '' ? wordwrap($message, 70) : '(none provided)';

$body = "New enrollment request from the Nura Care Institute website\n\n"
    . "Name: {$name}\n"
    . "Phone: {$phone}\n"
    . "Email: {$email}\n"
    . "Program: {$course}\n"
    . "Preferred start: " . ($preferredStart !== '' ? $preferredStart : '(none provided)') . "\n\n"
    . "Message:\n{$messageBody}\n";

$subject = '=?UTF-8?B?' . base64_encode(SITE_NAME . ' enrollment request: ' . $name) . '?=';

$headers = [
    'From: ' . SITE_NAME . ' Website <no-reply@nuracareinstitute.com>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'X-Mailer: PHP/' . phpversion(),
    'Content-Type: text/plain; charset=UTF-8',
];

$sent = @mail(RECIPIENT_EMAIL, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    respond(false, 'We could not send your request right now. Please call us at (916) 544-1256.', 500);
}

respond(true, 'Thank you. Your enrollment request has been sent.');

<?php
declare(strict_types=1);

/**
 * Enrollment form handler for Nura Care Institute.
 * Validates and sanitizes the submission, then emails the admissions inbox.
 */

const RECIPIENT_EMAIL = 'education@nuracareinstitute.com';
const SITE_NAME = 'Nura Care Institute';

// Progressive enhancement: browsers without JS post here directly and expect
// a redirect; the fetch()-based form in main.js sends Accept: application/json.
$wantsJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(bool $ok, string $message, int $status = 200): void
{
    global $wantsJson;

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }

    $target = $ok ? '/thank-you/' : '/enroll/?error=1';
    header('Location: ' . $target, true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'This endpoint only accepts form submissions.', 405);
}

// Honeypot: if filled, silently report success so bots move on without learning anything.
if (!empty($_POST['website'])) {
    respond(true, 'Thank you.');
}

function clean_line(string $value): string
{
    $value = trim($value);
    // Strip header-injection attempts (newlines / carriage returns).
    return preg_replace('/[\r\n]+/', ' ', $value) ?? '';
}

$name = clean_line($_POST['name'] ?? '');
$phone = clean_line($_POST['phone'] ?? '');
$email = clean_line($_POST['email'] ?? '');
$course = clean_line($_POST['course'] ?? '');
$preferredStart = clean_line($_POST['preferred_start'] ?? '');
$message = trim((string) ($_POST['message'] ?? ''));

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

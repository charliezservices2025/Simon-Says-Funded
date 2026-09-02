<?php
declare(strict_types=1);

/**
 * Issues a signed, time-stamped human-verification question for the
 * enrollment form. The signature binds the timestamp, a random nonce,
 * and the expected answer, so the server can verify the submission
 * without storing any state.
 */

require __DIR__ . '/antispam.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$a = random_int(2, 9);
$b = random_int(2, 9);

$words = [2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine'];

// Mix digits and number words so the question is trivial for people and
// slightly annoying for generic form-spam scripts.
if (random_int(0, 1) === 1) {
    $question = $words[$a] . ' plus ' . $b;
} else {
    $question = $a . ' plus ' . $words[$b];
}

$answer = $a + $b;
$ts = time();
$nonce = bin2hex(random_bytes(8));
$sig = hash_hmac('sha256', $ts . '|' . $nonce . '|' . $answer, antispam_secret());

echo json_encode(['ts' => $ts, 'nonce' => $nonce, 'q' => $question, 'sig' => $sig]);

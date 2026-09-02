<?php
declare(strict_types=1);

/**
 * Site configuration: admin accounts, notification targets, storage paths.
 *
 * To change a password: run
 *   php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
 * and replace the hash below.
 */

const NCI_DATA_DIR = __DIR__ . '/../data';

const NCI_ADMINS = [
    'tynesha' => [
        'name' => 'Tynesha Zacarias',
        'hash' => '$2y$12$1f2Yidd1Iml7n3jPI03Crev25D8ntRzLCuZQi9EFwZAMmiKeATJIO',
        'role' => 'superadmin',
    ],
    'charlie' => [
        'name' => 'Charlie',
        'hash' => '$2y$12$iIkp7Mmqd5Wnqxu/1pYVpOdY4IMLvhZ06O8O1BaEBv9v4SZFR5Dqq',
        'role' => 'superadmin',
    ],
];

// Every address here receives an email the moment a booking request arrives.
const NCI_NOTIFY_EMAILS = [
    'education@nuracareinstitute.com',
    'charliezservices@gmail.com',
];

const NCI_MAIL_FROM = 'Nura Care Institute Website <no-reply@nuracareinstitute.com>';
const NCI_ADMIN_URL = 'https://nuracareinstitute.com/admin/';

/**
 * Text message notifications via Textbelt (https://textbelt.com).
 *
 * The API key is read from php/sms.key, a one-line file that is blocked
 * from the web and kept out of version control. SMS enables itself when
 * a key is present and disables itself silently when the file is empty
 * or missing; email alerts always send either way.
 *
 * To top up or replace the key: buy credit at textbelt.com/purchase and
 * paste the new key into php/sms.key (nothing else on the line).
 * To check remaining credit: https://textbelt.com/quota/YOUR_KEY
 */
$nciSmsKey = is_readable(__DIR__ . '/sms.key')
    ? trim((string) file_get_contents(__DIR__ . '/sms.key'))
    : '';
define('NCI_SMS', [
    'enabled' => $nciSmsKey !== '',
    'provider' => 'textbelt',
    'key' => $nciSmsKey,
    // Everyone here is texted the moment a booking arrives.
    'numbers' => [
        '+19165441256',             // Tynesha Zacarias
    ],
]);
unset($nciSmsKey);

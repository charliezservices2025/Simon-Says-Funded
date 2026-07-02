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
 * Optional SMS notifications. Email alerts are always sent; to add true text
 * messages, create a Twilio (or Textbelt) account, fill these in, and set
 * enabled to true. Without credentials this is skipped silently.
 */
const NCI_SMS = [
    'enabled' => false,
    'provider' => 'textbelt',       // https://textbelt.com : POST phone/message/key
    'key' => '',
    'numbers' => ['+19165441256'],
];

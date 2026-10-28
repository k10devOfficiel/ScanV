<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'ScanV'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => strtolower((string) Env::get('APP_ENV', 'production')) === 'production' ? false : (bool) Env::get('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),

    'database' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', 3306),
        'database' => Env::get('DB_DATABASE', 'scanv'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'session' => [
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
        'secure' => (bool) Env::get('SESSION_SECURE_COOKIE', false),
        'name' => 'scanv_session',
    ],

    'key' => Env::get('APP_KEY', '94c8de5b1a3e8729f041b6cd385e02419a738fc9841b80c57193f45610bcde82'),

    'mail' => [
        'driver' => strtolower((string) Env::get('MAIL_DRIVER', 'log')),
        'host' => Env::get('MAIL_HOST', '127.0.0.1'),
        'port' => (int) Env::get('MAIL_PORT', 1025),
        'username' => Env::get('MAIL_USERNAME', ''),
        'password' => Env::get('MAIL_PASSWORD', ''),
        'encryption' => Env::get('MAIL_ENCRYPTION', ''),
        'from_address' => Env::get('MAIL_FROM_ADDRESS', 'noreply@scanv.test'),
        'from_name' => Env::get('MAIL_FROM_NAME', 'ScanV'),
    ],

    'otp' => [
        'ttl_minutes' => (int) Env::get('OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) Env::get('OTP_MAX_ATTEMPTS', 5),
        'resend_seconds' => (int) Env::get('OTP_RESEND_SECONDS', 60),
    ],
];

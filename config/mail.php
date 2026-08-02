<?php

declare(strict_types=1);

// Phase 15 (Notification & Email System, CMS-052). 'log' cho local/test (ghi noi dung mail vao
// storage/logs/mail.log, KHONG gui that), 'smtp' cho production (tu viet client, khong thu vien
// ngoai) - dung khuon config/cache.php (default + drivers[]).
return [
    'default' => getenv('MAIL_DRIVER') ?: 'log',
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com',
        'name' => getenv('MAIL_FROM_NAME') ?: 'CMS',
    ],
    'drivers' => [
        'log' => [
            'path' => dirname(__DIR__) . '/storage/logs/mail.log',
        ],
        'smtp' => [
            'host' => getenv('MAIL_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('MAIL_PORT') ?: 587),
            'username' => getenv('MAIL_USERNAME') ?: null,
            'password' => getenv('MAIL_PASSWORD') ?: null,
            'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        ],
    ],
];

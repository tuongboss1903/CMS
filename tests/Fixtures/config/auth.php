<?php

declare(strict_types=1);

// Fixture rieng cho Unit Test - gia tri co dinh, khong dung config/auth.php that (production).
return [
    'session' => [
        'cookie_name' => 'test_session',
        'lifetime_minutes' => 30,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ],
];

<?php

declare(strict_types=1);

return [
    'session' => [
        'cookie_name' => 'app_test_session',
        'lifetime_minutes' => 30,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ],
];

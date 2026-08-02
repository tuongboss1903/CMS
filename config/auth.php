<?php

declare(strict_types=1);

// Hybrid Auth da chot chinh thuc: Session cho Admin Panel (SSR), JWT Bearer cho /api/v1/*.
// Xem 02-module-auth.md va cms-architecture-proposal.md muc 10.
return [
    'session' => [
        'cookie_name' => getenv('SESSION_COOKIE') ?: 'cms_session',
        'lifetime_minutes' => (int) (getenv('SESSION_LIFETIME') ?: 120),
        'secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'http_only' => true,
        'same_site' => 'lax',
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: '',
        'algo' => 'HS256',
        'access_ttl' => (int) (getenv('JWT_ACCESS_TTL') ?: 3600),
        'refresh_ttl' => (int) (getenv('JWT_REFRESH_TTL') ?: 1209600),
    ],
    'password_reset' => [
        'token_ttl' => (int) (getenv('PASSWORD_RESET_TTL') ?: 3600),
    ],
    'login_throttle' => [
        'max_attempts' => (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5),
        'decay_seconds' => (int) (getenv('LOGIN_DECAY_SECONDS') ?: 900),
    ],
];

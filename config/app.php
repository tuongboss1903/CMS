<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'My CMS Project',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh',
    'locale' => getenv('APP_LOCALE') ?: 'vi',
    'key' => getenv('APP_KEY') ?: '',
    // Dung cho ca activeTheme lan defaultTheme cua View cho toi khi co TenantManager that (Phase 2+)
    // tu resolve theme rieng theo tung site - xem CMS-011 Quyet dinh #1.
    'theme' => getenv('APP_THEME') ?: 'default',
];

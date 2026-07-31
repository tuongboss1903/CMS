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
];

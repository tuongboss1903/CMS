<?php

declare(strict_types=1);

/**
 * Nap file .env o project root vao bien moi truong (putenv + $_ENV) neu ton tai. CHI set khi
 * bien do CHUA co san trong moi truong that (getenv() uu tien OS/shell that hon .env) - dung
 * cho local demo, khong tao dependency ngoai (khong vlucas/phpdotenv), parser thuan tuy.
 * Duoc require boi public/index.php va cac script trong bin/ TRUOC khi khoi tao Core\Config
 * (Config::loadAll() goi getenv() ngay luc construct).
 */
$envFile = \dirname(__DIR__) . '/.env';

if (!\is_file($envFile)) {
    return;
}

$lines = \file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

foreach ($lines as $line) {
    $line = \trim($line);

    if ($line === '' || \str_starts_with($line, '#')) {
        continue;
    }

    [$key, $value] = \array_pad(\explode('=', $line, 2), 2, '');
    $key = \trim($key);
    $value = \trim($value, " \t\n\r\0\x0B\"'");

    if ($key !== '' && \getenv($key) === false) {
        \putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}

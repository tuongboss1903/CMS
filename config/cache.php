<?php

declare(strict_types=1);

// Redis cho production, File cache cho local/dev - qua interface CacheDriver (xem cms-architecture-proposal.md muc 9).
// Prefix o day la namespace cap app; prefix theo tenant (tenant:{id}:...) do Cache class ap dung luc runtime.
return [
    'default' => getenv('CACHE_DRIVER') ?: 'file',
    'prefix' => getenv('CACHE_PREFIX') ?: 'cms',
    'drivers' => [
        'file' => [
            'path' => dirname(__DIR__) . '/storage/cache',
        ],
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => (int) (getenv('REDIS_DB') ?: 0),
        ],
    ],
];

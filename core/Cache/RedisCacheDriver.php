<?php

declare(strict_types=1);

namespace Core\Cache;

use Redis;
use RedisException;

/**
 * Doi hoi PHP extension "redis" (ext-redis) da cai tren server - quyet dinh chinh thuc uu tien
 * hieu nang cho production, KHONG dung composer package (predis/predis). Lazy connect (giong
 * Database::connect()) - chi ket noi khi thuc su can, khong o constructor.
 */
final class RedisCacheDriver implements CacheDriver
{
    private ?Redis $connection = null;

    /** @param array{host: string, port: int, password: string|null, database: int} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function get(string $key): mixed
    {
        $raw = $this->connection()->get($key);

        if ($raw === false) {
            return null;
        }

        return \unserialize($raw);
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds): void
    {
        $serialized = \serialize($value);

        if ($ttlSeconds === null) {
            $this->connection()->set($key, $serialized);

            return;
        }

        $this->connection()->setex($key, $ttlSeconds, $serialized);
    }

    public function has(string $key): bool
    {
        return $this->connection()->exists($key) > 0;
    }

    public function forget(string $key): void
    {
        $this->connection()->del($key);
    }

    public function flush(): void
    {
        $this->connection()->flushDB();
    }

    private function connection(): Redis
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        if (!\class_exists(Redis::class)) {
            throw new CacheException('PHP extension "redis" (ext-redis) chua duoc cai dat tren may nay.');
        }

        $redis = new Redis();

        try {
            if (!$redis->connect($this->config['host'], (int) $this->config['port'])) {
                throw new CacheException(\sprintf(
                    'Khong the ket noi Redis tai %s:%d.',
                    $this->config['host'],
                    (int) $this->config['port']
                ));
            }

            if (!empty($this->config['password']) && !$redis->auth($this->config['password'])) {
                throw new CacheException('Xac thuc Redis that bai (sai password).');
            }

            if (!$redis->select($this->config['database'])) {
                throw new CacheException('Khong the chon Redis database index.');
            }
        } catch (RedisException $exception) {
            throw new CacheException('Khong the ket noi Redis: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->connection = $redis;
    }
}

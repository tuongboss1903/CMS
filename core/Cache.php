<?php

declare(strict_types=1);

namespace Core;

use Closure;
use Core\Cache\CacheDriver;

/**
 * Facade duy nhat cho Cache Layer - modul/tang tren KHONG duoc goi CacheDriver truc tiep.
 * Ap dung prefix (namespace cap app tu config/cache.php), cung cap remember() (Object cache) va
 * TAG support (dat o day, khong o driver - xem ly do trong CacheDriver.php).
 *
 * Tenant key KHONG phai API rieng: caller (Repository tuong lai) tu ghep "tenant:{id}:..." vao
 * $key - dung nguyen tac da ap dung nhat quan cho Database/QueryBuilder (core trung lap voi
 * khai niem "tenant", chi business layer moi biet).
 */
final class Cache
{
    public function __construct(
        private readonly CacheDriver $driver,
        private readonly string $prefix = '',
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->driver->get($this->fullKey($key)) ?? $default;
    }

    /** @param list<string> $tags */
    public function put(string $key, mixed $value, ?int $ttl = null, array $tags = []): void
    {
        $fullKey = $this->fullKey($key);
        $this->driver->put($fullKey, $value, $ttl);

        foreach ($tags as $tag) {
            $this->addKeyToTag($tag, $fullKey);
        }
    }

    public function has(string $key): bool
    {
        return $this->driver->has($this->fullKey($key));
    }

    public function forget(string $key): void
    {
        $this->driver->forget($this->fullKey($key));
    }

    /** Lay hoac tinh-roi-luu - phuc vu Object cache (Repository boc ket qua query). */
    public function remember(string $key, ?int $ttl, Closure $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, $ttl);

        return $value;
    }

    public function flush(): void
    {
        $this->driver->flush();
    }

    /** @param list<string> $tags */
    public function flushTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $registryKey = $this->tagRegistryKey($tag);

            /** @var list<string> $members */
            $members = $this->driver->get($registryKey) ?? [];

            foreach ($members as $memberKey) {
                $this->driver->forget($memberKey);
            }

            $this->driver->forget($registryKey);
        }
    }

    private function addKeyToTag(string $tag, string $fullKey): void
    {
        $registryKey = $this->tagRegistryKey($tag);

        /** @var list<string> $members */
        $members = $this->driver->get($registryKey) ?? [];

        if (!\in_array($fullKey, $members, true)) {
            $members[] = $fullKey;
            $this->driver->put($registryKey, $members, null);
        }
    }

    private function tagRegistryKey(string $tag): string
    {
        return $this->fullKey('_tag:' . $tag);
    }

    private function fullKey(string $key): string
    {
        return $this->prefix === '' ? $key : $this->prefix . ':' . $key;
    }
}

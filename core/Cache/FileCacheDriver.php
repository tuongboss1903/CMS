<?php

declare(strict_types=1);

namespace Core\Cache;

/**
 * Luu moi entry thanh 1 file rieng duoi $path (config/cache.php: drivers.file.path).
 * Ten file la hash(key) - tranh path traversal/ky tu khong hop le neu key co chua '/', '..'...
 * Ghi ATOMIC (file tam + rename()) de tranh doc phai file dang ghi do khi nhieu PHP-FPM worker
 * cung ghi 1 key dong thoi - rui ro that voi traffic thuc, khong phai ly thuyet.
 */
final class FileCacheDriver implements CacheDriver
{
    private const EXTENSION = '.cache';

    public function __construct(private readonly string $path)
    {
    }

    public function get(string $key): mixed
    {
        $file = $this->pathFor($key);

        if (!\is_file($file)) {
            return null;
        }

        $raw = @\file_get_contents($file);

        if ($raw === false || $raw === '') {
            return null;
        }

        $entry = @\unserialize($raw);

        if (!\is_array($entry) || !\array_key_exists('value', $entry) || !\array_key_exists('expires_at', $entry)) {
            return null;
        }

        if ($entry['expires_at'] !== null && $entry['expires_at'] < \time()) {
            $this->forget($key);

            return null;
        }

        return $entry['value'];
    }

    public function put(string $key, mixed $value, ?int $ttlSeconds): void
    {
        $this->ensureDirectory();

        $entry = [
            'expires_at' => $ttlSeconds === null ? null : \time() + $ttlSeconds,
            'value' => $value,
        ];

        $file = $this->pathFor($key);
        $tmpFile = $file . '.' . \uniqid('', true) . '.tmp';

        if (\file_put_contents($tmpFile, \serialize($entry), LOCK_EX) === false) {
            throw new CacheException(\sprintf('Khong the ghi file cache tam thoi: "%s".', $tmpFile));
        }

        if (!\rename($tmpFile, $file)) {
            @\unlink($tmpFile);

            throw new CacheException(\sprintf('Khong the doi ten file cache tam thoi thanh "%s".', $file));
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): void
    {
        $file = $this->pathFor($key);

        if (\is_file($file)) {
            @\unlink($file);
        }
    }

    public function flush(): void
    {
        $files = \glob(\rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . '*' . self::EXTENSION) ?: [];

        foreach ($files as $file) {
            @\unlink($file);
        }
    }

    private function pathFor(string $key): string
    {
        return \rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . \hash('sha256', $key) . self::EXTENSION;
    }

    private function ensureDirectory(): void
    {
        if (\is_dir($this->path)) {
            return;
        }

        if (!\mkdir($this->path, 0775, true) && !\is_dir($this->path)) {
            throw new CacheException(\sprintf('Khong the tao thu muc cache: "%s".', $this->path));
        }
    }
}

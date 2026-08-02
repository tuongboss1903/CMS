<?php

declare(strict_types=1);

namespace Core;

/**
 * Dem so lan "hit" theo key trong 1 cua so thoi gian (decay), luu trong Session (namespace
 * rate_limit.{key}, gia tri {attempts:int, expires_at:int} - chi integer timestamp, khong
 * object/DateTime/serialize). Thuan logic, khong biet HTTP/Request/Response/Middleware/DB/Redis.
 */
final class RateLimiter
{
    private const NAMESPACE = 'rate_limit';

    public function __construct(private readonly Session $session)
    {
    }

    public function hit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $now = \time();
        $data = $this->read($key);

        if ($data === null || $data['expires_at'] <= $now) {
            $data = ['attempts' => 1, 'expires_at' => $now + $decaySeconds];
        } else {
            $data['attempts']++;
        }

        $this->write($key, $data);

        return $data['attempts'] <= $maxAttempts;
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $data = $this->read($key);

        if ($data === null || $data['expires_at'] <= \time()) {
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    public function attempts(string $key): int
    {
        $data = $this->read($key);

        if ($data === null || $data['expires_at'] <= \time()) {
            return 0;
        }

        return $data['attempts'];
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return \max(0, $maxAttempts - $this->attempts($key));
    }

    public function clear(string $key): void
    {
        $this->session->remove(self::NAMESPACE . ".{$key}");
    }

    public function availableIn(string $key): int
    {
        $data = $this->read($key);

        if ($data === null) {
            return 0;
        }

        return \max(0, $data['expires_at'] - \time());
    }

    /** @return array{attempts: int, expires_at: int}|null */
    private function read(string $key): ?array
    {
        return $this->session->get(self::NAMESPACE . ".{$key}");
    }

    /** @param array{attempts: int, expires_at: int} $data */
    private function write(string $key, array $data): void
    {
        $this->session->set(self::NAMESPACE . ".{$key}", $data);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Core\Cache;

use Core\Cache\CacheException;
use Core\Cache\RedisCacheDriver;
use PHPUnit\Framework\TestCase;
use Redis;

/**
 * Test co dieu kien: tu markTestSkipped() neu moi truong khong co extension "redis" hoac khong
 * ket noi duoc Redis server that - khong fail suite chi vi thieu ha tang, dung tinh than da
 * xac nhan (khac SQLite-cho-Database vi Redis khong co che do "in-memory" tuong duong don gian).
 */
final class RedisCacheDriverTest extends TestCase
{
    private RedisCacheDriver $driver;

    protected function setUp(): void
    {
        if (!\class_exists(Redis::class)) {
            self::markTestSkipped('PHP extension "redis" khong duoc cai dat trong moi truong nay.');
        }

        $this->driver = new RedisCacheDriver([
            'host' => \getenv('REDIS_TEST_HOST') ?: '127.0.0.1',
            'port' => (int) (\getenv('REDIS_TEST_PORT') ?: 6379),
            'password' => null,
            'database' => 15,
        ]);

        try {
            $this->driver->flush();
        } catch (CacheException $exception) {
            self::markTestSkipped('Khong ket noi duoc Redis server that: ' . $exception->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->driver)) {
            try {
                $this->driver->flush();
            } catch (CacheException) {
                // Bo qua - moi truong co the da mat ket noi giua chung.
            }
        }
    }

    public function testPutAndGetRoundTripsValue(): void
    {
        $this->driver->put('foo', ['a' => 1], null);

        self::assertSame(['a' => 1], $this->driver->get('foo'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->driver->get('missing'));
    }

    public function testHasAndForget(): void
    {
        $this->driver->put('foo', 'bar', null);

        self::assertTrue($this->driver->has('foo'));

        $this->driver->forget('foo');

        self::assertFalse($this->driver->has('foo'));
    }

    public function testTtlExpiry(): void
    {
        $this->driver->put('foo', 'bar', 1);

        self::assertTrue($this->driver->has('foo'));

        \sleep(2);

        self::assertFalse($this->driver->has('foo'));
    }
}

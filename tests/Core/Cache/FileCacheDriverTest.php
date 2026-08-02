<?php

declare(strict_types=1);

namespace Tests\Core\Cache;

use Core\Cache\FileCacheDriver;
use PHPUnit\Framework\TestCase;

final class FileCacheDriverTest extends TestCase
{
    private string $path;
    private FileCacheDriver $driver;

    protected function setUp(): void
    {
        $this->path = \sys_get_temp_dir() . '/cms-cache-test-' . \uniqid('', true);
        $this->driver = new FileCacheDriver($this->path);
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->path)) {
            foreach (\glob($this->path . '/*') ?: [] as $file) {
                @\unlink($file);
            }

            @\rmdir($this->path);
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

    public function testHasReturnsTrueFalseCorrectly(): void
    {
        $this->driver->put('foo', 'bar', null);

        self::assertTrue($this->driver->has('foo'));
        self::assertFalse($this->driver->has('missing'));
    }

    public function testForgetRemovesKey(): void
    {
        $this->driver->put('foo', 'bar', null);
        $this->driver->forget('foo');

        self::assertNull($this->driver->get('foo'));
    }

    public function testFlushRemovesAllKeys(): void
    {
        $this->driver->put('a', 1, null);
        $this->driver->put('b', 2, null);

        $this->driver->flush();

        self::assertNull($this->driver->get('a'));
        self::assertNull($this->driver->get('b'));
    }

    public function testExpiredEntryIsTreatedAsMissAndCleanedUp(): void
    {
        // ttl am -> expires_at da o qua khu ngay khi ghi.
        $this->driver->put('foo', 'bar', -1);

        self::assertNull($this->driver->get('foo'));
        self::assertFalse($this->driver->has('foo'));
    }

    public function testEntryWithoutTtlNeverExpires(): void
    {
        $this->driver->put('foo', 'bar', null);

        self::assertSame('bar', $this->driver->get('foo'));
    }

    public function testKeysWithSpecialCharactersAreSafeAsFilenames(): void
    {
        $key = 'tenant:3:posts/list?x=1..%2Fetc';

        $this->driver->put($key, 'value', null);

        self::assertSame('value', $this->driver->get($key));
    }
}

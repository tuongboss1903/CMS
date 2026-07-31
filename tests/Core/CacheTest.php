<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\FileCacheDriver;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    private string $path;
    private Cache $cache;

    protected function setUp(): void
    {
        $this->path = \sys_get_temp_dir() . '/cms-cache-facade-test-' . \uniqid('', true);
        $this->cache = new Cache(new FileCacheDriver($this->path), 'cms');
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

    public function testPutAndGetAppliesPrefixTransparently(): void
    {
        $this->cache->put('foo', 'bar');

        self::assertSame('bar', $this->cache->get('foo'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        self::assertSame('default', $this->cache->get('missing', 'default'));
    }

    public function testHasAndForget(): void
    {
        $this->cache->put('foo', 'bar');

        self::assertTrue($this->cache->has('foo'));

        $this->cache->forget('foo');

        self::assertFalse($this->cache->has('foo'));
    }

    public function testRememberComputesOnceAndCachesResult(): void
    {
        $calls = 0;
        $callback = function () use (&$calls): string {
            $calls++;

            return 'computed';
        };

        $first = $this->cache->remember('key', 60, $callback);
        $second = $this->cache->remember('key', 60, $callback);

        self::assertSame('computed', $first);
        self::assertSame('computed', $second);
        self::assertSame(1, $calls);
    }

    public function testFlushClearsEverything(): void
    {
        $this->cache->put('a', 1);
        $this->cache->put('b', 2);

        $this->cache->flush();

        self::assertNull($this->cache->get('a'));
        self::assertNull($this->cache->get('b'));
    }

    public function testFlushTagsRemovesOnlyTaggedKeys(): void
    {
        $this->cache->put('post:1', 'A', null, ['posts', 'tenant:1']);
        $this->cache->put('post:2', 'B', null, ['posts', 'tenant:1']);
        $this->cache->put('page:1', 'C', null, ['pages']);

        $this->cache->flushTags(['posts']);

        self::assertNull($this->cache->get('post:1'));
        self::assertNull($this->cache->get('post:2'));
        self::assertSame('C', $this->cache->get('page:1'));
    }

    public function testFlushTagsWithMultipleTagsClearsUnionOfKeys(): void
    {
        $this->cache->put('post:1', 'A', null, ['posts']);
        $this->cache->put('page:1', 'B', null, ['pages']);

        $this->cache->flushTags(['posts', 'pages']);

        self::assertNull($this->cache->get('post:1'));
        self::assertNull($this->cache->get('page:1'));
    }

    public function testPuttingSameKeyUnderSameTagTwiceDoesNotDuplicateRegistryEntry(): void
    {
        $this->cache->put('post:1', 'A', null, ['posts']);
        $this->cache->put('post:1', 'A-updated', null, ['posts']);

        self::assertSame('A-updated', $this->cache->get('post:1'));

        $this->cache->flushTags(['posts']);

        self::assertNull($this->cache->get('post:1'));
    }
}

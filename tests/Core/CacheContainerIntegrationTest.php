<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Regression: Cache (CMS-008) phai rap dung qua Container (CMS-003) - bind CacheDriver interface
 * toi FileCacheDriver bang Closure (giong pattern Config da dung: tham so constructor la scalar
 * nen khong the auto-wire truc tiep, phai bind() thu cong).
 */
final class CacheContainerIntegrationTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = \sys_get_temp_dir() . '/cms-cache-container-test-' . \uniqid('', true);
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

    public function testCacheCanBeResolvedThroughContainerAsSingleton(): void
    {
        $container = new Container();

        $container->singleton(CacheDriver::class, fn (): CacheDriver => new FileCacheDriver($this->path));
        $container->singleton(Cache::class, fn (Container $c): Cache => new Cache($c->get(CacheDriver::class), 'cms'));

        $cache = $container->get(Cache::class);

        self::assertInstanceOf(Cache::class, $cache);

        $cache->put('foo', 'bar');

        self::assertSame('bar', $cache->get('foo'));
        self::assertSame($cache, $container->get(Cache::class));
    }
}

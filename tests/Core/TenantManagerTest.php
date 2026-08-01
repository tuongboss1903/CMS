<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\TenantManager;
use PHPUnit\Framework\TestCase;

final class TenantManagerTest extends TestCase
{
    public function testCheckReturnsFalseBeforeSetCurrent(): void
    {
        $tenantManager = new TenantManager();

        self::assertFalse($tenantManager->check());
    }

    public function testCheckReturnsTrueAfterSetCurrent(): void
    {
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1);

        self::assertTrue($tenantManager->check());
    }

    public function testIdReturnsNullBeforeSetCurrent(): void
    {
        $tenantManager = new TenantManager();

        self::assertNull($tenantManager->id());
    }

    public function testIdReturnsStoredTenantId(): void
    {
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(42);

        self::assertSame(42, $tenantManager->id());
    }

    public function testIdAcceptsStringTenantId(): void
    {
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent('site-abc');

        self::assertSame('site-abc', $tenantManager->id());
    }

    public function testCurrentReturnsNullBeforeSetCurrent(): void
    {
        $tenantManager = new TenantManager();

        self::assertNull($tenantManager->current());
    }

    public function testCurrentReturnsStoredData(): void
    {
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1, ['name' => 'Site A', 'theme_active' => 'default']);

        self::assertSame(['name' => 'Site A', 'theme_active' => 'default'], $tenantManager->current());
    }

    public function testSetCurrentWithEmptyDataArrayReturnsEmptyArrayNotNull(): void
    {
        $tenantManager = new TenantManager();
        $tenantManager->setCurrent(1);

        self::assertSame([], $tenantManager->current());
        self::assertNotNull($tenantManager->current());
    }

    public function testStateIsIsolatedPerInstance(): void
    {
        $first = new TenantManager();
        $second = new TenantManager();

        $first->setCurrent(1, ['name' => 'Site A']);

        self::assertTrue($first->check());
        self::assertFalse($second->check());
        self::assertNull($second->id());
        self::assertNull($second->current());
    }
}

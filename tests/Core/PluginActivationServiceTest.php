<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Database;
use Core\PluginActivationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit/Integration test cho Phase 19 (CMS-056) - dong Technical Debt #9. Database SQLite in-memory
 * that, Cache qua FileCacheDriver that (temp dir rieng, khong mock) - dung tien le AdminSettingTest.
 */
final class PluginActivationServiceTest extends TestCase
{
    private Database $database;
    private PluginActivationService $service;
    private string $cachePath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-plugin-activation-' . \uniqid('', true);

        $cache = new Cache(new FileCacheDriver($this->cachePath));
        $this->service = new PluginActivationService($cache, $this->database);

        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL
        )');
        $this->database->statement('CREATE TABLE site_plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            plugin_key VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 0,
            activated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )');
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->cachePath)) {
            $this->removeDirectory($this->cachePath);
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (\glob($path . '/*') ?: [] as $file) {
            \is_dir($file) ? $this->removeDirectory($file) : @\unlink($file);
        }

        @\rmdir($path);
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    public function testIsActiveReturnsFalseByDefault(): void
    {
        $siteId = $this->seedSite();

        self::assertFalse($this->service->isActive($siteId, 'ecommerce'));
    }

    public function testActivateMarksPluginActiveForTenant(): void
    {
        $siteId = $this->seedSite();

        $this->service->activate($siteId, 'ecommerce');

        self::assertTrue($this->service->isActive($siteId, 'ecommerce'));

        $row = $this->database->selectOne('SELECT * FROM site_plugins WHERE tenant_id = ? AND plugin_key = ?', [$siteId, 'ecommerce']);
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['is_active']);
        self::assertNotNull($row['activated_at']);
    }

    public function testDeactivateMarksPluginInactive(): void
    {
        $siteId = $this->seedSite();
        $this->service->activate($siteId, 'ecommerce');

        $this->service->deactivate($siteId, 'ecommerce');

        self::assertFalse($this->service->isActive($siteId, 'ecommerce'));
    }

    public function testEnabledKeysForReturnsOnlyActiveKeysForTenant(): void
    {
        $siteId = $this->seedSite();
        $this->service->activate($siteId, 'ecommerce');
        $this->service->activate($siteId, 'seo-booster');
        $this->service->deactivate($siteId, 'seo-booster');

        self::assertSame(['ecommerce'], $this->service->enabledKeysFor($siteId));
    }

    public function testActivationIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');

        $this->service->activate($siteA, 'ecommerce');

        self::assertTrue($this->service->isActive($siteA, 'ecommerce'));
        self::assertFalse($this->service->isActive($siteB, 'ecommerce'));
    }

    public function testEnabledKeysForIsCachedUntilActivationChanges(): void
    {
        $siteId = $this->seedSite();
        $this->service->activate($siteId, 'ecommerce');

        self::assertSame(['ecommerce'], $this->service->enabledKeysFor($siteId));

        // Ghi truc tiep xuong DB, khong qua service - gia lap du lieu thay doi ma cache chua biet.
        $this->database->insert(
            'INSERT INTO site_plugins (tenant_id, plugin_key, is_active) VALUES (?, ?, ?)',
            [$siteId, 'seo-booster', 1]
        );

        self::assertSame(['ecommerce'], $this->service->enabledKeysFor($siteId), 'Cache phai giu gia tri cu cho toi khi service tu invalidate.');

        $this->service->activate($siteId, 'seo-booster');

        self::assertSame(['ecommerce', 'seo-booster'], $this->service->enabledKeysFor($siteId));
    }

    public function testActivateIsIdempotent(): void
    {
        $siteId = $this->seedSite();

        $this->service->activate($siteId, 'ecommerce');
        $this->service->activate($siteId, 'ecommerce');

        $rows = $this->database->select('SELECT * FROM site_plugins WHERE tenant_id = ? AND plugin_key = ?', [$siteId, 'ecommerce']);
        self::assertCount(1, $rows);
    }

    public function testDeactivateBeforeActivateIsNoop(): void
    {
        $siteId = $this->seedSite();

        $this->service->deactivate($siteId, 'ecommerce');

        self::assertFalse($this->service->isActive($siteId, 'ecommerce'));
    }
}

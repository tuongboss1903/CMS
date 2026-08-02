<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Database;
use Core\TenantManager;
use Modules\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit test cho Phase 17 (System Settings & General Configurations, CMS-054) -
 * Modules\Settings\SettingManager. Dung Database SQLite in-memory that + Core\Cache voi
 * FileCacheDriver that (temp dir rieng moi test - khong dung chung state giua cac test method).
 */
final class SettingManagerTest extends TestCase
{
    private Database $database;
    private TenantManager $tenantManager;
    private SettingManager $settingManager;
    private string $cachePath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->database = new Database($config);
        $this->tenantManager = new TenantManager();
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-cache-' . \uniqid('', true);

        $cache = new Cache(new FileCacheDriver($this->cachePath));
        $this->settingManager = new SettingManager($cache, $config, $this->database, $this->tenantManager);

        $this->migrate();
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

    private function migrate(): void
    {
        $this->database->statement("CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
            `key` VARCHAR(100) NOT NULL,
            value TEXT NULL,
            is_encrypted BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL
        )");
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $this->tenantManager->setCurrent(1);

        self::assertSame('fallback', $this->settingManager->get('khong.ton.tai', 'fallback'));
        self::assertNull($this->settingManager->get('khong.ton.tai'));
    }

    public function testSetThenGetReturnsStoredValue(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->settingManager->set('site.tagline', 'Hello World');

        self::assertSame('Hello World', $this->settingManager->get('site.tagline'));
    }

    public function testGetReadsFromCacheOnSecondCall(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('cache.test', 'gia-tri-goc');

        self::assertSame('gia-tri-goc', $this->settingManager->get('cache.test'));

        // Xoa thang truc tiep khoi DB (khong qua forget()) - neu get() lan 2 van tra dung gia tri,
        // chung to da doc tu Cache chu khong phai Database.
        $this->database->statement('DELETE FROM settings WHERE `key` = ?', ['cache.test']);

        self::assertSame('gia-tri-goc', $this->settingManager->get('cache.test'));
    }

    public function testSetInvalidatesCacheSoSubsequentGetReflectsNewValue(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('cache.invalidate', 'gia-tri-1');
        self::assertSame('gia-tri-1', $this->settingManager->get('cache.invalidate'));

        $this->settingManager->set('cache.invalidate', 'gia-tri-2');

        self::assertSame('gia-tri-2', $this->settingManager->get('cache.invalidate'));
    }

    public function testForgetRemovesSettingAndCache(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('to.xoa', 'gia-tri');
        self::assertSame('gia-tri', $this->settingManager->get('to.xoa'));

        $this->settingManager->forget('to.xoa');

        self::assertNull($this->settingManager->get('to.xoa'));
        self::assertNull($this->database->selectOne('SELECT id FROM settings WHERE `key` = ?', ['to.xoa']));
    }

    public function testEncryptedValueIsStoredDifferentFromPlainInDatabase(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->settingManager->set('mail.smtp_password', 'sieu-bi-mat-123', 'mail', true);

        $row = $this->database->selectOne('SELECT value FROM settings WHERE `key` = ?', ['mail.smtp_password']);
        self::assertNotNull($row);
        self::assertNotSame('sieu-bi-mat-123', $row['value']);
    }

    public function testEncryptedValueDecryptsCorrectlyOnGet(): void
    {
        $this->tenantManager->setCurrent(1);

        $this->settingManager->set('mail.smtp_password', 'sieu-bi-mat-123', 'mail', true);

        self::assertSame('sieu-bi-mat-123', $this->settingManager->get('mail.smtp_password'));
    }

    public function testGetGroupReturnsAllKeysInGroupDecrypted(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('mail.host', 'smtp.example.com', 'mail');
        $this->settingManager->set('mail.password', 'bi-mat', 'mail', true);
        $this->settingManager->set('site.name', 'Khong lien quan', 'general');

        $group = $this->settingManager->getGroup('mail');

        self::assertCount(2, $group);
        self::assertSame('smtp.example.com', $group['mail.host']);
        self::assertSame('bi-mat', $group['mail.password']);
        self::assertArrayNotHasKey('site.name', $group);
    }

    public function testSettingsAreIsolatedPerTenant(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('shared.key', 'gia-tri-tenant-1');

        $this->tenantManager->setCurrent(2);

        self::assertNull($this->settingManager->get('shared.key'));
    }

    public function testSetOverwritesExistingKeySameTenant(): void
    {
        $this->tenantManager->setCurrent(1);
        $this->settingManager->set('overwrite.key', 'ban-dau');
        $this->settingManager->set('overwrite.key', 'da-cap-nhat');

        self::assertSame('da-cap-nhat', $this->settingManager->get('overwrite.key'));

        $rows = $this->database->select('SELECT * FROM settings WHERE `key` = ?', ['overwrite.key']);
        self::assertCount(1, $rows);
    }
}

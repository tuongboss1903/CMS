<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 17 (System Settings & General Configurations, CMS-054) - man hinh
 * Modules\Admin\SystemSetting{List,Save,Delete}Controller.php. Dung dung pattern actingAs()
 * Session-based cua AdminCommentModerationTest.php.
 *
 * Modules\Settings\SettingManager phu thuoc Core\Cache -> Core\Cache\CacheDriver la INTERFACE,
 * Container KHONG the tu auto-wire (cung bai hoc voi MailerDriver o Phase 15) - phai dang ky
 * tuong minh FileCacheDriver tro toi temp dir rieng cho test nay.
 */
final class AdminSettingTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private string $cachePath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-admin-setting-cache-' . \uniqid('', true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
        );
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'admin']);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];

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
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
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

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedSetting(int $tenantId, string $key, string $value, string $group = 'general', bool $encrypted = false): int
    {
        $this->database->insert(
            'INSERT INTO settings (tenant_id, setting_group, `key`, value, is_encrypted) VALUES (?, ?, ?, ?, ?)',
            [$tenantId, $group, $key, $value, $encrypted ? 1 : 0]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    public function testListRendersSettingsGroupedByGroup(): void
    {
        $siteId = $this->seedSite();
        $this->seedSetting($siteId, 'site.tagline', 'Xin chao', 'general');
        $this->actingAs($siteId, ['settings.manage']);

        $response = $this->router->dispatch(new Request('GET', '/admin/system-settings', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('site.tagline', $response->getBody());
        self::assertStringContainsString('Xin chao', $response->getBody());
    }

    public function testListMasksEncryptedValues(): void
    {
        $siteId = $this->seedSite();
        $this->seedSetting($siteId, 'mail.password', 'ma-hoa-that-day', 'mail', true);
        $this->actingAs($siteId, ['settings.manage']);

        $response = $this->router->dispatch(new Request('GET', '/admin/system-settings', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('********', $response->getBody());
        self::assertStringNotContainsString('ma-hoa-that-day', $response->getBody());
    }

    public function testListRequiresSettingsManagePermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/system-settings', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testSaveCreatesNewSetting(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['settings.manage']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/system-settings',
            'example.com',
            [],
            ['key' => 'moderation.auto_approve', 'setting_group' => 'moderation', 'value' => '0', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT * FROM settings WHERE `key` = ?', ['moderation.auto_approve']);
        self::assertNotNull($row);
        self::assertSame('moderation', $row['setting_group']);
        self::assertSame($siteId, (int) $row['tenant_id']);
    }

    public function testSaveUpdatesExistingSetting(): void
    {
        $siteId = $this->seedSite();
        $this->seedSetting($siteId, 'site.tagline', 'Cu', 'general');
        $this->actingAs($siteId, ['settings.manage']);

        $this->router->dispatch(new Request(
            'POST',
            '/admin/system-settings',
            'example.com',
            [],
            ['key' => 'site.tagline', 'setting_group' => 'general', 'value' => 'Moi', '_token' => $this->csrfToken()]
        ));

        $rows = $this->database->select('SELECT * FROM settings WHERE `key` = ?', ['site.tagline']);
        self::assertCount(1, $rows);
        self::assertSame('Moi', $rows[0]['value']);
    }

    public function testSaveEncryptedSettingStoresNonPlainValue(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['settings.manage']);

        $this->router->dispatch(new Request(
            'POST',
            '/admin/system-settings',
            'example.com',
            [],
            [
                'key' => 'mail.smtp_password',
                'setting_group' => 'mail',
                'value' => 'mat-khau-that',
                'is_encrypted' => '1',
                '_token' => $this->csrfToken(),
            ]
        ));

        $row = $this->database->selectOne('SELECT value, is_encrypted FROM settings WHERE `key` = ?', ['mail.smtp_password']);
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['is_encrypted']);
        self::assertNotSame('mat-khau-that', $row['value']);
    }

    public function testSaveRequiresSettingsManagePermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/system-settings',
            'example.com',
            [],
            ['key' => 'x', 'value' => 'y', '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteRemovesSetting(): void
    {
        $siteId = $this->seedSite();
        $settingId = $this->seedSetting($siteId, 'to.xoa', 'gia-tri');
        $this->actingAs($siteId, ['settings.manage']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/system-settings/{$settingId}/delete",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertNull($this->database->selectOne('SELECT id FROM settings WHERE id = ?', [$settingId]));
    }

    public function testDeleteCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $settingA = $this->seedSetting($siteA, 'tenant-a.key', 'gia-tri');

        $this->actingAs($siteB, ['settings.manage']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/system-settings/{$settingA}/delete",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
        self::assertNotNull($this->database->selectOne('SELECT id FROM settings WHERE id = ?', [$settingA]));
    }

    public function testListIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedSetting($siteA, 'tenant-a.only', 'gia-tri-a');
        $this->seedSetting($siteB, 'tenant-b.only', 'gia-tri-b');

        $this->actingAs($siteA, ['settings.manage']);
        $response = $this->router->dispatch(new Request('GET', '/admin/system-settings', 'example.com'));

        self::assertStringContainsString('tenant-a.only', $response->getBody());
        self::assertStringNotContainsString('tenant-b.only', $response->getBody());
    }
}

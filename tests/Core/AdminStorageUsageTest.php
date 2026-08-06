<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/** Integration test cho Phase 24 (Storage Usage, CMS-081) - GET /admin/storage (Modules\Admin\StorageUsageController). */
final class AdminStorageUsageTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));

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
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL,
            plan_id BIGINT NULL, storage_used_bytes BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE TABLE plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT, `key` VARCHAR(50) NOT NULL, name VARCHAR(150) NOT NULL,
            max_users INT NULL, max_storage_mb INT NULL, max_products INT NULL, is_active BOOLEAN NOT NULL DEFAULT 1
        )');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function seedSite(?int $planId = null, int $usedBytes = 0): int
    {
        $this->database->insert(
            'INSERT INTO sites (name, plan_id, storage_used_bytes) VALUES (?, ?, ?)',
            ['Site A', $planId, $usedBytes]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPlan(?int $maxStorageMb): int
    {
        $this->database->insert(
            "INSERT INTO plans (`key`, name, max_storage_mb) VALUES ('pro', 'Pro', ?)",
            [$maxStorageMb]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedMedia(int $tenantId, string $fileName, string $mimeType, int $size): void
    {
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, 1)',
            [$tenantId, $fileName, 'x/' . $fileName, $mimeType, $size]
        );
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
    }

    public function testRequiresMediaViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testShowsUnlimitedWhenNoPlanAssigned(): void
    {
        $siteId = $this->seedSite(null, 5 * 1024 * 1024);
        $this->actingAs($siteId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('không giới hạn', $response->getBody());
    }

    public function testShowsUsedVersusPlanQuota(): void
    {
        $planId = $this->seedPlan(100);
        $siteId = $this->seedSite($planId, 50 * 1024 * 1024);
        $this->actingAs($siteId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('50,0 MB', $response->getBody());
        self::assertStringContainsString('50%', $response->getBody());
    }

    public function testBreakdownGroupsByMimeCategory(): void
    {
        $siteId = $this->seedSite();
        $this->seedMedia($siteId, 'anh.png', 'image/png', 1024 * 1024);
        $this->seedMedia($siteId, 'tailieu.pdf', 'application/pdf', 2 * 1024 * 1024);
        $this->actingAs($siteId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Hình ảnh', $response->getBody());
        self::assertStringContainsString('Tài liệu', $response->getBody());
    }

    public function testLargestFilesListedDescendingBySize(): void
    {
        $siteId = $this->seedSite();
        $this->seedMedia($siteId, 'nho.png', 'image/png', 100);
        $this->seedMedia($siteId, 'lon.png', 'image/png', 9999);
        $this->actingAs($siteId, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));
        $body = $response->getBody();

        self::assertLessThan(\strpos($body, 'nho.png'), \strpos($body, 'lon.png'));
    }

    public function testIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite();
        $siteB = $this->seedSite();
        $this->seedMedia($siteA, 'cua-site-a.png', 'image/png', 1000);

        $this->actingAs($siteB, ['media.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/storage', 'example.com'));

        self::assertStringNotContainsString('cua-site-a.png', $response->getBody());
    }
}

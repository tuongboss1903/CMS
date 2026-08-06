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

/**
 * Integration test cho Phase 16 (Security & Audit Log System, CMS-053) - man hinh
 * Modules\Admin\AuditLogController.php (List/Filter/Phan trang). Dung dung pattern actingAs()
 * Session-based cua AdminCommentModerationTest.php. Log duoc SEED TRUC TIEP vao bang (khong qua
 * dispatch Controller khac) - viec Controller thuc su GHI dung log da duoc kiem chung rieng o
 * tests/Core/AuditLoggerTest.php, file nay chi tap trung UI xem/loc/phan trang.
 */
final class AdminAuditLogTest extends TestCase
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
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
        );

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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        // Design Audit Phase 24: AuditLogController LEFT JOIN users (avatar + ten that thay "user#N")
        // - fixture truoc day khong co bang users, gay loi SQL "no such table: users".
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->insert('INSERT INTO users (id, name, email, password) VALUES (1, ?, ?, ?)', ['Admin Test', 'admin@example.com', 'x']);
        $this->database->statement('CREATE TABLE audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            user_id BIGINT NULL,
            event VARCHAR(100) NOT NULL,
            auditable_type VARCHAR(20) NULL,
            auditable_id BIGINT NULL,
            old_values TEXT NULL,
            new_values TEXT NULL,
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedLog(
        int $tenantId,
        string $event,
        string $createdAt,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        $this->database->insert(
            'INSERT INTO audit_logs (tenant_id, user_id, event, ip_address, old_values, new_values, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $tenantId,
                1,
                $event,
                '203.0.113.1',
                $oldValues !== null ? \json_encode($oldValues) : null,
                $newValues !== null ? \json_encode($newValues) : null,
                $createdAt,
            ]
        );
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
    }

    public function testListRendersSeededLogs(): void
    {
        $siteId = $this->seedSite();
        $this->seedLog($siteId, 'page.created', '2026-08-14 10:00:00');
        $this->actingAs($siteId, ['audit_log.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('page.created', $response->getBody());
    }

    public function testListFiltersByEvent(): void
    {
        $siteId = $this->seedSite();
        $this->seedLog($siteId, 'page.created', '2026-08-14 10:00:00');
        $this->seedLog($siteId, 'auth.login_success', '2026-08-14 11:00:00');
        $this->actingAs($siteId, ['audit_log.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com', ['event' => 'page.created']));

        self::assertSame(200, $response->getStatusCode());
        // Design Audit Phase 24: badge gio hien LABEL da dich (vd "Tạo trang mới") thay vi ma tho,
        // ma goc chi con o thuoc tinh title="..." - dung title de assert dung 1 dong (khong bi lan
        // voi <option value="..."> cua dropdown loc, van liet ke MOI event nhu comment cu giai thich).
        self::assertStringContainsString('title="page.created">Tạo trang mới</span>', $response->getBody());
        self::assertStringNotContainsString('title="auth.login_success">', $response->getBody());
    }

    public function testListFiltersByDateRange(): void
    {
        $siteId = $this->seedSite();
        $this->seedLog($siteId, 'page.created', '2026-08-01 10:00:00');
        $this->seedLog($siteId, 'page.updated', '2026-08-20 10:00:00');
        $this->actingAs($siteId, ['audit_log.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com', [
            'date_from' => '2026-08-15',
            'date_to' => '2026-08-25',
        ]));

        self::assertSame(200, $response->getStatusCode());
        // Cung ly do o testListFiltersByEvent() ben tren - assert qua title="..." (ma goc), khong
        // phai label da dich, vi dropdown loc van liet ke moi event du bang da loc dung.
        self::assertStringContainsString('title="page.updated">Cập nhật trang</span>', $response->getBody());
        self::assertStringNotContainsString('title="page.created">', $response->getBody());
    }

    public function testListRequiresAuditLogViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testListIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedLog($siteA, 'page.created', '2026-08-14 10:00:00');
        $this->seedLog($siteB, 'settings.updated', '2026-08-14 10:00:00');

        $this->actingAs($siteA, ['audit_log.view']);
        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com'));

        self::assertStringContainsString('page.created', $response->getBody());
        self::assertStringNotContainsString('settings.updated', $response->getBody());
    }

    public function testPaginationSplitsAcrossPages(): void
    {
        $siteId = $this->seedSite();

        for ($i = 1; $i <= 25; $i++) {
            $this->seedLog($siteId, 'page.created', \sprintf('2026-08-%02d 10:00:00', ($i % 28) + 1));
        }

        $this->actingAs($siteId, ['audit_log.view']);

        $pageOne = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com'));
        self::assertSame(200, $pageOne->getStatusCode());

        $pageTwo = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com', ['page' => '2']));
        self::assertSame(200, $pageTwo->getStatusCode());
        self::assertStringContainsString('page.created', $pageTwo->getBody());
    }

    public function testListShowsOldAndNewValuesWhenPresent(): void
    {
        $siteId = $this->seedSite();
        $this->seedLog($siteId, 'page.updated', '2026-08-14 10:00:00', ['title' => 'Cu'], ['title' => 'Moi']);
        $this->actingAs($siteId, ['audit_log.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com'));

        self::assertStringContainsString('Cu', $response->getBody());
        self::assertStringContainsString('Moi', $response->getBody());
    }

    public function testEmptyStateWhenNoLogsMatchFilter(): void
    {
        $siteId = $this->seedSite();
        $this->seedLog($siteId, 'page.created', '2026-08-14 10:00:00');
        $this->actingAs($siteId, ['audit_log.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/audit-logs', 'example.com', ['event' => 'khong-ton-tai']));

        self::assertStringContainsString('Không có nhật ký nào phù hợp.', $response->getBody());
    }
}

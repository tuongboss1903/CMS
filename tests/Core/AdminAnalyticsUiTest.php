<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Config;
use Core\Container;
use Core\Database;
use Core\Http\Request;
use Core\Http\Response;
use Core\ModuleManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 12 (Advanced Analytics Dashboard, CMS-049) - render du lieu
 * AnalyticsService tren Admin Dashboard (modules/Admin/DashboardController.php +
 * themes/default/views/admin/pages/dashboard.php). Dung dung pattern setUp/migrate/seedUser/
 * extractCsrfToken cua AdminUiFoundationTest.php (khong sua file do, trung lap co chu dich).
 */
final class AdminAnalyticsUiTest extends TestCase
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
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            name VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE TABLE permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            `key` VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE TABLE role_permissions (
            role_id BIGINT NOT NULL,
            permission_id BIGINT NOT NULL
        )');
        $this->database->statement('CREATE TABLE user_site_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL
        )');
        $this->database->statement('CREATE TABLE pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'draft\',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE analytics_views (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            page_id BIGINT NULL,
            path VARCHAR(500) NOT NULL,
            ip_hash VARCHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            referrer VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }

    /** @return array{siteId: int, userId: int} */
    private function seedUser(
        string $email = 'admin@example.com',
        string $password = 'correct-password',
    ): array {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['Admin', $email, \password_hash($password, PASSWORD_DEFAULT), 'active']
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteId, 'editor']);
        $roleId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );

        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);

        return ['siteId' => $siteId, 'userId' => $userId];
    }

    private function seedView(int $tenantId, string $path, string $ipHash = 'hash-1'): void
    {
        $this->database->insert(
            'INSERT INTO analytics_views (tenant_id, path, ip_hash) VALUES (?, ?, ?)',
            [$tenantId, $path, $ipHash]
        );
    }

    /** Cho test xu huong (Design Audit Phase 8) - can dieu khien created_at de gia lap "ky truoc". */
    private function seedViewAt(int $tenantId, string $path, string $ipHash, int $daysAgo): void
    {
        $this->database->insert(
            'INSERT INTO analytics_views (tenant_id, path, ip_hash, created_at) VALUES (?, ?, ?, ?)',
            [$tenantId, $path, $ipHash, \date('Y-m-d H:i:s', \time() - $daysAgo * 86400)]
        );
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    private function loginAndGetDashboard(string $email, string $password): Response
    {
        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => $email, 'password' => $password, '_token' => $token]
        ));

        return $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));
    }

    public function testDashboardRendersTotalViewsAndUniqueVisitors(): void
    {
        $seeded = $this->seedUser();
        $this->seedView($seeded['siteId'], '/', 'hash-1');
        $this->seedView($seeded['siteId'], '/', 'hash-2');
        $this->seedView($seeded['siteId'], '/about', 'hash-1');

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-field="total_views">3<', $response->getBody());
        self::assertStringContainsString('data-field="unique_visitors">2<', $response->getBody());
    }

    public function testDashboardRendersTopPagesTable(): void
    {
        $seeded = $this->seedUser();
        $this->seedView($seeded['siteId'], '/pricing', 'hash-1');
        $this->seedView($seeded['siteId'], '/pricing', 'hash-2');
        $this->seedView($seeded['siteId'], '/about', 'hash-1');

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('/pricing', $response->getBody());
        self::assertStringContainsString('/about', $response->getBody());
    }

    public function testDashboardAnalyticsIsolatedPerTenant(): void
    {
        $seededA = $this->seedUser('admin-a@example.com', 'correct-password');
        $siteAId = $seededA['siteId'];

        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site B']);
        $siteBId = (int) $this->database->connection()->lastInsertId();

        $this->seedView($siteAId, '/tenant-a-only', 'hash-1');
        $this->seedView($siteBId, '/tenant-b-only', 'hash-1');
        $this->seedView($siteBId, '/tenant-b-only', 'hash-2');

        $response = $this->loginAndGetDashboard('admin-a@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-field="total_views">1<', $response->getBody());
        self::assertStringContainsString('/tenant-a-only', $response->getBody());
        self::assertStringNotContainsString('/tenant-b-only', $response->getBody());
    }

    public function testDashboardRendersSvgChartContainer(): void
    {
        $seeded = $this->seedUser();
        $this->seedView($seeded['siteId'], '/', 'hash-1');

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<svg', $response->getBody());
    }

    public function testDashboardRendersGracefullyWithoutAnalyticsData(): void
    {
        $this->seedUser();

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-field="total_views">0<', $response->getBody());
        self::assertStringContainsString('data-field="unique_visitors">0<', $response->getBody());
    }

    // ---- Chi bao xu huong KPI Card (Design Audit Phase 8) ----

    public function testDashboardShowsUpTrendWhenCurrentWeekHasMoreViewsThanPrevious(): void
    {
        $seeded = $this->seedUser();
        // Ky truoc (7-14 ngay truoc): 1 luot xem.
        $this->seedViewAt($seeded['siteId'], '/', 'hash-old', 10);
        // Ky hien tai (0-7 ngay truoc): 2 luot xem -> tang 100%.
        $this->seedViewAt($seeded['siteId'], '/', 'hash-1', 1);
        $this->seedViewAt($seeded['siteId'], '/', 'hash-2', 2);

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('stat-trend--up', $response->getBody());
        self::assertStringContainsString('100% so với kỳ trước', $response->getBody());
    }

    public function testDashboardShowsDownTrendWhenCurrentWeekHasFewerViewsThanPrevious(): void
    {
        $seeded = $this->seedUser();
        // Ky truoc: 4 luot xem tu 4 IP khac nhau.
        $this->seedViewAt($seeded['siteId'], '/', 'hash-a', 8);
        $this->seedViewAt($seeded['siteId'], '/', 'hash-b', 9);
        $this->seedViewAt($seeded['siteId'], '/', 'hash-c', 10);
        $this->seedViewAt($seeded['siteId'], '/', 'hash-d', 11);
        // Ky hien tai: 1 luot xem -> giam 75%.
        $this->seedViewAt($seeded['siteId'], '/', 'hash-e', 1);

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('stat-trend--down', $response->getBody());
        self::assertStringContainsString('75% so với kỳ trước', $response->getBody());
    }

    public function testDashboardShowsUpTrendWithoutFabricatedPercentWhenNoPreviousData(): void
    {
        $seeded = $this->seedUser();
        // Khong co du lieu ky truoc (>14 ngay), chi co du lieu ky hien tai.
        $this->seedViewAt($seeded['siteId'], '/', 'hash-1', 1);

        $response = $this->loginAndGetDashboard('admin@example.com', 'correct-password');

        self::assertSame(200, $response->getStatusCode());
        // Ky truoc = 0 -> khong the tinh % that su (chia cho 0), CHI hien mui ten, khong bia so
        // (khong duoc ghep "N% so voi ky truoc" khi N khong co y nghia thong ke that).
        self::assertStringContainsString('stat-trend--up">&#9650; so với kỳ trước</div>', $response->getBody());
    }
}

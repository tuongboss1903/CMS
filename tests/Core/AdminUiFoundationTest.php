<?php

declare(strict_types=1);

namespace Tests\Core;

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
 * Integration test cho Module Admin UI THAT (modules/Admin/) - ModuleManager tro thang modules/
 * that, Router::dispatch() that, khong fixture Controller. View dung dung themes/default/ that
 * (khac CMS-044 - Admin theme KHONG phai fixture, la noi dung san pham that theo Owner Decision
 * CMS-045 muc 4).
 */
final class AdminUiFoundationTest extends TestCase
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
        // KHONG dung singleton() cho View::class o day - test nay dispatch() nhieu "request" mo
        // phong (login/dashboard/logout) qua CHUNG 1 Container, khac production (1 Container/that
        // MOI request that - xem Application::registerCoreServices()). globalData cua View chua
        // csrf_token doc tai thoi diem resolve; Auth::login() CHU DICH xoay token khi dang nhap
        // (chong fixation - xem Auth::login()), nen phai resolve View MOI cho moi dispatch() de
        // globalData phan anh dung session HIEN TAI, giong nhu moi request that se co Container
        // (va View) rieng.
        $this->container->bind(
            View::class,
            // globalData 'csrf_token' mirror dung Application::registerCoreServices() that - Admin
            // Topbar (dropdown Dang xuat) doc csrf_token qua globalData, khong qua tung Controller
            // truyen rieng nua.
            static fn (Container $c): View => new View(self::REAL_THEMES_PATH, 'default', 'default', [
                'csrf_token' => $c->get(Csrf::class)->token(),
            ])
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
    }

    /** @return array{siteId: int, userId: int} */
    private function seedUser(
        string $email = 'admin@example.com',
        string $password = 'correct-password',
        string $status = 'active',
    ): array {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['Admin', $email, \password_hash($password, PASSWORD_DEFAULT), $status]
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

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    // ---- 1. GET /admin/login ----

    public function testShowLoginFormReturnsHtmlWithCsrfToken(): void
    {
        $this->tenantManager->setCurrent(1, ['id' => 1]);

        $response = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
        self::assertStringContainsString('name="_token"', $response->getBody());
    }

    // ---- 2. POST /admin/login - dung credential ----

    public function testLoginWithValidCredentialsRedirectsToDashboard(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/dashboard', $response->getHeaders()['Location']);
        self::assertNotNull($this->session->get('auth.user_id'));
    }

    // ---- 3. POST /admin/login - sai password (PRG: redirect ve GET, loi hien qua flash) ----

    public function testLoginWithWrongPasswordRedirectsBackWithFlashError(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'wrong-password', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/login', $response->getHeaders()['Location']);
        self::assertNull($this->session->get('auth.user_id'));

        // Test nay dung Router rieng (khong qua Application::boot()/StartSessionMiddleware -
        // setUp() tu goi $this->session->start() 1 lan cho ca test). Mo phong ranh gioi request
        // that bang cach dong roi mo lai session thu cong de Session::start() chay lai
        // ageFlashData() (_flash_new -> _flash_old) dung nhu 2 request HTTP rieng biet.
        \session_write_close();
        $this->session->start();

        $retryLoginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));

        self::assertSame(200, $retryLoginPage->getStatusCode());
        self::assertStringContainsString('Email hoặc mật khẩu không đúng.', $retryLoginPage->getBody());
    }

    // ---- 4. POST /admin/login - sai CSRF ----

    public function testLoginWithInvalidCsrfTokenReturns419(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }

    // ---- 5. GET /admin/dashboard - chua login ----

    public function testDashboardRedirectsToLoginWhenNotAuthenticated(): void
    {
        $this->tenantManager->setCurrent(1, ['id' => 1]);

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/login', $response->getHeaders()['Location']);
    }

    // ---- 6. GET /admin/dashboard - da login ----

    public function testDashboardRendersUserCountAndRoleCountWhenAuthenticated(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('user_count', $response->getBody());
        self::assertStringContainsString('role_count', $response->getBody());
        self::assertStringContainsString('page_count', $response->getBody());
        self::assertStringContainsString('media_count', $response->getBody());
    }

    public function testDashboardCountsAndActivityStreamReflectSeededData(): void
    {
        $seeded = $this->seedUser(email: 'admin@example.com', password: 'correct-password');
        $siteId = $seeded['siteId'];
        $this->database->insert(
            'INSERT INTO pages (tenant_id, title, slug, status) VALUES (?, ?, ?, ?)',
            [$siteId, 'Trang mau', 'trang-mau', 'published']
        );
        $this->database->insert(
            'INSERT INTO media (tenant_id, file_name, path, mime_type, size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$siteId, 'anh-mau.png', $siteId . '/anh-mau.png', 'image/png', 1024, 1]
        );

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Trang mau', $response->getBody());
        self::assertStringContainsString('anh-mau.png', $response->getBody());
    }

    // ---- 7. POST /admin/logout ----

    public function testLogoutClearsSessionAndDashboardRedirectsAgain(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        $dashboard = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));
        $logoutToken = $this->extractCsrfToken($dashboard->getBody());

        $logoutResponse = $this->router->dispatch(new Request(
            'POST',
            '/admin/logout',
            'example.com',
            [],
            ['_token' => $logoutToken]
        ));

        self::assertSame(302, $logoutResponse->getStatusCode());

        // Auth::logout() -> Session::destroy() ket thuc phien hien tai (isStarted() tro ve false) -
        // dung vong doi 1 request MOI se tu start() lai qua cookie; mo phong dieu do truoc khi doc
        // lai trang thai (cung pattern ModuleAuthIntegrationTest::testLogoutClearsAuthenticatedUser).
        $this->session->start();

        self::assertNull($this->session->get('auth.user_id'));

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/login', $response->getHeaders()['Location']);
    }

    // ---- 8. Phase 18 (UI/UX Admin Dashboard Overhaul, CMS-055) - Dark Mode attribute structure ----

    public function testDashboardLayoutRendersThemeToggleAndFoucGuardScript(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));
        $body = $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        // topbar.php: nut chuyen Sang/Toi phai co mat tren layout admin da dang nhap.
        self::assertStringContainsString('data-theme-toggle', $body);
        // main.php: inline script chong FOUC phai doc dung key localStorage ma app.js dung
        // (cms-theme) va gan [data-theme] len <html> TRUOC khi app.js (cuoi <body>) tai xong.
        self::assertStringContainsString("localStorage.getItem('cms-theme')", $body);
        self::assertStringContainsString('document.documentElement.setAttribute', $body);
    }

    public function testDashboardLayoutIncludesConfirmModalPartialWithFixedId(): void
    {
        $this->seedUser(email: 'admin@example.com', password: 'correct-password');

        $loginPage = $this->router->dispatch(new Request('GET', '/admin/login', 'example.com'));
        $token = $this->extractCsrfToken($loginPage->getBody());
        $this->router->dispatch(new Request(
            'POST',
            '/admin/login',
            'example.com',
            [],
            ['email' => 'admin@example.com', 'password' => 'correct-password', '_token' => $token]
        ));

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));
        $body = $response->getBody();

        // admin.partials.confirm_modal duoc include o layout main.php - phai co mat o MOI trang
        // admin (khong rieng gi trang co form data-confirm) de app.js luon tim thay #confirm-modal.
        self::assertStringContainsString('id="confirm-modal"', $body);
        self::assertStringContainsString('data-confirm-accept', $body);
    }
}

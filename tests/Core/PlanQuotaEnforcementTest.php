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
use Modules\Admin\MediaUploadController;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho enforcement gioi han Goi dich vu (Buoc 4, CMS-065) tren 2 diem: Media
 * Upload (max_storage_mb) va User Create (max_users). Tach RIENG file, khong sua
 * AdminMediaManagementUiTest.php/AdminUserManagementUiTest.php da co (dang co thay doi dang lam
 * khac chua commit, tranh lam 2 mach thay doi lan vao nhau) - cung pattern setUp (ModuleManager tro
 * modules/ that, boot ['auth','admin']).
 */
final class PlanQuotaEnforcementTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private const REAL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private string $storageDir;
    private string $sourceDir;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');

        $this->storageDir = \sys_get_temp_dir() . '/cms_plan_quota_test_' . \uniqid('', true);
        \mkdir($this->storageDir, 0755, true);
        $this->sourceDir = \sys_get_temp_dir() . '/cms_plan_quota_source_' . \uniqid('', true);
        \mkdir($this->sourceDir, 0755, true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(
            View::class,
            static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default')
        );

        $storageDir = $this->storageDir;
        $this->container->singleton(
            MediaUploadController::class,
            static fn (Container $c): MediaUploadController => new MediaUploadController(
                $c->get(\Core\Authorization::class),
                $c->get(\Core\Auth::class),
                $c->get(Database::class),
                $c->get(TenantManager::class),
                $storageDir
            )
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

        $this->removeDirectory($this->storageDir);
        $this->removeDirectory($this->sourceDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            \is_dir($path) ? $this->removeDirectory($path) : @\unlink($path);
        }

        @\rmdir($dir);
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            `key` VARCHAR(50) NOT NULL,
            name VARCHAR(150) NOT NULL,
            max_users INT NULL,
            max_storage_mb INT NULL
        )');
        $this->database->statement('CREATE TABLE sites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            plan_id BIGINT NULL,
            storage_used_bytes BIGINT NOT NULL DEFAULT 0
        )');
        $this->database->statement('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_users_email ON users (email)');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            alt_text VARCHAR(255) NULL,
            title VARCHAR(255) NULL,
            caption VARCHAR(500) NULL,
            uploaded_by BIGINT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            name VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE TABLE user_site_roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id BIGINT NOT NULL,
            site_id BIGINT NOT NULL,
            role_id BIGINT NOT NULL
        )');
    }

    private function seedPlan(?int $maxUsers, ?int $maxStorageMb): int
    {
        $this->database->insert(
            'INSERT INTO plans (`key`, name, max_users, max_storage_mb) VALUES (?, ?, ?, ?)',
            ['plan-' . \uniqid('', true), 'Test Plan', $maxUsers, $maxStorageMb]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedSite(?int $planId = null): int
    {
        $this->database->insert('INSERT INTO sites (name, plan_id) VALUES (?, ?)', ['Site A', $planId]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedRole(int $siteId): int
    {
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteId, 'editor']);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUser(int $siteId, int $roleId, string $email): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            ['User', $email, \password_hash('x', PASSWORD_DEFAULT)]
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );

        return $userId;
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, int $userId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', $userId);
        $this->session->set('auth.permissions', $permissions);
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    private function realPngBytes(): string
    {
        return (string) \base64_decode(self::REAL_PNG_BASE64, true);
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function fakeUploadedFile(string $originalName, string $mimeType, string $content): array
    {
        $tmpName = $this->sourceDir . '/tmp_' . \uniqid('', true);
        \file_put_contents($tmpName, $content);

        return [
            'name' => $originalName,
            'type' => $mimeType,
            'tmp_name' => $tmpName,
            'error' => UPLOAD_ERR_OK,
            'size' => \strlen($content),
        ];
    }

    // ---- Media storage quota ----

    public function testUploadRejectedWhenExceedsPlanStorageQuota(): void
    {
        $planId = $this->seedPlan(null, 1); // 1 MB
        $siteId = $this->seedSite($planId);
        $this->database->statement('UPDATE sites SET storage_used_bytes = ? WHERE id = ?', [1024 * 1024 - 10, $siteId]);
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId, 'a@example.com');
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $pngBytes = $this->realPngBytes(); // > 10 bytes con lai trong quota
        $file = $this->fakeUploadedFile('photo.png', 'image/png', $pngBytes);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(0, (int) $count['c']);
    }

    public function testUploadAllowedWhenUnderPlanStorageQuota(): void
    {
        $planId = $this->seedPlan(null, 10); // 10 MB
        $siteId = $this->seedSite($planId);
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId, 'a@example.com');
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $pngBytes = $this->realPngBytes();
        $file = $this->fakeUploadedFile('photo.png', 'image/png', $pngBytes);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(1, (int) $count['c']);
    }

    public function testUploadAllowedWhenSiteHasNoPlanRegardlessOfSize(): void
    {
        $siteId = $this->seedSite(null);
        $this->database->statement('UPDATE sites SET storage_used_bytes = ? WHERE id = ?', [999_999_999, $siteId]);
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId, 'a@example.com');
        $this->actingAs($siteId, $userId, ['media.view', 'media.upload']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/media', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $pngBytes = $this->realPngBytes();
        $file = $this->fakeUploadedFile('photo.png', 'image/png', $pngBytes);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/media',
            'example.com',
            [],
            ['_token' => $token],
            [],
            [],
            ['file' => $file]
        ));

        self::assertSame(302, $response->getStatusCode());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM media WHERE tenant_id = ?', [$siteId]);
        self::assertSame(1, (int) $count['c']);
    }

    // ---- User quota ----

    public function testCreateUserRejectedWhenAtPlanUserLimit(): void
    {
        $planId = $this->seedPlan(1, null);
        $siteId = $this->seedSite($planId);
        $roleId = $this->seedRole($siteId);
        $this->seedUser($siteId, $roleId, 'existing@example.com');
        $this->actingAs($siteId, 999, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => (string) $roleId, '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Da dat gioi han so User theo goi dich vu hien tai.', $response->getBody());

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNull($row);
    }

    public function testCreateUserAllowedWhenUnderPlanUserLimit(): void
    {
        $planId = $this->seedPlan(5, null);
        $siteId = $this->seedSite($planId);
        $roleId = $this->seedRole($siteId);
        $this->actingAs($siteId, 999, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => (string) $roleId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNotNull($row);
    }

    public function testCreateUserAllowedWhenSiteHasNoPlan(): void
    {
        $siteId = $this->seedSite(null);
        $roleId = $this->seedRole($siteId);
        $this->actingAs($siteId, 999, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => (string) $roleId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNotNull($row);
    }
}

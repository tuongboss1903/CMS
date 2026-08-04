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
 * Integration test cho Admin User Management UI THAT (modules/Admin/User*Controller) - cung
 * pattern AdminUiFoundationTest (ModuleManager tro modules/ that, View dung themes/default/
 * that). actingAs() ghi truc tiep Session (khong qua AuthenticationService::attempt() that -
 * cung cach ModuleUserIntegrationTest da dung cho API JSON).
 */
final class AdminUserManagementUiTest extends TestCase
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
            status VARCHAR(20) NOT NULL DEFAULT \'active\'
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_users_email ON users (email)');
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
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedRole(int $siteId, string $name = 'editor'): int
    {
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteId, $name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedSystemRole(string $name = 'Admin'): int
    {
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (NULL, ?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUser(
        int $siteId,
        int $roleId,
        string $email = 'user@example.com',
        string $password = 'correct-password',
        string $status = 'active',
    ): int {
        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', $email, \password_hash($password, PASSWORD_DEFAULT), $status]
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );

        return $userId;
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.permissions', $permissions);
    }

    private function extractCsrfToken(string $html): string
    {
        \preg_match('/name="_token" value="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }

    // ---- List ----

    public function testListFiltersByStatus(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $this->seedUser($siteId, $roleId, 'locked-user@example.com', 'correct-password', 'locked');
        $this->seedUser($siteId, $roleId, 'active-user@example.com', 'correct-password', 'active');
        $this->actingAs($siteId, ['user.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com', ['status' => 'locked']));

        self::assertStringContainsString('locked-user@example.com', $response->getBody());
        self::assertStringNotContainsString('active-user@example.com', $response->getBody());
    }

    public function testListShowsOnlyCurrentTenantUsers(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleA = $this->seedRole($siteA);
        $roleB = $this->seedRole($siteB);
        $this->seedUser($siteA, $roleA, 'a@example.com');
        $this->seedUser($siteB, $roleB, 'b@example.com');
        $this->actingAs($siteA, ['user.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('a@example.com', $response->getBody());
        self::assertStringNotContainsString('b@example.com', $response->getBody());
    }

    // ---- Create ----

    public function testCreateUserSuccessRedirectsToList(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $this->actingAs($siteId, ['user.create']);

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
        self::assertSame('/admin/users', $response->getHeaders()['Location']);

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNotNull($row);
    }

    public function testCreateUserValidationFailRendersFormAgain(): void
    {
        $siteId = $this->seedSite();
        $this->seedRole($siteId);
        $this->actingAs($siteId, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => '', 'email' => 'new@example.com', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="_token"', $response->getBody());

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNull($row);
    }

    public function testCreateUserDuplicateEmailRendersFormAgainWithoutCreating(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $this->seedUser($siteId, $roleId, 'existing@example.com');
        $this->actingAs($siteId, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'Dup', 'email' => 'existing@example.com', 'password' => 'password123', 'role_id' => (string) $roleId, '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Email da ton tai.', $response->getBody());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM users WHERE email = ?', ['existing@example.com']);
        self::assertSame(1, (int) $count['c']);
    }

    public function testCreateUserInvalidRoleRollsBackAndRendersFormAgain(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['user.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => '999999', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Role khong hop le.', $response->getBody());

        $row = $this->database->selectOne('SELECT id FROM users WHERE email = ?', ['new@example.com']);
        self::assertNull($row);
    }

    // ---- Edit ----

    public function testEditUserSuccessRedirectsToList(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId);
        $this->actingAs($siteId, ['user.update']);

        $formPage = $this->router->dispatch(new Request('GET', "/admin/users/{$userId}/edit", 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/users/{$userId}",
            'example.com',
            [],
            ['name' => 'Updated Name', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/users', $response->getHeaders()['Location']);

        $row = $this->database->selectOne('SELECT name FROM users WHERE id = ?', [$userId]);
        self::assertSame('Updated Name', $row['name']);
    }

    public function testEditCrossTenantUserReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleB = $this->seedRole($siteB);
        $userInB = $this->seedUser($siteB, $roleB);
        $this->actingAs($siteA, ['user.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/users/{$userInB}/edit", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- Lock / Unlock ----

    public function testLockUserSetsStatusLocked(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId);
        $this->actingAs($siteId, ['user.lock', 'user.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/users/{$userId}/lock",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status FROM users WHERE id = ?', [$userId]);
        self::assertSame('locked', $row['status']);
    }

    public function testUnlockUserSetsStatusActive(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $userId = $this->seedUser($siteId, $roleId, status: 'locked');
        $this->actingAs($siteId, ['user.lock', 'user.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/users/{$userId}/unlock",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status FROM users WHERE id = ?', [$userId]);
        self::assertSame('active', $row['status']);
    }

    // ---- Assign Role ----

    public function testAssignRoleUpdatesRoleId(): void
    {
        $siteId = $this->seedSite();
        $roleA = $this->seedRole($siteId, 'editor');
        $roleB = $this->seedRole($siteId, 'viewer');
        $userId = $this->seedUser($siteId, $roleA);
        $this->actingAs($siteId, ['user.assign_role', 'user.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/users/{$userId}/role",
            'example.com',
            [],
            ['role_id' => (string) $roleB, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne(
            'SELECT role_id FROM user_site_roles WHERE user_id = ? AND site_id = ?',
            [$userId, $siteId]
        );
        self::assertSame($roleB, (int) $row['role_id']);
    }

    public function testAssignSystemRoleIsRejected(): void
    {
        $siteId = $this->seedSite();
        $roleA = $this->seedRole($siteId, 'editor');
        $userId = $this->seedUser($siteId, $roleA);
        $systemRoleId = $this->seedSystemRole();
        $this->actingAs($siteId, ['user.assign_role', 'user.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/users/{$userId}/role",
            'example.com',
            [],
            ['role_id' => (string) $systemRoleId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne(
            'SELECT role_id FROM user_site_roles WHERE user_id = ? AND site_id = ?',
            [$userId, $siteId]
        );
        self::assertSame($roleA, (int) $row['role_id']);
    }

    // ---- Authorization ----

    public function testMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/users', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId);
        $this->actingAs($siteId, ['user.create']);

        $this->router->dispatch(new Request('GET', '/admin/users/create', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => (string) $roleId, '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}

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
 * Integration test cho Admin Role Management UI THAT (modules/Admin/Role*Controller) - cung
 * pattern AdminUserManagementUiTest (ModuleManager tro modules/ that, View dung themes/default/
 * that). actingAs() ghi truc tiep Session (khong qua AuthenticationService that).
 */
final class AdminRoleManagementUiTest extends TestCase
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
        $this->database->statement('CREATE TABLE roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id BIGINT NULL,
            name VARCHAR(100) NOT NULL
        )');
        $this->database->statement('CREATE UNIQUE INDEX uq_roles_tenant_name ON roles (tenant_id, name)');
        $this->database->statement('CREATE TABLE permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            `key` VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL
        )');
        $this->database->statement('CREATE TABLE role_permissions (
            role_id BIGINT NOT NULL,
            permission_id BIGINT NOT NULL,
            PRIMARY KEY (role_id, permission_id)
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

    private function seedRole(?int $tenantId, string $name = 'editor'): int
    {
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$tenantId, $name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPermission(string $key = 'post.create'): int
    {
        $this->database->insert('INSERT INTO permissions (`key`) VALUES (?)', [$key]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUser(int $siteId, int $roleId, string $email = 'user@example.com'): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', $email, \password_hash('x', PASSWORD_DEFAULT), 'active']
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

    public function testListShowsSystemAndTenantRolesOfCurrentTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->seedRole(null, 'Admin');
        $this->seedRole($siteA, 'editor-a');
        $this->seedRole($siteB, 'editor-b');
        $this->actingAs($siteA, ['role.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Admin', $response->getBody());
        self::assertStringContainsString('editor-a', $response->getBody());
        self::assertStringNotContainsString('editor-b', $response->getBody());
    }

    // ---- Create ----

    public function testCreateRoleSuccessRedirectsToList(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['role.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/roles/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/roles',
            'example.com',
            [],
            ['name' => 'editor', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/roles', $response->getHeaders()['Location']);

        $row = $this->database->selectOne('SELECT id FROM roles WHERE name = ?', ['editor']);
        self::assertNotNull($row);
    }

    public function testCreateRoleDuplicateNameRendersFormAgain(): void
    {
        $siteId = $this->seedSite();
        $this->seedRole($siteId, 'editor');
        $this->actingAs($siteId, ['role.create']);

        $formPage = $this->router->dispatch(new Request('GET', '/admin/roles/create', 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/roles',
            'example.com',
            [],
            ['name' => 'editor', '_token' => $token]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Ten role da ton tai.', $response->getBody());

        $count = $this->database->selectOne('SELECT COUNT(*) as c FROM roles WHERE name = ? AND tenant_id = ?', ['editor', $siteId]);
        self::assertSame(1, (int) $count['c']);
    }

    // ---- Edit ----

    public function testEditRoleSuccessRedirectsToList(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId, 'editor');
        $this->actingAs($siteId, ['role.update']);

        $formPage = $this->router->dispatch(new Request('GET', "/admin/roles/{$roleId}/edit", 'example.com'));
        $token = $this->extractCsrfToken($formPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$roleId}",
            'example.com',
            [],
            ['name' => 'editor-renamed', '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT name FROM roles WHERE id = ?', [$roleId]);
        self::assertSame('editor-renamed', $row['name']);
    }

    public function testEditCrossTenantRoleReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleInB = $this->seedRole($siteB, 'editor-b');
        $this->actingAs($siteA, ['role.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/roles/{$roleInB}/edit", 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    // ---- System role protection ----

    public function testEditSystemRoleReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        $this->actingAs($siteId, ['role.update']);

        $response = $this->router->dispatch(new Request('GET', "/admin/roles/{$systemRoleId}/edit", 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
    }

    public function testDeleteSystemRoleReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        // Seed them 1 Tenant Role "vo hai" (khong dung trong assertion) - list.php chi render
        // form Delete (chua _token) cho role KHONG PHAI system, neu danh sach chi co System Role
        // thi trang List khong co form nao de trich token.
        $this->seedRole($siteId, 'dummy-for-token');
        $this->actingAs($siteId, ['role.delete', 'role.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$systemRoleId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAssignPermissionToSystemRoleReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        $permissionId = $this->seedPermission();
        // Seed them 1 Tenant Role "vo hai" - cung ly do voi testDeleteSystemRoleReturns403Html:
        // list.php chi co form (chua _token) khi co it nhat 1 role khong phai system trong danh sach.
        $this->seedRole($siteId, 'dummy-for-token');
        $this->actingAs($siteId, ['role.assign_permission', 'role.view']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/roles/{$systemRoleId}/permissions", 'example.com'));
        self::assertSame(200, $showPage->getStatusCode());

        // permissions.php khong render form (System Role khong co nut "Gan"), nen khong the trich
        // token tu chinh trang nay - token van ton tai trong cung Session (Csrf::token() da goi
        // khi render trang), lay qua trang khac co form (list) trong cung phien.
        $listPage = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$systemRoleId}/permissions",
            'example.com',
            [],
            ['permission_id' => (string) $permissionId, '_token' => $token]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    // ---- Delete ----

    public function testDeleteRoleSuccessRedirectsToList(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId, 'editor');
        $this->actingAs($siteId, ['role.delete', 'role.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$roleId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM roles WHERE id = ?', [$roleId]);
        self::assertNull($row);
    }

    public function testDeleteRoleInUseReturns409Html(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId, 'editor');
        $this->seedUser($siteId, $roleId);
        $this->actingAs($siteId, ['role.delete', 'role.view']);

        $listPage = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));
        $token = $this->extractCsrfToken($listPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$roleId}/delete",
            'example.com',
            [],
            ['_token' => $token]
        ));

        self::assertSame(409, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT id FROM roles WHERE id = ?', [$roleId]);
        self::assertNotNull($row);
    }

    // ---- Assign Permission ----

    public function testShowPermissionsSplitsAssignedAndUnassigned(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId, 'editor');
        $assignedPermissionId = $this->seedPermission('post.create');
        $unassignedPermissionId = $this->seedPermission('post.delete');
        $this->database->insert(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, $assignedPermissionId]
        );
        $this->actingAs($siteId, ['role.assign_permission']);

        $response = $this->router->dispatch(new Request('GET', "/admin/roles/{$roleId}/permissions", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('post.create', $response->getBody());
        self::assertStringContainsString('post.delete', $response->getBody());
    }

    public function testAssignPermissionAddsRowIdempotently(): void
    {
        $siteId = $this->seedSite();
        $roleId = $this->seedRole($siteId, 'editor');
        $permissionId = $this->seedPermission();
        $this->actingAs($siteId, ['role.assign_permission']);

        $showPage = $this->router->dispatch(new Request('GET', "/admin/roles/{$roleId}/permissions", 'example.com'));
        $token = $this->extractCsrfToken($showPage->getBody());

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$roleId}/permissions",
            'example.com',
            [],
            ['permission_id' => (string) $permissionId, '_token' => $token]
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame("/admin/roles/{$roleId}/permissions", $response->getHeaders()['Location']);

        // Goi lai lan 2 (idempotent) - khong loi, khong tao them dong trung PRIMARY KEY. Tai su
        // dung dung token lan dau (Csrf::token() khong tu rotate trong cung session) - sau lan 1
        // permission da chuyen sang "Da gan" nen trang khong con form/token nao cho no de trich.
        $second = $this->router->dispatch(new Request(
            'POST',
            "/admin/roles/{$roleId}/permissions",
            'example.com',
            [],
            ['permission_id' => (string) $permissionId, '_token' => $token]
        ));

        self::assertSame(302, $second->getStatusCode());

        $count = $this->database->selectOne(
            'SELECT COUNT(*) as c FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $permissionId]
        );
        self::assertSame(1, (int) $count['c']);
    }

    // ---- Authorization ----

    public function testMissingPermissionReturns403Html(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/roles', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaders()['Content-Type']);
    }

    // ---- CSRF ----

    public function testCsrfFailureReturns419(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['role.create']);

        $this->router->dispatch(new Request('GET', '/admin/roles/create', 'example.com'));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/roles',
            'example.com',
            [],
            ['name' => 'editor', '_token' => 'invalid-token']
        ));

        self::assertSame(419, $response->getStatusCode());
    }
}

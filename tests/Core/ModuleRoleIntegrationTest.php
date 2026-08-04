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
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Module THAT (modules/Role/) - ModuleManager tro thang modules/ that,
 * Router::dispatch() that, khong fixture, cung pattern ModuleAuthIntegrationTest/
 * ModuleUserIntegrationTest. Permission rows seed truc tiep trong test.
 */
final class ModuleRoleIntegrationTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';

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

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['role']);
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

    private function seedRole(?int $tenantId, string $name = 'Editor'): int
    {
        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$tenantId, $name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPermission(string $key): int
    {
        $this->database->insert('INSERT INTO permissions (`key`) VALUES (?)', [$key]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedUserWithRole(int $siteId, int $roleId): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
            ['User', 'u' . \uniqid('', true) . '@example.com', \password_hash('x', PASSWORD_DEFAULT)]
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

    private function csrfToken(): string
    {
        return (new \Core\Csrf($this->session))->token();
    }

    public function testListRolesReturnsSystemAndTenantRoles(): void
    {
        $siteA = $this->seedSite();
        $this->seedRole(null, 'Admin');
        $this->seedRole($siteA, 'Editor');
        $this->actingAs($siteA, ['role.view']);

        $response = $this->router->dispatch(new Request('GET', '/roles', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(2, $decoded['data']);
    }

    public function testCreateRoleAssignsCurrentTenant(): void
    {
        $siteA = $this->seedSite();
        $this->actingAs($siteA, ['role.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/roles',
            'example.com',
            [],
            ['name' => 'Editor', '_token' => $this->csrfToken()]
        ));

        self::assertSame(201, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);

        $row = $this->database->selectOne('SELECT tenant_id FROM roles WHERE id = ?', [$decoded['data']['id']]);
        self::assertSame($siteA, (int) $row['tenant_id']);
    }

    public function testCreateRoleDuplicateNameReturns422(): void
    {
        $siteA = $this->seedSite();
        $this->seedRole($siteA, 'Editor');
        $this->actingAs($siteA, ['role.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/roles',
            'example.com',
            [],
            ['name' => 'Editor', '_token' => $this->csrfToken()]
        ));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testEditSystemRoleReturns403(): void
    {
        $siteA = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        $this->actingAs($siteA, ['role.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/roles/{$systemRoleId}",
            'example.com',
            [],
            ['name' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testEditTenantRoleSucceeds(): void
    {
        $siteA = $this->seedSite();
        $roleId = $this->seedRole($siteA, 'Editor');
        $this->actingAs($siteA, ['role.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/roles/{$roleId}",
            'example.com',
            [],
            ['name' => 'Senior Editor', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne('SELECT name FROM roles WHERE id = ?', [$roleId]);
        self::assertSame('Senior Editor', $row['name']);
    }

    public function testEditCrossTenantRoleReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleB = $this->seedRole($siteB, 'Editor');
        $this->actingAs($siteA, ['role.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/roles/{$roleB}",
            'example.com',
            [],
            ['name' => 'Hacked', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteSystemRoleReturns403(): void
    {
        $siteA = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        $this->actingAs($siteA, ['role.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/roles/{$systemRoleId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDeleteRoleInUseReturns409(): void
    {
        $siteA = $this->seedSite();
        $roleId = $this->seedRole($siteA, 'Editor');
        $this->seedUserWithRole($siteA, $roleId);
        $this->actingAs($siteA, ['role.delete']);

        $response = $this->router->dispatch(new Request('DELETE', "/roles/{$roleId}", 'example.com', [], ['_token' => $this->csrfToken()]));

        self::assertSame(409, $response->getStatusCode());
    }

    public function testAssignPermissionToSystemRoleReturns403(): void
    {
        $siteA = $this->seedSite();
        $systemRoleId = $this->seedRole(null, 'Admin');
        $permissionId = $this->seedPermission('user.view');
        $this->actingAs($siteA, ['role.assign_permission']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/roles/{$systemRoleId}/permissions",
            'example.com',
            [],
            ['permission_id' => $permissionId, '_token' => $this->csrfToken()]
        ));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAssignPermissionToTenantRoleSucceeds(): void
    {
        $siteA = $this->seedSite();
        $roleId = $this->seedRole($siteA, 'Editor');
        $permissionId = $this->seedPermission('user.view');
        $this->actingAs($siteA, ['role.assign_permission']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/roles/{$roleId}/permissions",
            'example.com',
            [],
            ['permission_id' => $permissionId, '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        $row = $this->database->selectOne(
            'SELECT role_id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $permissionId]
        );
        self::assertNotNull($row);
    }

    public function testListPermissionsReturnsAll(): void
    {
        $siteA = $this->seedSite();
        $this->seedPermission('user.view');
        $this->seedPermission('role.view');
        $this->actingAs($siteA, ['role.view']);

        $response = $this->router->dispatch(new Request('GET', '/permissions', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(2, $decoded['data']);
    }

    public function testPermissionDeniedReturns403(): void
    {
        $siteA = $this->seedSite();
        $this->actingAs($siteA, []);

        $response = $this->router->dispatch(new Request('GET', '/roles', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }
}

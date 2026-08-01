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
 * Integration test cho Module THAT (modules/User/) - ModuleManager tro thang modules/ that,
 * Router::dispatch() that, khong fixture, cung pattern ModuleAuthIntegrationTest (CMS-034).
 * Permission rows seed truc tiep trong test - khong phu thuoc migration nao (Owner Decision
 * CMS-037: CMS-037 gia dinh permission da ton tai san, khong tao migration seed).
 */
final class ModuleUserIntegrationTest extends TestCase
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
        $moduleManager->boot($this->router, ['user']);
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
        $this->database->statement('CREATE UNIQUE INDEX uq_user_site_roles ON user_site_roles (user_id, site_id)');
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

    private function seedUser(int $siteId, int $roleId, string $email = 'user@example.com', string $status = 'active'): int
    {
        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', $email, \password_hash('irrelevant', PASSWORD_DEFAULT), $status]
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

    public function testListUsersReturnsOnlyUsersOfCurrentTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleA = $this->seedRole($siteA);
        $roleB = $this->seedRole($siteB);
        $this->seedUser($siteA, $roleA, 'a@example.com');
        $this->seedUser($siteB, $roleB, 'b@example.com');

        $this->actingAs($siteA, ['user.view']);

        $response = $this->router->dispatch(new Request('GET', '/users', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertCount(1, $decoded['data']);
        self::assertSame('a@example.com', $decoded['data'][0]['email']);
    }

    public function testCreateUserSuccessInsertsUserAndUserSiteRoleInSingleTransaction(): void
    {
        $siteA = $this->seedSite('Site A');
        $roleA = $this->seedRole($siteA);
        $this->actingAs($siteA, ['user.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => $roleA]
        ));

        self::assertSame(201, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);
        $userId = $decoded['data']['id'];

        $userRow = $this->database->selectOne('SELECT * FROM users WHERE id = ?', [$userId]);
        self::assertNotNull($userRow);
        self::assertSame('new@example.com', $userRow['email']);

        $linkRow = $this->database->selectOne(
            'SELECT * FROM user_site_roles WHERE user_id = ? AND site_id = ?',
            [$userId, $siteA]
        );
        self::assertNotNull($linkRow);
        self::assertSame($roleA, (int) $linkRow['role_id']);
    }

    public function testCreateUserRollsBackWhenRoleInvalid(): void
    {
        $siteA = $this->seedSite('Site A');
        $this->actingAs($siteA, ['user.create']);

        $before = $this->database->select('SELECT id FROM users');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'new@example.com', 'password' => 'password123', 'role_id' => 999999]
        ));

        self::assertSame(422, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('Role khong hop le.', $decoded['message']);

        $after = $this->database->select('SELECT id FROM users');
        self::assertCount(\count($before), $after);
    }

    public function testCreateUserRollsBackWhenEmailDuplicate(): void
    {
        $siteA = $this->seedSite('Site A');
        $roleA = $this->seedRole($siteA);
        $this->seedUser($siteA, $roleA, 'existing@example.com');
        $this->actingAs($siteA, ['user.create']);

        $before = $this->database->select('SELECT id FROM users');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/users',
            'example.com',
            [],
            ['name' => 'New User', 'email' => 'existing@example.com', 'password' => 'password123', 'role_id' => $roleA]
        ));

        self::assertSame(422, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame('Email da ton tai.', $decoded['message']);

        $after = $this->database->select('SELECT id FROM users');
        self::assertCount(\count($before), $after);
    }

    public function testLockAndUnlockUseSamePermissionUserLock(): void
    {
        $siteA = $this->seedSite('Site A');
        $roleA = $this->seedRole($siteA);
        $userId = $this->seedUser($siteA, $roleA, 'target@example.com');

        $this->actingAs($siteA, ['user.lock']);

        $lockResponse = $this->router->dispatch(new Request('POST', "/users/{$userId}/lock", 'example.com'));
        self::assertSame(200, $lockResponse->getStatusCode());

        $locked = $this->database->selectOne('SELECT status FROM users WHERE id = ?', [$userId]);
        self::assertSame('locked', $locked['status']);

        $unlockResponse = $this->router->dispatch(new Request('POST', "/users/{$userId}/unlock", 'example.com'));
        self::assertSame(200, $unlockResponse->getStatusCode());

        $unlocked = $this->database->selectOne('SELECT status FROM users WHERE id = ?', [$userId]);
        self::assertSame('active', $unlocked['status']);
    }

    public function testPermissionDeniedReturns403(): void
    {
        $siteA = $this->seedSite('Site A');
        $this->actingAs($siteA, []);

        $response = $this->router->dispatch(new Request('GET', '/users', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $roleB = $this->seedRole($siteB);
        $userInSiteB = $this->seedUser($siteB, $roleB, 'other@example.com');

        $this->actingAs($siteA, ['user.update']);

        $response = $this->router->dispatch(new Request(
            'PATCH',
            "/users/{$userInSiteB}",
            'example.com',
            [],
            ['name' => 'Hacked Name']
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}

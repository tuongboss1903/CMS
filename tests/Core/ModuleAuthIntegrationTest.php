<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Auth;
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
 * Integration test cho Module THAT (modules/Auth/) - khong dung fixture module, chung minh
 * ModuleManager/Router/ControllerResolver hoat dong dung voi Module ngoai test fixture lan dau
 * tien (CMS-034). Khong qua Application::bootstrap() (tranh phu thuoc config production/ghi log
 * that vao storage/) - tu dung Container + Router toi thieu, giong pattern AuthenticationServiceTest/
 * TenantResolverMiddlewareTest.
 */
final class ModuleAuthIntegrationTest extends TestCase
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
        $moduleManager->boot($this->router, ['auth']);
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

    /** @return array{siteId: int, userId: int} */
    private function seedUser(
        string $email = 'user@example.com',
        string $password = 'correct-password',
        string $status = 'active',
    ): array {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', ['Site A']);
        $siteId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, ?)',
            ['User', $email, \password_hash($password, PASSWORD_DEFAULT), $status]
        );
        $userId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO roles (tenant_id, name) VALUES (?, ?)', [$siteId, 'editor']);
        $roleId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert('INSERT INTO permissions (`key`) VALUES (?)', ['post.create']);
        $permissionId = (int) $this->database->connection()->lastInsertId();

        $this->database->insert(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
            [$roleId, $permissionId]
        );
        $this->database->insert(
            'INSERT INTO user_site_roles (user_id, site_id, role_id) VALUES (?, ?, ?)',
            [$userId, $siteId, $roleId]
        );

        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);

        return ['siteId' => $siteId, 'userId' => $userId];
    }

    public function testLoginSucceedsWithValidCredentialsThroughRealModule(): void
    {
        $seed = $this->seedUser(email: 'user@example.com', password: 'correct-password');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'user@example.com', 'password' => 'correct-password']
        ));

        self::assertSame(200, $response->getStatusCode());

        /** @var array{success: bool, data: array{id: int, email: string, roles: list<string>, permissions: list<string>}} $decoded */
        $decoded = \json_decode($response->getBody(), true);

        self::assertTrue($decoded['success']);
        self::assertSame($seed['userId'], $decoded['data']['id']);
        self::assertSame('user@example.com', $decoded['data']['email']);
        self::assertSame(['editor'], $decoded['data']['roles']);
        self::assertSame(['post.create'], $decoded['data']['permissions']);
        self::assertArrayNotHasKey('password', $decoded['data']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->seedUser(email: 'user@example.com', password: 'correct-password');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'user@example.com', 'password' => 'wrong-password']
        ));

        self::assertSame(401, $response->getStatusCode());

        $decoded = \json_decode($response->getBody(), true);
        self::assertFalse($decoded['success']);
        self::assertSame('Email hoac mat khau khong dung.', $decoded['message']);
    }

    public function testLoginFailsWithMissingFieldsReturns422(): void
    {
        $this->tenantManager->setCurrent(1, ['id' => 1]);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'user@example.com']
        ));

        self::assertSame(422, $response->getStatusCode());

        $decoded = \json_decode($response->getBody(), true);
        self::assertFalse($decoded['success']);
        self::assertArrayHasKey('password', $decoded['errors']);
    }

    public function testLoginFailsWithInvalidEmailFormatReturns422(): void
    {
        $this->tenantManager->setCurrent(1, ['id' => 1]);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'not-an-email', 'password' => 'whatever']
        ));

        self::assertSame(422, $response->getStatusCode());

        $decoded = \json_decode($response->getBody(), true);
        self::assertArrayHasKey('email', $decoded['errors']);
    }

    public function testLoginDoesNotLeakPasswordHashInResponse(): void
    {
        $this->seedUser(email: 'user@example.com', password: 'correct-password');

        $response = $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'user@example.com', 'password' => 'correct-password']
        ));

        self::assertStringNotContainsString('$2y$', $response->getBody());
    }

    public function testLogoutClearsAuthenticatedUser(): void
    {
        $this->seedUser(email: 'user@example.com', password: 'correct-password');

        $this->router->dispatch(new Request(
            'POST',
            '/login',
            'example.com',
            [],
            ['email' => 'user@example.com', 'password' => 'correct-password']
        ));

        $auth = $this->container->get(Auth::class);
        self::assertTrue($auth->check());

        $response = $this->router->dispatch(new Request('POST', '/logout', 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);
        self::assertNull($decoded['data']);
        self::assertSame([], $decoded['errors']);

        // Session::destroy() ket thuc phien hien tai (isStarted() tro ve false) - dung vong doi
        // 1 request MOI se tu start() lai qua cookie; mo phong dieu do truoc khi doc lai trang thai
        // (cung pattern AuthTest::testLogoutClearsAuthenticationState, CMS-021).
        $this->session->start();

        self::assertFalse($auth->check());
        self::assertNull($auth->user());
    }

    public function testLogoutSucceedsWhenNotLoggedIn(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/logout', 'example.com'));

        self::assertSame(200, $response->getStatusCode());

        $decoded = \json_decode($response->getBody(), true);
        self::assertTrue($decoded['success']);

        $this->session->start();

        $auth = $this->container->get(Auth::class);
        self::assertFalse($auth->check());
    }
}

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
 * Integration test cho Module THAT (modules/Dashboard/) - cung pattern
 * ModuleAuthIntegrationTest/ModuleUserIntegrationTest/ModuleRoleIntegrationTest.
 */
final class ModuleDashboardIntegrationTest extends TestCase
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
        $moduleManager->boot($this->router, ['dashboard']);
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

    private function seedUserWithRole(int $siteId, int $roleId): void
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
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.permissions', $permissions);
    }

    public function testDashboardReturnsScopedCounts(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');

        $roleA = $this->seedRole($siteA, 'Editor A');
        $roleB = $this->seedRole($siteB, 'Editor B');
        $this->seedRole(null, 'Admin');

        $this->seedUserWithRole($siteA, $roleA);
        $this->seedUserWithRole($siteA, $roleA);
        $this->seedUserWithRole($siteB, $roleB);

        $this->actingAs($siteA, ['dashboard.view']);

        $response = $this->router->dispatch(new Request('GET', '/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        $decoded = \json_decode($response->getBody(), true);
        self::assertSame(2, $decoded['data']['user_count']);
        self::assertSame(2, $decoded['data']['role_count']);
    }

    public function testDashboardPermissionDeniedReturns403(): void
    {
        $siteA = $this->seedSite();
        $this->actingAs($siteA, []);

        $response = $this->router->dispatch(new Request('GET', '/dashboard', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }
}

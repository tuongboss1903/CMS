<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Container;
use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\ModuleManager;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 19 (CMS-056) - xac nhan diem hook "admin.menu.items" (qua
 * View::$globalData, xem core/Application.php::registerCoreServices()) chi hien thi muc menu
 * Ecommerce khi DUNG tenant da kich hoat plugin, VA xac nhan boot() ModuleManager+PluginManager
 * song song khong lam vo Admin route co san (Zero-Regression, dung tien le AdminUiFoundationTest.php).
 */
final class ApplicationPluginActivationIntegrationTest extends TestCase
{
    private const REAL_MODULES_PATH = __DIR__ . '/../../modules';
    private const REAL_PLUGINS_PATH = __DIR__ . '/../../plugins';
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';

    private Container $container;
    private Router $router;
    private Database $database;
    private Session $session;
    private TenantManager $tenantManager;
    private PluginActivationService $pluginActivation;
    private string $cachePath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-app-plugin-activation-' . \uniqid('', true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());

        // Dung dung logic that cua Application::registerCoreServices() View::class factory - tinh
        // toan admin.menu.items qua Hook::apply(), khong tu viet lai logic rieng cho test.
        $this->container->singleton(View::class, function (Container $c): View {
            $tenantManager = $c->get(TenantManager::class);
            $extraMenuItems = $c->get(Hook::class)->apply(
                'admin.menu.items',
                [],
                $tenantManager,
                $c->get(PluginActivationService::class)
            );

            return new View(self::REAL_THEMES_PATH, 'default', 'default', ['extra_admin_menu_items' => $extraMenuItems]);
        });

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);
        $this->pluginActivation = $this->container->get(PluginActivationService::class);

        $this->migrate();

        $moduleManager = new ModuleManager(self::REAL_MODULES_PATH);
        $moduleManager->boot($this->router, ['auth', 'admin']);

        $pluginManager = new PluginManager(self::REAL_PLUGINS_PATH);
        $pluginManager->boot($this->container->get(Hook::class), ['ecommerce']);
        $this->container->get(Hook::class)->do('plugin.routes.register', $this->router);
    }

    protected function tearDown(): void
    {
        if (\session_status() === PHP_SESSION_ACTIVE) {
            @\session_destroy();
        }

        $_SESSION = [];

        if (\is_dir($this->cachePath)) {
            $this->removeDirectory($this->cachePath);
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (\glob($path . '/*') ?: [] as $file) {
            \is_dir($file) ? $this->removeDirectory($file) : @\unlink($file);
        }

        @\rmdir($path);
    }

    private function migrate(): void
    {
        $this->database->statement('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'active\')');
        $this->database->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'active\', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $this->database->statement('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NULL, name VARCHAR(100) NOT NULL)');
        $this->database->statement('CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, `key` VARCHAR(100) NOT NULL)');
        $this->database->statement('CREATE TABLE role_permissions (role_id BIGINT NOT NULL, permission_id BIGINT NOT NULL)');
        $this->database->statement('CREATE TABLE user_site_roles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id BIGINT NOT NULL, site_id BIGINT NOT NULL, role_id BIGINT NOT NULL)');
        $this->database->statement("CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL)");
        $this->database->statement('CREATE TABLE media (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, file_name VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $this->database->statement('CREATE TABLE site_plugins (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, plugin_key VARCHAR(100) NOT NULL, is_active BOOLEAN NOT NULL DEFAULT 0, activated_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL)');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function actingAs(int $siteId): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
    }

    public function testSidebarHidesEcommerceMenuWhenPluginNotActive(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId);

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Sản phẩm', $response->getBody());
        self::assertStringNotContainsString('/admin/orders', $response->getBody());
    }

    public function testSidebarShowsEcommerceMenuWhenPluginActive(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId);
        $this->pluginActivation->activate($siteId, 'ecommerce');

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Sản phẩm', $response->getBody());
        self::assertStringContainsString('/admin/orders', $response->getBody());
    }

    public function testEcommerceMenuIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $this->pluginActivation->activate($siteA, 'ecommerce');

        $this->actingAs($siteB);
        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertStringNotContainsString('Sản phẩm', $response->getBody());
    }

    public function testExistingAdminRouteStillWorksAfterPluginWiring(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId);

        $response = $this->router->dispatch(new Request('GET', '/admin/dashboard', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('user_count', $response->getBody());
    }
}

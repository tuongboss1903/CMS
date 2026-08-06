<?php

declare(strict_types=1);

namespace Tests\Core;

use Core\Cache;
use Core\Cache\CacheDriver;
use Core\Cache\FileCacheDriver;
use Core\Config;
use Core\Container;
use Core\Csrf;
use Core\Database;
use Core\Hook;
use Core\Http\Request;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 19 (Ecommerce MVP, CMS-056) - Admin Product CRUD
 * (Plugins\Ecommerce\Controllers\Admin\Product*Controller). Route dang ky qua PluginManager that +
 * Hook "plugin.routes.register" (khong phai ModuleManager - dung tien le AdminSettingTest.php cho
 * phan Container/Router/Session thu cong, nhung thay ModuleManager bang PluginManager that).
 */
final class EcommerceProductManagementTest extends TestCase
{
    private const REAL_THEMES_PATH = __DIR__ . '/../../themes';
    private const REAL_PLUGINS_PATH = __DIR__ . '/../../plugins';

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
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-ecommerce-product-' . \uniqid('', true);

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());

        $this->router = new Router($this->container);
        $this->database = $this->container->get(Database::class);
        $this->session = $this->container->get(Session::class);
        $this->session->start();
        $this->tenantManager = $this->container->get(TenantManager::class);
        $this->pluginActivation = $this->container->get(PluginActivationService::class);

        $this->migrate();

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
        $this->database->statement('CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(150) NOT NULL)');
        $this->database->statement('CREATE TABLE site_plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, plugin_key VARCHAR(100) NOT NULL,
            is_active BOOLEAN NOT NULL DEFAULT 0, activated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )');
        $this->database->statement("CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL, description TEXT NULL, category VARCHAR(100) NULL,
            price DECIMAL(12,2) NOT NULL, sku VARCHAR(100) NULL, stock_quantity INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft', image_id BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE UNIQUE INDEX uq_products_tenant_slug ON products (tenant_id, slug)');
        $this->database->statement('CREATE TABLE media (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, file_name VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size BIGINT NOT NULL,
            uploaded_by BIGINT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->statement('CREATE TABLE product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL, sku VARCHAR(100) NULL, price_override DECIMAL(12,2) NULL,
            stock_quantity INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedProduct(int $tenantId, string $name = 'Ao thun', string $slug = 'ao-thun', string $status = 'published'): int
    {
        $this->database->insert(
            'INSERT INTO products (tenant_id, name, slug, price, stock_quantity, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $name, $slug, 100.0, 10, $status]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    /** @param list<string> $permissions */
    private function actingAs(int $siteId, array $permissions): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', $permissions);
        $this->pluginActivation->activate($siteId, 'ecommerce');
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    public function testListReturns404WhenPluginNotActivatedForTenant(): void
    {
        $siteId = $this->seedSite();
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->session->set('auth.user_id', 1);
        $this->session->set('auth.permissions', ['product.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/products', 'example.com'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testListRequiresProductViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/products', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testListRendersProductsForTenant(): void
    {
        $siteId = $this->seedSite();
        $this->seedProduct($siteId, 'Ao thun', 'ao-thun');
        $this->actingAs($siteId, ['product.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/products', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Ao thun', $response->getBody());
    }

    public function testCreateStoresNewProduct(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['product.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/products',
            'example.com',
            [],
            ['name' => 'Quan jean', 'slug' => 'quan-jean', 'price' => '250.5', 'stock_quantity' => '5', 'status' => 'published', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT * FROM products WHERE slug = ?', ['quan-jean']);
        self::assertNotNull($row);
        self::assertSame($siteId, (int) $row['tenant_id']);
        self::assertSame('published', $row['status']);
    }

    public function testCreateRejectsDuplicateSlug(): void
    {
        $siteId = $this->seedSite();
        $this->seedProduct($siteId, 'Ao thun', 'ao-thun');
        $this->actingAs($siteId, ['product.create']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/products',
            'example.com',
            [],
            ['name' => 'Ao thun 2', 'slug' => 'ao-thun', 'price' => '99', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('da ton tai', $response->getBody());
    }

    public function testUpdateModifiesProduct(): void
    {
        $siteId = $this->seedSite();
        $productId = $this->seedProduct($siteId);
        $this->actingAs($siteId, ['product.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/products/{$productId}",
            'example.com',
            [],
            ['name' => 'Ao thun moi', 'slug' => 'ao-thun', 'price' => '150', 'stock_quantity' => '20', 'status' => 'published', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT * FROM products WHERE id = ?', [$productId]);
        self::assertSame('Ao thun moi', $row['name']);
        self::assertSame(150.0, (float) $row['price']);
    }

    public function testDeleteSoftDeletesProduct(): void
    {
        $siteId = $this->seedSite();
        $productId = $this->seedProduct($siteId);
        $this->actingAs($siteId, ['product.delete']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/products/{$productId}/delete",
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT deleted_at FROM products WHERE id = ?', [$productId]);
        self::assertNotNull($row['deleted_at']);
    }

    public function testUpdateCrossTenantReturns404(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $productA = $this->seedProduct($siteA);

        $this->actingAs($siteB, ['product.update']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/products/{$productA}",
            'example.com',
            [],
            ['name' => 'Hack', 'slug' => 'hack', 'price' => '1', '_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}

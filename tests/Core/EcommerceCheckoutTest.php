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
use Core\Logger;
use Core\Mail\Drivers\ArrayMailerDriver;
use Core\Mail\Mailer;
use Core\Mail\MailerDriver;
use Core\PluginActivationService;
use Core\PluginManager;
use Core\Router;
use Core\Session;
use Core\TenantManager;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Integration test cho Phase 19 (Ecommerce MVP, CMS-056) - luong Public Cart -> Checkout
 * (Plugins\Ecommerce\Controllers\Public\*). Gio hang la Session that (khong mock) - nhieu
 * dispatch() lien tiep trong CUNG 1 test mo phong nhieu request cua CUNG 1 khach (session giu
 * nguyen giua cac dispatch(), dung nguyen tac da ap dung o CommentSubmitController RateLimiter test).
 */
final class EcommerceCheckoutTest extends TestCase
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
    private string $logPath;

    protected function setUp(): void
    {
        $config = new Config(__DIR__ . '/../Fixtures/config');
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-ecommerce-checkout-' . \uniqid('', true);
        $this->logPath = \sys_get_temp_dir() . '/cms-test-ecommerce-checkout-' . \uniqid('', true) . '.log';

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());
        // Phase 20 (CMS-057): CheckoutPlaceOrderController nay can Mailer (ban hook "order.created")
        // - MailerDriver la interface, Container KHONG tu auto-wire duoc (dung bai hoc MailerDriver
        // da ghi nhan tu Phase 15), phai dang ky tuong minh ArrayMailerDriver (test double).
        $this->container->singleton(MailerDriver::class, static fn (): ArrayMailerDriver => new ArrayMailerDriver());
        $this->container->singleton(Mailer::class, fn (Container $c): Mailer => new Mailer($c->get(MailerDriver::class), $c->get(View::class), new Logger($this->logPath)));

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

        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
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
            status VARCHAR(20) NOT NULL DEFAULT 'draft', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE TABLE product_variants (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
            name VARCHAR(255) NOT NULL, sku VARCHAR(100) NULL, price_override DECIMAL(12,2) NULL,
            stock_quantity INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )');
        $this->database->statement("CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, order_number VARCHAR(40) NOT NULL,
            guest_name VARCHAR(255) NOT NULL, guest_email VARCHAR(255) NOT NULL, shipping_address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', total_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT, order_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
            product_variant_id BIGINT NULL, product_name_snapshot VARCHAR(255) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL, quantity INT NOT NULL, subtotal DECIMAL(12,2) NOT NULL
        )');
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedProduct(int $tenantId, int $stock = 10): int
    {
        $this->database->insert(
            "INSERT INTO products (tenant_id, name, slug, price, stock_quantity, status) VALUES (?, 'Ao thun', 'ao-thun', 100.0, ?, 'published')",
            [$tenantId, $stock]
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function actingAsShopper(int $siteId): void
    {
        $this->tenantManager->setCurrent($siteId, ['id' => $siteId]);
        $this->pluginActivation->activate($siteId, 'ecommerce');
    }

    private function csrfToken(): string
    {
        return $this->container->get(Csrf::class)->token();
    }

    public function testAddToCartThenViewCartShowsItem(): void
    {
        $siteId = $this->seedSite();
        $productId = $this->seedProduct($siteId);
        $this->actingAsShopper($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/cart/add',
            'example.com',
            [],
            ['product_id' => (string) $productId, 'quantity' => '2', '_token' => $this->csrfToken()]
        ));

        $response = $this->router->dispatch(new Request('GET', '/cart', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Ao thun', $response->getBody());
    }

    public function testAddToCartInsufficientStockRedirectsWithError(): void
    {
        $siteId = $this->seedSite();
        $productId = $this->seedProduct($siteId, 1);
        $this->actingAsShopper($siteId);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/cart/add',
            'example.com',
            [],
            ['product_id' => (string) $productId, 'quantity' => '5', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $cartResponse = $this->router->dispatch(new Request('GET', '/cart', 'example.com'));
        self::assertStringContainsString('trong', $cartResponse->getBody());
    }

    public function testCheckoutWithEmptyCartRedirectsToCart(): void
    {
        $siteId = $this->seedSite();
        $this->actingAsShopper($siteId);

        $response = $this->router->dispatch(new Request('GET', '/checkout', 'example.com'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/cart', $response->getHeaders()['Location']);
    }

    public function testCheckoutPlaceOrderCreatesOrderAndClearsCart(): void
    {
        $siteId = $this->seedSite();
        $productId = $this->seedProduct($siteId, 10);
        $this->actingAsShopper($siteId);

        $this->router->dispatch(new Request(
            'POST',
            '/cart/add',
            'example.com',
            [],
            ['product_id' => (string) $productId, 'quantity' => '2', '_token' => $this->csrfToken()]
        ));

        $response = $this->router->dispatch(new Request(
            'POST',
            '/checkout',
            'example.com',
            [],
            ['guest_name' => 'Nguyen Van A', 'guest_email' => 'a@example.com', 'shipping_address' => '123 Le Loi', '_token' => $this->csrfToken()]
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('thanh cong', $response->getBody());

        $order = $this->database->selectOne('SELECT * FROM orders WHERE tenant_id = ?', [$siteId]);
        self::assertNotNull($order);
        self::assertSame('Nguyen Van A', $order['guest_name']);
        self::assertSame(200.0, (float) $order['total_amount']);

        $items = $this->database->select('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
        self::assertCount(1, $items);
        self::assertSame(2, (int) $items[0]['quantity']);

        $product = $this->database->selectOne('SELECT stock_quantity FROM products WHERE id = ?', [$productId]);
        self::assertSame(8, (int) $product['stock_quantity']);

        $cartResponse = $this->router->dispatch(new Request('GET', '/cart', 'example.com'));
        self::assertStringContainsString('trong', $cartResponse->getBody());
    }
}

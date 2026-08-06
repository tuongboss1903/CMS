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

/** Integration test cho Phase 19 (Ecommerce MVP, CMS-056) - Admin Order List/Show/UpdateStatus. */
final class EcommerceOrderManagementTest extends TestCase
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
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-ecommerce-order-' . \uniqid('', true);
        $this->logPath = \sys_get_temp_dir() . '/cms-test-ecommerce-order-' . \uniqid('', true) . '.log';

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());
        // Phase 20 (CMS-057): OrderUpdateStatusController nay can Mailer (ban hook "order.shipped")
        // - MailerDriver la interface, dang ky tuong minh ArrayMailerDriver (dung bai hoc Phase 15).
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

        if (\is_file($this->logPath)) {
            @\unlink($this->logPath);
        }

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
            slug VARCHAR(255) NOT NULL, price DECIMAL(12,2) NOT NULL, stock_quantity INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft', image_id BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
        )");
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

    private function seedOrder(int $tenantId, string $status = 'pending'): int
    {
        $this->database->insert(
            'INSERT INTO orders (tenant_id, order_number, guest_name, guest_email, status, total_amount)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, 'ORD-TEST-001', 'Nguyen Van A', 'a@example.com', $status, 100.0]
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

    public function testListRequiresOrderViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/orders', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testListRendersOrdersForTenant(): void
    {
        $siteId = $this->seedSite();
        $this->seedOrder($siteId);
        $this->actingAs($siteId, ['order.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/orders', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('ORD-TEST-001', $response->getBody());
    }

    public function testShowDisplaysOrderItems(): void
    {
        $siteId = $this->seedSite();
        $orderId = $this->seedOrder($siteId);
        $this->database->insert(
            'INSERT INTO order_items (order_id, product_id, product_name_snapshot, unit_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)',
            [$orderId, 1, 'Ao thun', 100.0, 1, 100.0]
        );
        $this->actingAs($siteId, ['order.view']);

        $response = $this->router->dispatch(new Request('GET', "/admin/orders/{$orderId}", 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Ao thun', $response->getBody());
    }

    public function testUpdateStatusTransitionsPendingToProcessing(): void
    {
        $siteId = $this->seedSite();
        $orderId = $this->seedOrder($siteId, 'pending');
        $this->actingAs($siteId, ['order.update_status']);

        $response = $this->router->dispatch(new Request(
            'POST',
            "/admin/orders/{$orderId}/status",
            'example.com',
            [],
            ['status' => 'processing', '_token' => $this->csrfToken()]
        ));

        self::assertSame(302, $response->getStatusCode());

        $row = $this->database->selectOne('SELECT status FROM orders WHERE id = ?', [$orderId]);
        self::assertSame('processing', $row['status']);
    }

    public function testUpdateStatusRejectsInvalidTransition(): void
    {
        $siteId = $this->seedSite();
        $orderId = $this->seedOrder($siteId, 'completed');
        $this->actingAs($siteId, ['order.update_status']);

        $this->router->dispatch(new Request(
            'POST',
            "/admin/orders/{$orderId}/status",
            'example.com',
            [],
            ['status' => 'pending', '_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne('SELECT status FROM orders WHERE id = ?', [$orderId]);
        self::assertSame('completed', $row['status']);
    }

    public function testUpdateStatusIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $orderA = $this->seedOrder($siteA);

        $this->actingAs($siteB, ['order.update_status']);

        $this->router->dispatch(new Request(
            'POST',
            "/admin/orders/{$orderA}/status",
            'example.com',
            [],
            ['status' => 'processing', '_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne('SELECT status FROM orders WHERE id = ?', [$orderA]);
        self::assertSame('pending', $row['status'], 'Don hang cua tenant khac khong duoc thay doi.');
    }
}

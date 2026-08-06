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

/** Integration test cho Phase 24 (Payment Management, CMS-081) - bat/tat cong thanh toan + danh sach giao dich. */
final class EcommercePaymentManagementTest extends TestCase
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
        $this->cachePath = \sys_get_temp_dir() . '/cms-test-ecommerce-payment-' . \uniqid('', true);
        $this->logPath = \sys_get_temp_dir() . '/cms-test-ecommerce-payment-' . \uniqid('', true) . '.log';

        $this->container = new Container();
        $this->container->instance(Config::class, $config);
        $this->container->singleton(Database::class, static fn (Container $c): Database => new Database($c->get(Config::class)));
        $this->container->singleton(Session::class, static fn (Container $c): Session => new Session($c->get(Config::class)));
        $this->container->singleton(TenantManager::class, static fn (): TenantManager => new TenantManager());
        $this->container->singleton(View::class, static fn (): View => new View(self::REAL_THEMES_PATH, 'default', 'default'));
        $this->container->singleton(CacheDriver::class, fn (): FileCacheDriver => new FileCacheDriver($this->cachePath));
        $this->container->singleton(Cache::class, static fn (Container $c): Cache => new Cache($c->get(CacheDriver::class)));
        $this->container->singleton(Hook::class, static fn (): Hook => new Hook());
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
        $this->database->statement("CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, order_number VARCHAR(40) NOT NULL,
            guest_name VARCHAR(255) NOT NULL, guest_email VARCHAR(255) NOT NULL, shipping_address TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending', total_amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(20) NOT NULL DEFAULT 'cod', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE TABLE payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, order_id BIGINT NOT NULL,
            driver VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'pending\', amount DECIMAL(12,2) NOT NULL,
            transaction_ref VARCHAR(100) NULL, raw_payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )');
        $this->database->statement("CREATE TABLE settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NULL, setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
            `key` VARCHAR(100) NOT NULL, value TEXT NULL, is_encrypted BOOLEAN NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NULL
        )");
    }

    private function seedSite(string $name = 'Site A'): int
    {
        $this->database->insert('INSERT INTO sites (name) VALUES (?)', [$name]);

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedOrder(int $tenantId, string $orderNumber = 'ORD-TEST-001'): int
    {
        $this->database->insert(
            'INSERT INTO orders (tenant_id, order_number, guest_name, guest_email, total_amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $orderNumber, 'Nguyen Van A', 'a@example.com', 150.0, 'momo']
        );

        return (int) $this->database->connection()->lastInsertId();
    }

    private function seedPayment(int $tenantId, int $orderId, string $driver = 'momo', string $status = 'pending'): void
    {
        $this->database->insert(
            'INSERT INTO payments (tenant_id, order_id, driver, status, amount, transaction_ref) VALUES (?, ?, ?, ?, ?, ?)',
            [$tenantId, $orderId, $driver, $status, 150.0, 'TXN-001']
        );
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

    public function testPaymentSettingsListRequiresPaymentManagePermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/payment-settings', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPaymentSettingsListDefaultsAllDriversEnabled(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['payment.manage']);

        $response = $this->router->dispatch(new Request('GET', '/admin/payment-settings', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('is-on', $response->getBody());
        self::assertStringContainsString('Ví MoMo', $response->getBody());
    }

    public function testToggleDisablesThenReEnablesDriver(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['payment.manage']);

        $this->router->dispatch(new Request(
            'POST',
            '/admin/payment-settings/momo/toggle',
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne(
            "SELECT value FROM settings WHERE tenant_id = ? AND `key` = 'payment.enabled.momo'",
            [$siteId]
        );
        self::assertSame('0', $row['value']);

        $this->router->dispatch(new Request(
            'POST',
            '/admin/payment-settings/momo/toggle',
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        $row = $this->database->selectOne(
            "SELECT value FROM settings WHERE tenant_id = ? AND `key` = 'payment.enabled.momo'",
            [$siteId]
        );
        self::assertSame('1', $row['value']);
    }

    public function testToggleRejectsUnknownDriverKey(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, ['payment.manage']);

        $response = $this->router->dispatch(new Request(
            'POST',
            '/admin/payment-settings/paypal/toggle',
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDisabledGatewayIsExcludedFromCheckoutFormAndRejectedOnSubmit(): void
    {
        $siteId = $this->seedSite();
        $this->database->statement("CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id BIGINT NOT NULL, name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL, price DECIMAL(12,2) NOT NULL, stock_quantity INT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft', image_id BIGINT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL
        )");
        $this->database->statement('CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT, order_id BIGINT NOT NULL, product_id BIGINT NOT NULL,
            product_variant_id BIGINT NULL, product_name_snapshot VARCHAR(255) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL, quantity INT NOT NULL, subtotal DECIMAL(12,2) NOT NULL
        )');
        $this->database->insert(
            "INSERT INTO products (tenant_id, name, slug, price, stock_quantity, status) VALUES (?, 'Ao thun', 'ao-thun', 100.0, 10, 'published')",
            [$siteId]
        );
        $productId = (int) $this->database->connection()->lastInsertId();

        $this->actingAs($siteId, ['payment.manage']);
        $this->router->dispatch(new Request(
            'POST',
            '/admin/payment-settings/momo/toggle',
            'example.com',
            [],
            ['_token' => $this->csrfToken()]
        ));

        $this->router->dispatch(new Request(
            'POST',
            '/cart/add',
            'example.com',
            [],
            ['product_id' => (string) $productId, 'quantity' => '1', '_token' => $this->csrfToken()]
        ));

        $checkoutResponse = $this->router->dispatch(new Request('GET', '/checkout', 'example.com'));
        self::assertStringNotContainsString('value="momo"', $checkoutResponse->getBody());
        self::assertStringContainsString('value="cod"', $checkoutResponse->getBody());

        $placeOrderResponse = $this->router->dispatch(new Request(
            'POST',
            '/checkout',
            'example.com',
            [],
            [
                'guest_name' => 'Nguyen Van A', 'guest_email' => 'a@example.com',
                'customer_lat' => '10.7769', 'customer_lng' => '106.7009',
                'payment_method' => 'momo', '_token' => $this->csrfToken(),
            ]
        ));

        self::assertStringContainsString('không khả dụng', $placeOrderResponse->getBody());
        $order = $this->database->selectOne('SELECT id FROM orders WHERE tenant_id = ?', [$siteId]);
        self::assertNull($order, 'Khong duoc tao don hang voi cong thanh toan da bi tat.');
    }

    public function testPaymentListRequiresPaymentViewPermission(): void
    {
        $siteId = $this->seedSite();
        $this->actingAs($siteId, []);

        $response = $this->router->dispatch(new Request('GET', '/admin/payments', 'example.com'));

        self::assertSame(403, $response->getStatusCode());
    }

    public function testPaymentListShowsTenantTransactionsJoinedWithOrder(): void
    {
        $siteId = $this->seedSite();
        $orderId = $this->seedOrder($siteId);
        $this->seedPayment($siteId, $orderId, 'momo', 'completed');
        $this->actingAs($siteId, ['payment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/payments', 'example.com'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('ORD-TEST-001', $response->getBody());
        self::assertStringContainsString('TXN-001', $response->getBody());
    }

    public function testPaymentListFiltersByStatus(): void
    {
        $siteId = $this->seedSite();
        $orderId1 = $this->seedOrder($siteId, 'ORD-A');
        $orderId2 = $this->seedOrder($siteId, 'ORD-B');
        $this->seedPayment($siteId, $orderId1, 'momo', 'completed');
        $this->seedPayment($siteId, $orderId2, 'vnpay', 'failed');
        $this->actingAs($siteId, ['payment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/payments', 'example.com', ['status' => 'failed']));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('ORD-B', $response->getBody());
        self::assertStringNotContainsString('ORD-A', $response->getBody());
    }

    public function testPaymentListIsIsolatedPerTenant(): void
    {
        $siteA = $this->seedSite('Site A');
        $siteB = $this->seedSite('Site B');
        $orderA = $this->seedOrder($siteA, 'ORD-SITE-A');
        $this->seedPayment($siteA, $orderA, 'momo', 'completed');

        $this->actingAs($siteB, ['payment.view']);

        $response = $this->router->dispatch(new Request('GET', '/admin/payments', 'example.com'));

        self::assertStringNotContainsString('ORD-SITE-A', $response->getBody());
    }
}
